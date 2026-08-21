<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/** Modifier bits shared by legacy xterm and the Kitty keyboard protocol. */
final class KeyModifier
{
    public const int None = 0;

    /**
     * These protocol-facing bits intentionally follow CSI-u/Kitty rather than
     * Turbo Vision's historical hardware-state bit layout. Key carries the
     * legacy kbXxx identities; KeyDownEvent::modifiers preserves modern input
     * metadata independently.
     */
    public const int Shift = 1 << 0;

    public const int Alt = 1 << 1;

    public const int Ctrl = 1 << 2;

    public const int Super = 1 << 3;

    public const int Hyper = 1 << 4;

    public const int Meta = 1 << 5;

    public const int CapsLock = 1 << 6;

    public const int NumLock = 1 << 7;

    /** All modifier bits the framework currently understands. */
    public const int Known = self::Shift
        | self::Alt
        | self::Ctrl
        | self::Super
        | self::Hyper
        | self::Meta
        | self::CapsLock
        | self::NumLock;
}
