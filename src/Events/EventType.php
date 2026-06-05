<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/** The kind of a single event. Values are faithful to Turbo Vision's evXxx. */
enum EventType: int
{
    case Nothing = 0x0000;
    case MouseDown = 0x0001;
    case MouseUp = 0x0002;
    case MouseMove = 0x0004;
    case MouseAuto = 0x0008;
    case KeyDown = 0x0010;
    case Command = 0x0100;
    case Broadcast = 0x0200;

    /** True if this type's bit is set in the given routing mask. */
    public function inMask(int $mask): bool
    {
        return ($this->value & $mask) !== 0;
    }
}
