<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\KitchenSink;

/** Commands owned by the Kitchen Sink demo. */
final class KitchenSinkCommand
{
    public const int Controls = 2_000;
    public const int MessageBoxes = 2_001;
    public const int Memo = 2_002;
    public const int Editor = 2_003;
    public const int FileDialog = 2_004;
    public const int ChangeDirectory = 2_005;
    public const int Canvas = 2_006;
    public const int Outline = 2_007;
    public const int Terminal = 2_008;
    public const int Colors = 2_009;
    public const int Resources = 2_010;
    public const int ContextMenu = 2_011;
    public const int About = 2_012;
    public const int ResetDesktop = 2_013;
    public const int ToggleAdvanced = 2_014;
    public const int CycleTheme = 2_015;
    public const int ThemeDark = 2_016;
    public const int ThemeClassic = 2_017;
    public const int ThemeBlackWhite = 2_018;
    public const int ThemeMonochrome = 2_019;
    public const int ContextInspect = 2_020;
    public const int ContextReset = 2_021;

    private function __construct() {}
}
