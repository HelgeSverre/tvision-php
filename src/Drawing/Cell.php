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

    /** The default blank cell: a space with attribute 0x07. */
    public static function blank(): self
    {
        return new self();
    }

    /** The front-buffer "never painted" sentinel; Screen internals only. */
    public static function sentinel(): self
    {
        return new self("\0", -1);
    }

    /** An Attribute-object flavored constructor. */
    public static function of(string $char, Attribute $attribute): self
    {
        return new self($char, $attribute->toCellValue());
    }

    /**
     * A copy of this cell with substituted fields — the immutable-update path
     * for recoloring or re-glyphing without touching the original.
     */
    public function with(string $char, int $attr): self
    {
        return new self($char, $attr);
    }

    public function equals(Cell $other): bool
    {
        return $this->char === $other->char && $this->attr === $other->attr;
    }
}
