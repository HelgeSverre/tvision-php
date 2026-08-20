<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/**
 * A single-row paint helper (faithful to Turbo Vision's TDrawBuffer). Views fill
 * one per row; the renderer blits the cells into the screen Buffer. All writes are
 * multibyte-aware and clipped to [0, width).
 */
final class DrawBuffer
{
    /** @var Cell[] indexed by column 0..width-1 */
    private array $cells;

    public function __construct(
        public readonly int $width,
        int $fillAttr = 0x07,
    ) {
        $this->clear($fillAttr);
    }

    public function clear(int $attr = 0x07): void
    {
        $this->cells = array_fill(0, max(0, $this->width), new Cell(' ', $attr));
    }

    public function moveChar(int $x, string $char, int $attr, int $count): void
    {
        for ($i = 0; $i < $count; $i++, $x++) {
            if ($x >= 0 && $x < $this->width) {
                $this->cells[$x] = new Cell($char, $attr);
            }
        }
    }

    public function moveStr(int $x, string $str, int $attr): void
    {
        foreach (TerminalText::graphemes($str) as $ch) {
            if ($x >= 0 && $x < $this->width) {
                $this->cells[$x] = new Cell($ch, $attr);
            }
            $x++;
        }
    }

    /**
     * Write a control string, toggling to $highlightAttr between each pair of '~'.
     * The '~' markers are consumed (not displayed). Returns the column after the
     * last visible character written.
     */
    public function moveCStr(int $x, string $str, int $normalAttr, int $highlightAttr): int
    {
        $attr = $normalAttr;

        foreach (TerminalText::graphemes($str) as $ch) {
            if ($ch === '~') {
                $attr = $attr === $normalAttr ? $highlightAttr : $normalAttr;
                continue;
            }
            if ($x >= 0 && $x < $this->width) {
                $this->cells[$x] = new Cell($ch, $attr);
            }
            $x++;
        }

        return $x;
    }

    public function putAttribute(int $x, int $attr): void
    {
        if ($x >= 0 && $x < $this->width) {
            $this->cells[$x] = new Cell($this->cells[$x]->char, $attr);
        }
    }

    /** @return Cell[] the row's cells, indexed by column */
    public function cells(): array
    {
        return $this->cells;
    }
}
