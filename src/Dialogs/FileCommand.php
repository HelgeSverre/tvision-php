<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** Commands and broadcasts specific to the standard file and directory dialogs. */
final class FileCommand
{
    public const int Open = 1001;
    public const int Replace = 1002;
    public const int Clear = 1003;
    public const int Init = 1004;
    public const int ChangeDir = 1005;
    public const int Revert = 1006;
    public const int DirSelection = 1007;

    public const int Focused = 102;
    public const int DoubleClicked = 103;
}
