<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/** One screen cell: a single grapheme plus a packed attribute byte. */
final readonly class Cell
{
    public string $char;

    public int $attr;

    public function __construct(
        string $char = ' ',
        int $attr = 0x07,
    ) {
        // Screen uses a sentinel NUL only in its private invalidated front buffer.
        $this->char = $attr === -1 && $char === "\0" ? $char : TerminalText::cellGlyph($char);
        $this->attr = $attr;
    }

    public static function of(string $char, Attribute $attribute): self
    {
        return new self($char, $attribute->toByte());
    }

    public function equals(Cell $other): bool
    {
        return $this->char === $other->char && $this->attr === $other->attr;
    }
}
