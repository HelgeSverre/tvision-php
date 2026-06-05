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
        $fg = $this->fg->value;
        $bg = $this->bg->value;

        $codes = [
            $fg < 8 ? 30 + $fg : 90 + ($fg - 8),
            $bg < 8 ? 40 + $bg : 100 + ($bg - 8),
        ];

        if ($this->blink) {
            $codes[] = 5;
        }

        return "\e[0;" . implode(';', $codes) . 'm';
    }
}
