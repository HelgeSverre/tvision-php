<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

use HelgeSverre\TurboVision\Geometry\Rect;
use InvalidArgumentException;
use Countable;

/**
 * A width x height grid of Cells, stored row-major. The screen back/front buffer.
 *
 * @implements \IteratorAggregate<int, list<Cell>>
 */
final class Buffer implements \Countable, \IteratorAggregate
{
    /** Guard against terminal dimensions turning into a process-killing allocation. */
    public const int MAX_CELLS = 1_000_000;

    /** @var array<int, Cell> length width*height, row-major */
    private array $cells;

    public function __construct(
        public readonly int $width,
        public readonly int $height,
        ?Cell $fill = null,
    ) {
        if ($this->width < 0 || $this->height < 0) {
            throw new InvalidArgumentException('Buffer dimensions must be non-negative.');
        }
        if ($this->width !== 0 && $this->height > intdiv(self::MAX_CELLS, $this->width)) {
            throw new InvalidArgumentException('Buffer dimensions exceed the safe cell limit.');
        }

        $count = $this->width * $this->height;
        $this->cells = array_fill(0, $count, $fill ?? new Cell());
    }

    public function at(int $x, int $y): Cell
    {
        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            return new Cell();
        }

        return $this->cells[$y * $this->width + $x];
    }

    public function put(int $x, int $y, Cell $cell): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            return;
        }

        $this->cells[$y * $this->width + $x] = $cell;
    }

    /**
     * Return the row-major cells. PHP arrays are copy-on-write, so callers can scan
     * the snapshot without exposing this buffer to mutation.
     *
     * @return array<int, Cell>
     */
    public function cells(): array
    {
        return $this->cells;
    }

    /** One full row of cells (blank-padded when $y is out of bounds).
     * @return list<Cell>
     */
    public function row(int $y): array
    {
        if ($y < 0 || $y >= $this->height) {
            return array_fill(0, max(0, $this->width), new Cell());
        }

        return array_slice($this->cells, $y * $this->width, $this->width);
    }

    public function count(): int
    {
        return $this->height;
    }

    /** Iterate rows: yields [$y => list<Cell>] top to bottom. */
    public function getIterator(): \Generator
    {
        for ($y = 0; $y < $this->height; $y++) {
            yield $y => $this->row($y);
        }
    }

    /** Cheap copy-on-write snapshot; Cell instances are immutable. */
    public function copy(): self
    {
        return clone $this;
    }

    public function fill(Rect $rect, Cell $cell): void
    {
        $x0 = max(0, $rect->a->x);
        $y0 = max(0, $rect->a->y);
        $x1 = min($this->width, $rect->b->x);
        $y1 = min($this->height, $rect->b->y);

        for ($y = $y0; $y < $y1; $y++) {
            for ($x = $x0; $x < $x1; $x++) {
                $this->cells[$y * $this->width + $x] = $cell;
            }
        }
    }

    /** @return list<string> one string of characters per row (for snapshots) */
    public function rows(): array
    {
        $out = [];

        for ($y = 0; $y < $this->height; $y++) {
            $row = '';
            for ($x = 0; $x < $this->width; $x++) {
                $row .= $this->cells[$y * $this->width + $x]->char;
            }
            $out[] = $row;
        }

        return $out;
    }
}
