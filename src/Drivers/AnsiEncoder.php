<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Support\IntMath;
use InvalidArgumentException;

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
        if ($x < 0 || $y < 0) {
            throw new InvalidArgumentException('Cursor coordinates must be non-negative.');
        }

        return "\e[" . IntMath::add($y, 1) . ';' . IntMath::add($x, 1) . 'H';
    }

    /** SGR sequence for a classic attribute byte or extended cell value. */
    public function style(int $attrByte): string
    {
        return Attribute::fromCellValue($attrByte)->toSgr($this->useDefaultBackgroundForBlack);
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
     * Begin a synchronized update (DEC private mode 2026). Supporting terminals buffer
     * output until endSyncUpdate() and present the frame atomically.
     */
    public function beginSyncUpdate(): string
    {
        return "\e[?2026h";
    }

    public function endSyncUpdate(): string
    {
        return "\e[?2026l";
    }

    /** Push Kitty's disambiguated-keyboard mode on terminals that advertise it. */
    public function pushKittyKeyboard(): string
    {
        return "\e[>1u";
    }

    /** Restore the keyboard protocol state active before pushKittyKeyboard(). */
    public function popKittyKeyboard(): string
    {
        return "\e[<u";
    }
}
