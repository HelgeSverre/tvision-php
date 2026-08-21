<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Geometry;

use HelgeSverre\TurboVision\Support\IntMath;

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
        return IntMath::subtract($this->b->x, $this->a->x);
    }

    public function height(): int
    {
        return IntMath::subtract($this->b->y, $this->a->y);
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
            new Point(IntMath::add($this->a->x, $dx), IntMath::add($this->a->y, $dy)),
            new Point(IntMath::add($this->b->x, $dx), IntMath::add($this->b->y, $dy)),
        );
    }

    public function grow(int $dx, int $dy): self
    {
        return new self(
            new Point(IntMath::subtract($this->a->x, $dx), IntMath::subtract($this->a->y, $dy)),
            new Point(IntMath::add($this->b->x, $dx), IntMath::add($this->b->y, $dy)),
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

    /** Whether the two rectangles overlap in a non-empty region. */
    public function intersects(Rect $other): bool
    {
        return ! $this->intersect($other)->isEmpty();
    }

    /** The smallest rectangle covering both inputs. */
    public function union(Rect $other): self
    {
        return new self(
            new Point(min($this->a->x, $other->a->x), min($this->a->y, $other->a->y)),
            new Point(max($this->b->x, $other->b->x), max($this->b->y, $other->b->y)),
        );
    }

    /** Whether every part of $other lies inside this rectangle. */
    public function containsRect(Rect $other): bool
    {
        return $this->contains($other->a) && $this->contains(new Point(
            IntMath::subtract($other->b->x, 1),
            IntMath::subtract($other->b->y, 1),
        )) || ($other->isEmpty() && $this->contains($other->a));
    }

    /**
     * Shrink (positive deltas) or grow (negative deltas) each edge inward,
     * mirroring grow()'s outward convention.
     */
    public function inset(int $dx, int $dy): self
    {
        return $this->grow(-$dx, -$dy);
    }

    /** A rectangle of this size centered horizontally and vertically inside $container. */
    public function centeredIn(Rect $container): self
    {
        $width = max(0, $this->width());
        $height = max(0, $this->height());
        $left = $container->a->x + intdiv(max(0, $container->width() - $width), 2);
        $top = $container->a->y + intdiv(max(0, $container->height() - $height), 2);

        return Rect::of($left, $top, IntMath::add($left, $width), IntMath::add($top, $height));
    }

    /** This rectangle's size moved as close to the center of $container without leaving it. */
    public function clampInto(Rect $container): self
    {
        if ($container->isEmpty()) {
            return new self($container->a, $container->a);
        }
        $width = min(max(0, $this->width()), $container->width());
        $height = min(max(0, $this->height()), $container->height());
        $left = IntMath::clamp($this->a->x, $container->a->x, IntMath::subtract($container->b->x, $width));
        $top = IntMath::clamp($this->a->y, $container->a->y, IntMath::subtract($container->b->y, $height));

        return Rect::of($left, $top, IntMath::add($left, $width), IntMath::add($top, $height));
    }

    /** A rectangle anchored at a corner with the given extent. */
    public static function fromSize(int $x, int $y, int $width, int $height): self
    {
        return Rect::of($x, $y, IntMath::add($x, max(0, $width)), IntMath::add($y, max(0, $height)));
    }

    /** The (width, height) extent as a Point. */
    public function size(): Point
    {
        return new Point($this->width(), $this->height());
    }

    public function __toString(): string
    {
        return "[{$this->a} - {$this->b}]";
    }
}
