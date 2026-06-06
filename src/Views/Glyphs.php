<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

/**
 * The Unicode graphemes M2 views paint, mapped from Turbo Vision's CP437 semigraphics.
 * Single source of truth: a future CP437/terminal-quirk mode changes only this file.
 * Icon strings embed ~..~ highlight markers consumed by DrawBuffer::moveCStr.
 */
final class Glyphs
{
    // Single-line box (inactive window frame).
    public const string SINGLE_TOP_LEFT = '┌';
    public const string SINGLE_TOP_RIGHT = '┐';
    public const string SINGLE_BOTTOM_LEFT = '└';
    public const string SINGLE_BOTTOM_RIGHT = '┘';
    public const string SINGLE_HORIZONTAL = '─';
    public const string SINGLE_VERTICAL = '│';

    // Double-line box (active window frame).
    public const string DOUBLE_TOP_LEFT = '╔';
    public const string DOUBLE_TOP_RIGHT = '╗';
    public const string DOUBLE_BOTTOM_LEFT = '╚';
    public const string DOUBLE_BOTTOM_RIGHT = '╝';
    public const string DOUBLE_HORIZONTAL = '═';
    public const string DOUBLE_VERTICAL = '║';

    // Scroll bar.
    public const string ARROW_LEFT = '◄';
    public const string ARROW_RIGHT = '►';
    public const string ARROW_UP = '▲';
    public const string ARROW_DOWN = '▼';
    public const string SCROLL_TRACK = '░';
    public const string SCROLL_THUMB = '▒';

    // Frame icons (with ~hotkey~ markers).
    public const string CLOSE_ICON = '[~■~]';
    public const string ZOOM_ICON = '[~↑~]';
    public const string UNZOOM_ICON = '[~↓~]';
    public const string DRAG_ICON = '~──~';
}
