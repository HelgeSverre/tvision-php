<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Geometry;

use HelgeSverre\TurboVision\Support\IntMath;

/** An immutable (x, y) screen coordinate. Faithful to Turbo Vision's TPoint. */
final readonly class Point
{
    public function __construct(
        public int $x = 0,
        public int $y = 0,
    ) {}

    public function add(Point $other): self
    {
        return new self(IntMath::add($this->x, $other->x), IntMath::add($this->y, $other->y));
    }

    public function subtract(Point $other): self
    {
        return new self(IntMath::subtract($this->x, $other->x), IntMath::subtract($this->y, $other->y));
    }

    public function equals(Point $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    public function __toString(): string
    {
        return "({$this->x}, {$this->y})";
    }
}
