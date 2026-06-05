<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

use HelgeSverre\TurboVision\Geometry\Point;

/** Immutable mouse payload: pointer position, button bitmask, double-click, wheel delta. */
final readonly class MouseEvent
{
    public function __construct(
        public Point $where,
        public int $buttons = 0,
        public bool $doubleClick = false,
        public int $wheel = 0,
    ) {}
}
