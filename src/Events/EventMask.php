<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/** Routing bit-masks for groups of event types (faithful to evXxx groupings). */
final class EventMask
{
    public const int MouseDown = 0x0001;
    public const int MouseUp = 0x0002;
    public const int MouseMove = 0x0004;
    public const int MouseAuto = 0x0008;
    public const int Mouse = 0x000F;
    public const int Keyboard = 0x0010;
    public const int Command = 0x0100;
    public const int Broadcast = 0x0200;
    public const int Message = 0xFF00;

    /** Mouse events are routed to the subview under the pointer. */
    public const int Positional = self::Mouse;

    /** Keyboard events are routed to the focused subview chain. */
    public const int Focused = self::Keyboard;
}
