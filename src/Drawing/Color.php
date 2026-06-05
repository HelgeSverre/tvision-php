<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/** The 16 CGA/ANSI colors, by their classic index (0-15). */
enum Color: int
{
    case Black = 0;
    case Blue = 1;
    case Green = 2;
    case Cyan = 3;
    case Red = 4;
    case Magenta = 5;
    case Brown = 6;
    case LightGray = 7;
    case DarkGray = 8;
    case LightBlue = 9;
    case LightGreen = 10;
    case LightCyan = 11;
    case LightRed = 12;
    case LightMagenta = 13;
    case Yellow = 14;
    case White = 15;

    /** True for the high 8 colors (indexes 8-15), rendered as ANSI "bright". */
    public function isBright(): bool
    {
        return $this->value >= 8;
    }
}
