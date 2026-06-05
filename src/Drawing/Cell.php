<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/** One screen cell: a single grapheme plus a packed attribute byte. */
final readonly class Cell
{
    public function __construct(
        public string $char = ' ',
        public int $attr = 0x07,
    ) {}

    public static function of(string $char, Attribute $attribute): self
    {
        return new self($char, $attribute->toByte());
    }

    public function equals(Cell $other): bool
    {
        return $this->char === $other->char && $this->attr === $other->attr;
    }
}
