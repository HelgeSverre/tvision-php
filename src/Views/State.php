<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

/**
 * The faithful Turbo Vision view-flag families: sf* (state), of* (options), and
 * gf* (grow mode). Kept as plain typed int constants because the originals are
 * freely bit-OR'd. Values verbatim from docs/references/source/tvision-0.8/lib/views.h.
 */
final class State
{
    // --- sf* : state flags (View::$state) ---
    public const int Visible = 0x001;
    public const int CursorVis = 0x002;
    public const int CursorIns = 0x004;
    public const int Shadow = 0x008;
    public const int Active = 0x010;
    public const int Selected = 0x020;
    public const int Focused = 0x040;
    public const int Dragging = 0x080;
    public const int Disabled = 0x100;
    public const int Modal = 0x200;
    public const int Default = 0x400;
    public const int Exposed = 0x800;

    // --- of* : option flags (View::$options) ---
    public const int Selectable = 0x001;
    public const int TopSelect = 0x002;
    public const int FirstClick = 0x004;
    public const int Framed = 0x008;
    public const int PreProcess = 0x010;
    public const int PostProcess = 0x020;
    public const int Buffered = 0x040;
    public const int Tileable = 0x080;
    public const int CenterX = 0x100;
    public const int CenterY = 0x200;
    public const int Centered = 0x300;
    public const int Validate = 0x400;

    // --- gf* : grow-mode flags (View::$growMode) ---
    public const int GrowLoX = 0x01;
    public const int GrowLoY = 0x02;
    public const int GrowHiX = 0x04;
    public const int GrowHiY = 0x08;
    public const int GrowAll = 0x0f;
    public const int GrowRel = 0x10;
    public const int GrowFixed = 0x20;
}
