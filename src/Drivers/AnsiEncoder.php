<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Drawing\Attribute;

/**
 * Pure (no-I/O) translator from drawing intent to ANSI/VT byte strings.
 * Coordinates are 0-based; emitted CUP sequences are 1-based, as terminals expect.
 */
final class AnsiEncoder
{
    /** Cursor Position (CUP): move to 0-based ($x, $y). */
    public function moveCursor(int $x, int $y): string
    {
        return "\e[" . ($y + 1) . ';' . ($x + 1) . 'H';
    }

    /** SGR sequence for a packed Turbo Vision attribute byte. */
    public function style(int $attrByte): string
    {
        return Attribute::fromByte($attrByte)->toSgr();
    }

    /** Move + style + literal text — the renderer's per-run workhorse. */
    public function run(int $x, int $y, string $text, int $attrByte): string
    {
        return $this->moveCursor($x, $y) . $this->style($attrByte) . $text;
    }

    public function reset(): string
    {
        return "\e[0m";
    }

    public function clearScreen(): string
    {
        return "\e[2J\e[H";
    }

    public function hideCursor(): string
    {
        return "\e[?25l";
    }

    public function showCursor(): string
    {
        return "\e[?25h";
    }

    public function enterAltScreen(): string
    {
        return "\e[?1049h";
    }

    public function leaveAltScreen(): string
    {
        return "\e[?1049l";
    }

    /** Enable xterm normal-tracking (1000) + SGR extended (1006) mouse reporting. */
    public function enableMouse(): string
    {
        return "\e[?1000h\e[?1006h";
    }

    public function disableMouse(): string
    {
        return "\e[?1006l\e[?1000l";
    }

    /**
     * Begin a synchronized update (DEC private mode 2026). Modern terminals
     * (Ghostty, kitty, WezTerm, recent xterm) buffer everything until the matching
     * endSyncUpdate() and present the frame atomically — no tearing or flicker.
     * Terminals that don't support it ignore the sequence harmlessly.
     */
    public function beginSyncUpdate(): string
    {
        return "\e[?2026h";
    }

    public function endSyncUpdate(): string
    {
        return "\e[?2026l";
    }
}
