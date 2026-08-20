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
    public function __construct(private readonly bool $useDefaultBackgroundForBlack = true) {}

    /** Cursor Position (CUP): move to 0-based ($x, $y). */
    public function moveCursor(int $x, int $y): string
    {
        return "\e[" . ($y + 1) . ';' . ($x + 1) . 'H';
    }

    /** SGR sequence for a packed Turbo Vision attribute byte. */
    public function style(int $attrByte): string
    {
        return Attribute::fromByte($attrByte)->toSgr($this->useDefaultBackgroundForBlack);
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

    /** Enable button tracking and, when requested, hover/any-motion reporting. */
    public function enableMouse(bool $trackMouseMotion = false): string
    {
        return "\e[?1000h\e[?1002h"
            . ($trackMouseMotion ? "\e[?1003h" : '')
            . "\e[?1006h";
    }

    public function disableMouse(bool $trackMouseMotion = false): string
    {
        return "\e[?1006l"
            . ($trackMouseMotion ? "\e[?1003l" : '')
            . "\e[?1002l\e[?1000l";
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
