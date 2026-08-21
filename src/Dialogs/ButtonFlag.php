<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** bf* flags accepted by Button. They are bit flags, not mutually exclusive. */
final class ButtonFlag
{
    public const int Normal = 0x00;
    public const int Default = 0x01;
    public const int LeftJust = 0x02;
    public const int Broadcast = 0x04;
    public const int GrabFocus = 0x08;
}
