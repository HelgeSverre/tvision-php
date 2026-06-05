<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Geometry;

/**
 * An immutable rectangle. `a` is the top-left (inclusive) corner, `b` is the
 * bottom-right (exclusive) corner. Faithful to Turbo Vision's TRect.
 */
final readonly class Rect
{
    public function __construct(
        public Point $a,
        public Point $b,
    ) {}

    public static function of(int $ax, int $ay, int $bx, int $by): self
    {
        return new self(new Point($ax, $ay), new Point($bx, $by));
    }

    public function width(): int
    {
        return $this->b->x - $this->a->x;
    }

    public function height(): int
    {
        return $this->b->y - $this->a->y;
    }

    public function isEmpty(): bool
    {
        return $this->width() <= 0 || $this->height() <= 0;
    }

    public function contains(Point $p): bool
    {
        return $p->x >= $this->a->x && $p->x < $this->b->x
            && $p->y >= $this->a->y && $p->y < $this->b->y;
    }

    public function move(int $dx, int $dy): self
    {
        return new self(
            new Point($this->a->x + $dx, $this->a->y + $dy),
            new Point($this->b->x + $dx, $this->b->y + $dy),
        );
    }

    public function grow(int $dx, int $dy): self
    {
        return new self(
            new Point($this->a->x - $dx, $this->a->y - $dy),
            new Point($this->b->x + $dx, $this->b->y + $dy),
        );
    }

    public function intersect(Rect $other): self
    {
        return new self(
            new Point(max($this->a->x, $other->a->x), max($this->a->y, $other->a->y)),
            new Point(min($this->b->x, $other->b->x), min($this->b->y, $other->b->y)),
        );
    }

    public function equals(Rect $other): bool
    {
        return $this->a->equals($other->a) && $this->b->equals($other->b);
    }
}
