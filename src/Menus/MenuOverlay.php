<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;

/** @internal Full-screen input shield used while a MenuBar pull-down is open. */
final class MenuOverlay extends Group
{
    public function __construct(Rect $bounds, private readonly MenuBar $menuBar)
    {
        parent::__construct($bounds);
    }

    public function isOpaque(): bool
    {
        return false;
    }

    public function handleEvent(Event $event): void
    {
        if (($event->what->value & EventMask::Positional) !== 0) {
            $this->menuBar->handleOverlayMouse($event);

            return;
        }

        parent::handleEvent($event);
    }
}
