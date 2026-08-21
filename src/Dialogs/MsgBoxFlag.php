<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** mf* bit flags used by MessageBox. */
final class MsgBoxFlag
{
    public const int Warning = 0x0000;
    public const int Error = 0x0001;
    public const int Information = 0x0002;
    public const int Confirmation = 0x0003;
    public const int YesButton = 0x0100;
    public const int NoButton = 0x0200;
    public const int OkButton = 0x0400;
    public const int CancelButton = 0x0800;
    public const int YesNoCancel = self::YesButton | self::NoButton | self::CancelButton;
    public const int OkCancel = self::OkButton | self::CancelButton;
}
