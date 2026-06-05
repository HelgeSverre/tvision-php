<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/**
 * A text attribute: foreground + background color (+ blink). Bridges the modern
 * named colors and the classic Turbo Vision attribute byte (fg | bg<<4 | blink<<7).
 */
final readonly class Attribute
{
    public function __construct(
        public Color $fg = Color::LightGray,
        public Color $bg = Color::Black,
        public bool $blink = false,
    ) {}

    /** Pack into a classic CGA attribute byte (background limited to 3 bits). */
    public function toByte(): int
    {
        return ($this->fg->value & 0x0F)
            | (($this->bg->value & 0x07) << 4)
            | ($this->blink ? 0x80 : 0);
    }

    public static function fromByte(int $byte): self
    {
        return new self(
            Color::from($byte & 0x0F),
            Color::from(($byte >> 4) & 0x07),
            (bool) ($byte & 0x80),
        );
    }

    /** An ANSI SGR sequence that resets then applies this attribute. */
    public function toSgr(): string
    {
        $codes = [
            $this->fg->value < 8 ? 30 + self::ansiCode($this->fg) : 90 + self::ansiCode($this->fg),
            $this->bg->value < 8 ? 40 + self::ansiCode($this->bg) : 100 + self::ansiCode($this->bg),
        ];

        if ($this->blink) {
            $codes[] = 5;
        }

        return "\e[0;" . implode(';', $codes) . 'm';
    }

    /**
     * Map a CGA color index to its ANSI SGR colour code. CGA orders its low three
     * bits R-G-B opposite to ANSI's B-G-R, so blue<->red (and cyan<->brown) must be
     * swapped, otherwise Turbo Vision "blue" would render as red on a real terminal.
     */
    private static function ansiCode(Color $color): int
    {
        return match ($color->value & 0x07) {
            1 => 4,  // CGA Blue  -> ANSI Blue
            3 => 6,  // CGA Cyan  -> ANSI Cyan
            4 => 1,  // CGA Red   -> ANSI Red
            6 => 3,  // CGA Brown -> ANSI Yellow
            default => $color->value & 0x07, // 0 Black, 2 Green, 5 Magenta, 7 Gray map straight
        };
    }
}
