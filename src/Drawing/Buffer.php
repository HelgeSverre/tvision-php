<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

use HelgeSverre\TurboVision\Geometry\Rect;

/** A width x height grid of Cells, stored row-major. The screen back/front buffer. */
final class Buffer
{
    /** @var Cell[] length width*height, row-major */
    private array $cells;

    public function __construct(
        public readonly int $width,
        public readonly int $height,
        ?Cell $fill = null,
    ) {
        $count = max(0, $this->width * $this->height);
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
