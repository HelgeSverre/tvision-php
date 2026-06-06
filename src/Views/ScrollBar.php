<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;

/**
 * A vertical (width 1) or horizontal (height 1) scroll bar (faithful to TScrollBar).
 * Holds a value in [minVal, maxVal] with arrow/page steps, computes an integer thumb
 * position, draws a track + thumb + arrows, and broadcasts cmScrollBarChanged when the
 * value moves. Orientation is auto-detected from bounds (size.x === 1 => vertical).
 */
class ScrollBar extends View
{
    /** cpScrollBar: index1=page area, index2=arrows, index3=indicator/thumb. */
    private const string PALETTE = "\x04\x05\x05";

    public int $value = 0;

    public int $minVal = 0;

    public int $maxVal = 0;

    public int $pageStep = 1;

    public int $arrowStep = 1;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);

        if ($bounds->width() === 1) {
            $this->growMode = State::GrowLoX | State::GrowHiX | State::GrowHiY;
        } else {
            $this->growMode = State::GrowLoY | State::GrowHiX | State::GrowHiY;
        }
    }

    public function isVertical(): bool
    {
        return $this->bounds->width() === 1;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    /** The track length along the bar's long axis, faithful min of 2. */
    public function getSize(): int
    {
        $s = $this->isVertical() ? $this->bounds->height() : $this->bounds->width();

        return max(2, $s);
    }

    /** Thumb position (1-based offset along the track), faithful to TScrollBar::getPos. */
    public function getPos(): int
    {
        $r = $this->maxVal - $this->minVal;
        if ($r === 0) {
            return 1;
        }

        return intdiv(($this->value - $this->minVal) * ($this->getSize() - 3) + intdiv($r, 2), $r) + 1;
    }

    /**
     * Set the full value model. Clamps value into [min, max] (after normalising
     * max >= min), and broadcasts cmScrollBarChanged if the value actually moved.
     */
    public function setParams(int $value, int $min, int $max, int $pageStep, int $arrowStep): void
    {
        $max = max($max, $min);
        $value = max($min, min($max, $value));

        $changed = $value !== $this->value || $min !== $this->minVal || $max !== $this->maxVal;
        $valueMoved = $value !== $this->value;

        if ($changed) {
            $this->value = $value;
            $this->minVal = $min;
            $this->maxVal = $max;
            $this->drawView();
            if ($valueMoved) {
                $this->scrollDraw();
            }
        }

        $this->pageStep = $pageStep;
        $this->arrowStep = $arrowStep;
    }

    public function setRange(int $min, int $max): void
    {
        $this->setParams($this->value, $min, $max, $this->pageStep, $this->arrowStep);
    }

    public function setStep(int $pageStep, int $arrowStep): void
    {
        $this->setParams($this->value, $this->minVal, $this->maxVal, $pageStep, $arrowStep);
    }

    public function setValue(int $value): void
    {
        $this->setParams($value, $this->minVal, $this->maxVal, $this->pageStep, $this->arrowStep);
    }

    /** Broadcast that this bar's value changed so any attached Scroller redraws. */
    public function scrollDraw(): void
    {
        $this->owner?->handleEvent(
            Event::broadcast(Cmd::ScrollBarChanged, $this),
        );
    }

    public function draw(): void
    {
        $this->drawPos($this->getPos());
    }

    /** Paint the bar with the thumb at track offset $pos (faithful to drawPos). */
    public function drawPos(int $pos): void
    {
        $size = $this->getSize();
        $last = $size - 1;

        $glyphs = $this->isVertical()
            ? [Glyphs::ARROW_UP, Glyphs::ARROW_DOWN]
            : [Glyphs::ARROW_LEFT, Glyphs::ARROW_RIGHT];

        $arrowColor = $this->getColor(2);
        $trackColor = $this->getColor(1);
        $thumbColor = $this->getColor(3);

        $b = new DrawBuffer($size);
        $b->moveChar(0, $glyphs[0], $arrowColor, 1);

        if ($this->maxVal === $this->minVal) {
            $b->moveChar(1, Glyphs::SCROLL_TRACK, $trackColor, $last - 1);
        } else {
            $b->moveChar(1, Glyphs::SCROLL_TRACK, $trackColor, $last - 1);
            $b->moveChar($pos, Glyphs::SCROLL_THUMB, $thumbColor, 1);
        }

        $b->moveChar($last, $glyphs[1], $arrowColor, 1);

        if ($this->isVertical()) {
            // Blit one cell per row down the column.
            for ($y = 0; $y < $size; $y++) {
                $cell = $b->cells()[$y];
                $rowBuf = new DrawBuffer(1);
                $rowBuf->moveChar(0, $cell->char, $cell->attr, 1);
                $this->writeLine(0, $y, 1, 1, $rowBuf);
            }
        } else {
            $this->writeLine(0, 0, $size, 1, $b);
        }
    }

    /** Signed step for a part code, faithful to TScrollBar::scrollStep. */
    public function scrollStep(int $part): int
    {
        $step = ($part & 2) !== 0 ? $this->pageStep : $this->arrowStep;

        return ($part & 1) !== 0 ? $step : -$step;
    }

    public function handleEvent(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        $part = ScrollBarPart::Indicator;
        $absolute = null; // when set (Home/End), jump straight to this value

        if (! $this->isVertical()) {
            $code = $key->keyCode;
            $part = match (true) {
                $code === Key::Left->value => ScrollBarPart::LeftArrow,
                $code === Key::Right->value => ScrollBarPart::RightArrow,
                $code === Key::Home->value => -1,
                $code === Key::End->value => -1,
                default => ScrollBarPart::Indicator,
            };
            if ($code === Key::Home->value) {
                $absolute = $this->minVal;
            } elseif ($code === Key::End->value) {
                $absolute = $this->maxVal;
            }
        } else {
            $code = $key->keyCode;
            $part = match (true) {
                $code === Key::Up->value => ScrollBarPart::UpArrow,
                $code === Key::Down->value => ScrollBarPart::DownArrow,
                $code === Key::PageUp->value => ScrollBarPart::PageUp,
                $code === Key::PageDown->value => ScrollBarPart::PageDown,
                default => ScrollBarPart::Indicator,
            };
            if ($code === Key::Home->value) {
                $absolute = $this->minVal;
            } elseif ($code === Key::End->value) {
                $absolute = $this->maxVal;
            }
        }

        if ($absolute === null && $part === ScrollBarPart::Indicator) {
            return; // not a key this bar handles
        }

        $newValue = $absolute ?? ($this->value + $this->scrollStep($part));
        $this->setValue($newValue);
        $this->clearEvent($event);
    }
}
