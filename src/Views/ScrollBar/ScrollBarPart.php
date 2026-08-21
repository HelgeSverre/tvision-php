<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views\ScrollBar;

/** ScrollBar part codes and legacy orientation flags, verbatim from views.h. */
final class ScrollBarPart
{
    // sb* part codes (which region was clicked / which key was pressed).
    public const int LeftArrow = 0;
    public const int RightArrow = 1;
    public const int PageLeft = 2;
    public const int PageRight = 3;
    public const int UpArrow = 4;
    public const int DownArrow = 5;
    public const int PageUp = 6;
    public const int PageDown = 7;
    public const int Indicator = 8;

    // Legacy sb* flags accepted by Window::standardScrollBar().
    public const int Horizontal = 0x000;
    public const int Vertical = 0x001;
    public const int HandleKeyboard = 0x002;
}
