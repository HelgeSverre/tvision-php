<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;

/**
 * A vertical (width 1) or horizontal (height 1) scroll bar (faithful to TScrollBar).
 * Holds a value in [minVal, maxVal] with arrow/page steps, computes an integer thumb
 * position, draws a track + thumb + arrows, and broadcasts cmScrollBarChanged when the
 * value moves. Orientation can be explicit or inferred from the bounds.
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

    private readonly ?ScrollBarOrientation $orientation;

    /** The part held by a captured pointer gesture, or null when idle. */
    private ?int $mousePart = null;

    public function __construct(
        Rect $bounds,
        ?ScrollBarOrientation $orientation = null,
    ) {
        parent::__construct($bounds);

        $this->orientation = $orientation;
        $this->eventMask |= EventMask::Mouse;

        if ($this->isVertical()) {
            $this->growMode = State::GrowLoX | State::GrowHiX | State::GrowHiY;
        } else {
            $this->growMode = State::GrowLoY | State::GrowHiX | State::GrowHiY;
        }
    }

    public static function horizontal(Rect $bounds): self
    {
        return new self($bounds, ScrollBarOrientation::Horizontal);
    }

    public static function vertical(Rect $bounds): self
    {
        return new self($bounds, ScrollBarOrientation::Vertical);
    }

    public function isVertical(): bool
    {
        return $this->orientation === ScrollBarOrientation::Vertical
            || ($this->orientation === null && $this->bounds->width() === 1);
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
        if ($this->maxVal === $this->minVal) {
            return 1;
        }

        // Integer intermediates overflow for a legitimate full-width model such as
        // [PHP_INT_MIN, PHP_INT_MAX]. A bounded ratio keeps the same nearest-position
        // behavior while ensuring no caller-controlled range is promoted into intdiv().
        $range = (float) $this->maxVal - (float) $this->minVal;
        $relative = ((float) $this->value - (float) $this->minVal) / $range;
        $relative = max(0.0, min(1.0, $relative));
        $track = max(0, $this->getSize() - 3);
        $scaled = floor($relative * $track + 0.5);
        // Large in-range integers round to 2^63 as doubles on 64-bit PHP. Compare in
        // the float domain first and reuse the exact integer track before casting.
        $offset = $scaled >= (float) $track ? $track : (int) $scaled;

        return IntMath::add(1, $offset);
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

        $this->pageStep = max(0, $pageStep);
        $this->arrowStep = max(0, $arrowStep);
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
        // These fields are public for Turbo Vision compatibility, so normalize again
        // here in case application code mutated them after setParams().
        $step = max(0, ($part & 2) !== 0 ? $this->pageStep : $this->arrowStep);

        return ($part & 1) !== 0 ? $step : -$step;
    }

    /** Return the part under a root-relative mouse position, or -1 when outside. */
    public function getPartCode(Point $where): int
    {
        $mouse = $this->makeLocal($where);
        if (! $this->getExtent()->grow(1, 1)->contains($mouse)) {
            return -1;
        }

        $position = $this->getPos();
        $last = $this->getSize() - 1;
        $mark = $this->isVertical() ? $mouse->y : $mouse->x;
        if ($this->getSize() === 2) {
            return $mark < 1 ? ScrollBarPart::LeftArrow : ScrollBarPart::RightArrow;
        }
        if ($mark === $position) {
            return ScrollBarPart::Indicator;
        }

        $part = match (true) {
            $mark < 1 => ScrollBarPart::LeftArrow,
            $mark < $position => ScrollBarPart::PageLeft,
            $mark < $last => ScrollBarPart::PageRight,
            default => ScrollBarPart::RightArrow,
        };

        return $this->isVertical() ? $part + ScrollBarPart::UpArrow : $part;
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::MouseDown) {
            $this->beginMouseGesture($event);

            return;
        }
        if ($this->getState(State::Dragging)) {
            $this->continueMouseGesture($event);

            return;
        }

        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        $part = ScrollBarPart::Indicator;
        $absolute = null; // when set (Home/End), jump straight to this value

        if (! $this->isVertical()) {
            $code = $key->keyCode;
            $part = match (true) {
                $code === Key::Left->value && ($key->modifiers & KeyModifier::Ctrl) !== 0 => ScrollBarPart::PageLeft,
                $code === Key::Right->value && ($key->modifiers & KeyModifier::Ctrl) !== 0 => ScrollBarPart::PageRight,
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

        $this->owner?->handleEvent(Event::broadcast(Cmd::ScrollBarClicked, $this));
        $newValue = $absolute ?? IntMath::add($this->value, $this->scrollStep($part));
        $this->setValue($newValue);
        $this->clearEvent($event);
    }

    private function beginMouseGesture(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        $part = $this->getPartCode($mouse->where);
        if ($part < 0) {
            return;
        }

        $this->owner?->handleEvent(Event::broadcast(Cmd::ScrollBarClicked, $this));
        $this->mousePart = $part;
        $this->setState(State::Dragging, true);

        if ($part !== ScrollBarPart::Indicator) {
            $this->stepWhenPointerRemainsOnPart($mouse->where);
        }
        $this->clearEvent($event);
    }

    private function continueMouseGesture(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        if ($this->mousePart === ScrollBarPart::Indicator) {
            if ($event->what === EventType::MouseMove || $event->what === EventType::MouseUp) {
                $position = $this->thumbPositionFor($mouse->where);
                $this->drawPos($position);
                if ($event->what === EventType::MouseUp) {
                    $this->setValue($this->valueForThumbPosition($position));
                    $this->finishMouseGesture();
                }
                $this->clearEvent($event);
            }

            return;
        }

        if ($event->what === EventType::MouseAuto) {
            $this->stepWhenPointerRemainsOnPart($mouse->where);
        }
        if ($event->what === EventType::MouseUp) {
            $this->finishMouseGesture();
        }
        if ($event->what === EventType::MouseAuto
            || $event->what === EventType::MouseMove
            || $event->what === EventType::MouseUp
        ) {
            $this->clearEvent($event);
        }
    }

    private function stepWhenPointerRemainsOnPart(Point $where): void
    {
        if ($this->mousePart !== null && $this->getPartCode($where) === $this->mousePart) {
            $this->setValue(IntMath::add($this->value, $this->scrollStep($this->mousePart)));
        }
    }

    private function thumbPositionFor(Point $where): int
    {
        $local = $this->makeLocal($where);
        $mark = $this->isVertical() ? $local->y : $local->x;
        $last = $this->getSize() - 1;
        if (! $this->getExtent()->grow(1, 1)->contains($local)) {
            return $this->getPos();
        }

        return max(1, min($last - 1, $mark));
    }

    private function valueForThumbPosition(int $position): int
    {
        $track = $this->getSize() - 3;
        if ($track <= 0 || $this->maxVal === $this->minVal) {
            return $this->minVal;
        }

        $ratio = max(0.0, min(1.0, ($position - 1) / $track));
        $value = (float) $this->minVal + $ratio * ((float) $this->maxVal - (float) $this->minVal);

        return $value >= (float) $this->maxVal ? $this->maxVal : (int) floor($value + 0.5);
    }

    private function finishMouseGesture(): void
    {
        $this->mousePart = null;
        $this->setState(State::Dragging, false);
        $this->drawView();
    }
}
