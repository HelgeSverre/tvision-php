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

    /** Scale both components by a factor (saturating). */
    public function scale(int $factor): self
    {
        return new self(IntMath::multiply($this->x, $factor), IntMath::multiply($this->y, $factor));
    }

    /** The point reflected through the origin. */
    public function negate(): self
    {
        return new self(-$this->x, -$this->y);
    }

    /** Component-wise minimum of two points. */
    public static function min(self $a, self $b): self
    {
        return new self(min($a->x, $b->x), min($a->y, $b->y));
    }

    /** Component-wise maximum of two points. */
    public static function max(self $a, self $b): self
    {
        return new self(max($a->x, $b->x), max($a->y, $b->y));
    }

    /** Each component confined to the container rectangle's [a, b) range. */
    public function clampTo(Rect $container): self
    {
        return new self(
            IntMath::clamp($this->x, $container->a->x, IntMath::subtract($container->b->x, 1)),
            IntMath::clamp($this->y, $container->a->y, IntMath::subtract($container->b->y, 1)),
        );
    }

    public function __toString(): string
    {
        return "({$this->x}, {$this->y})";
    }
}
