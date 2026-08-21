<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\KitchenSink;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** A large logical surface demonstrating Scroller, both bars, mouse hit-testing, and clipping. */
final class KitchenSinkCanvas extends Scroller
{
    private Point $marker;

    public function __construct(Rect $bounds, ?ScrollBar $hScrollBar, ?ScrollBar $vScrollBar)
    {
        parent::__construct($bounds, $hScrollBar, $vScrollBar);
        $this->marker = new Point(14, 7);
        $this->eventMask |= EventMask::Mouse;
        $this->setLimit(120, 40);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $normal = $this->getColor(1);
        $accent = $this->getColor(2);

        for ($screenY = 0; $screenY < $height; $screenY++) {
            $logicalY = $screenY + $this->delta->y;
            $row = new DrawBuffer($width, $normal);
            $row->moveChar(0, ' ', $normal, $width);
            for ($screenX = 0; $screenX < $width; $screenX++) {
                $logicalX = $screenX + $this->delta->x;
                $glyph = ($logicalX % 10 === 0 && $logicalY % 5 === 0) ? '┼'
                    : ($logicalX % 10 === 0 ? '│' : ($logicalY % 5 === 0 ? '─' : '·'));
                $row->moveChar($screenX, $glyph, $normal, 1);
            }
            if ($logicalY === $this->marker->y) {
                $row->moveChar($this->marker->x - $this->delta->x, '◆', $accent, 1);
            }
            if ($logicalY === 1) {
                $row->moveStr(2 - $this->delta->x, '120 x 40 LOGICAL CANVAS · click to move marker', $accent);
            }
            $this->writeLine(0, $screenY, $width, 1, $row);
        }
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what !== EventType::MouseDown) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null || ! $this->mouseInView($mouse->where)) {
            return;
        }
        $local = $this->makeLocal($mouse->where);
        $this->marker = new Point($local->x + $this->delta->x, $local->y + $this->delta->y);
        $this->drawView();
        $this->clearEvent($event);
    }
}
