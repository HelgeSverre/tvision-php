<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

/**
 * A framed, movable, resizable, zoomable, closable group (faithful to TWindow). Owns a
 * Frame as its first subview, carries a title + number + wf* flags, resolves color
 * through one of the three window palettes, and (Task 14) handles cmClose/cmZoom/cmResize
 * plus Tab focus cycling. Implements FrameOwner so its Frame can draw itself.
 */
class Window extends Group implements FrameOwner
{
    public int $flags = WindowFlags::Default;

    protected int $paletteIndex = WindowPalette::Blue;

    protected Rect $zoomRect;

    protected ?Frame $frame = null;

    public function __construct(
        Rect $bounds,
        protected string $title = '',
        protected int $number = 0,
    ) {
        parent::__construct($bounds);

        $this->state |= State::Shadow;
        $this->options |= State::Selectable | State::TopSelect;
        $this->growMode = State::GrowAll | State::GrowRel;
        $this->zoomRect = $bounds;

        $this->frame = $this->initFrame($this->getExtent());
        if ($this->frame !== null) {
            $this->insert($this->frame);
        }
    }

    /** Override to supply a custom Frame subclass. */
    protected function initFrame(Rect $extent): ?Frame
    {
        return new Frame($extent);
    }

    // --- FrameOwner ---

    public function frameTitle(): string
    {
        return $this->title;
    }

    public function frameFlags(): int
    {
        return $this->flags;
    }

    public function frameNumber(): int
    {
        return $this->number;
    }

    public function frameIsZoomed(): bool
    {
        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();

        return $this->bounds->width() === $maxW && $this->bounds->height() === $maxH;
    }

    // --- palette ---

    public function setPalette(int $index): void
    {
        $this->paletteIndex = $index;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(WindowPalette::byteFor($this->paletteIndex));
    }

    // --- geometry ---

    /** Faithful min window size 16x6; max is the desktop extent if owned, else unbounded. */
    public function sizeLimits(): array
    {
        $maxW = PHP_INT_MAX;
        $maxH = PHP_INT_MAX;
        if ($this->owner !== null) {
            $ext = $this->owner->getExtent();
            $maxW = $ext->width();
            $maxH = $ext->height();
        }

        return [16, 6, $maxW, $maxH];
    }

    /**
     * Build, insert and return a standard scroll bar on the right (vertical) or bottom
     * (horizontal) edge, faithful to TWindow::standardScrollBar positions.
     */
    public function standardScrollBar(int $options): ScrollBar
    {
        $ext = $this->getExtent();

        if (($options & ScrollBarPart::Vertical) !== 0) {
            $r = Rect::of($ext->b->x - 1, $ext->a->y + 1, $ext->b->x, $ext->b->y - 1);
        } else {
            $r = Rect::of($ext->a->x + 2, $ext->b->y - 1, $ext->b->x - 2, $ext->b->y);
        }

        $bar = new ScrollBar($r);
        $this->insert($bar);
        if (($options & ScrollBarPart::HandleKeyboard) !== 0) {
            $bar->options |= State::PostProcess;
        }

        return $bar;
    }

    public function handleEvent(Event $event): void
    {
        // Command + Tab handling added in Task 14.
        parent::handleEvent($event);
    }
}
