<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Application;

/**
 * The root application palette to use at runtime.
 *
 * Color preserves the library's modern dark default. ClassicColor exposes the
 * unmodified Turbo Vision colour table for compatibility and visual nostalgia.
 */
enum PaletteMode: string
{
    case Color = 'color';
    case ClassicColor = 'classic-color';
    case BlackWhite = 'black-white';
    case Monochrome = 'monochrome';
}
