<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * A viewport (faithful to TScroller) onto a logical area larger than the view. Holds a
 * scroll offset (delta) and a logical size (limit), optionally wired to a horizontal
 * and/or vertical ScrollBar. A cmScrollBarChanged broadcast from one of its bars moves
 * delta and redraws. Subclasses override draw() to paint delta-offset content.
 */
class Scroller extends View
{
    /** cpScroller: index1=normal text, index2=highlighted text. */
    private const string PALETTE = "\x06\x07";

    public Point $delta;

    public Point $limit;

    /** Guards re-entrant redraws while several bars are reparameterised. */
    private int $drawLock = 0;

    private bool $drawFlag = false;

    public function __construct(
        Rect $bounds,
        protected ?ScrollBar $hScrollBar = null,
        protected ?ScrollBar $vScrollBar = null,
    ) {
        parent::__construct($bounds);
        $this->delta = new Point(0, 0);
        $this->limit = new Point(0, 0);
        $this->options |= State::Selectable;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function getHScrollBar(): ?ScrollBar
    {
        return $this->hScrollBar;
    }

    public function getVScrollBar(): ?ScrollBar
    {
        return $this->vScrollBar;
    }

    /** Set the logical size and reparameterise the attached bars (faithful setLimit). */
    public function setLimit(int $x, int $y): void
    {
        $x = max(0, $x);
        $y = max(0, $y);
        $changed = $x !== $this->limit->x || $y !== $this->limit->y;
        $this->limit = new Point($x, $y);
        $this->drawLock++;

        if ($this->hScrollBar !== null) {
            $this->hScrollBar->setParams(
                $this->hScrollBar->value,
                0,
                $x - $this->bounds->width(),
                $this->bounds->width() - 1,
                $this->hScrollBar->arrowStep,
            );
        }
        if ($this->vScrollBar !== null) {
            $this->vScrollBar->setParams(
                $this->vScrollBar->value,
                0,
                $y - $this->bounds->height(),
                $this->bounds->height() - 1,
                $this->vScrollBar->arrowStep,
            );
        }

        $this->drawLock--;
        if ($changed) {
            $this->drawFlag = true;
        }
        $this->checkDraw();
    }

    /** Scroll to a logical position by driving the bars (which clamp). */
    public function scrollTo(int $x, int $y): void
    {
        $this->drawLock++;
        $this->hScrollBar?->setValue($x);
        $this->vScrollBar?->setValue($y);
        $this->drawLock--;
        // Sync delta from the bars' (possibly clamped) values.
        $this->scrollDraw();
        $this->checkDraw();
    }

    /** Recompute delta from the bars' values; redraw (deferred under drawLock). */
    public function scrollDraw(): void
    {
        $dx = $this->hScrollBar !== null ? $this->hScrollBar->value : 0;
        $dy = $this->vScrollBar !== null ? $this->vScrollBar->value : 0;

        if ($dx !== $this->delta->x || $dy !== $this->delta->y) {
            $this->delta = new Point($dx, $dy);
            if ($this->drawLock !== 0) {
                $this->drawFlag = true;
            } else {
                $this->drawView();
            }
        }
    }

    private function checkDraw(): void
    {
        if ($this->drawLock === 0 && $this->drawFlag) {
            $this->drawFlag = false;
            $this->drawView();
        }
    }

    public function changeBounds(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->drawLock++;
        $this->setLimit($this->limit->x, $this->limit->y);
        $this->drawLock--;
        $this->drawFlag = false;
        $this->drawView();
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isCommand(Cmd::ScrollBarChanged)) {
            $info = $event->asMessage()?->info;
            if ($info === $this->hScrollBar || $info === $this->vScrollBar) {
                $this->scrollDraw();
            }
        }
    }
}
