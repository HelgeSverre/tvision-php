<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Support;

/**
 * The minimum and maximum extent a view may occupy. Replaces the historical
 * positional [minWidth, minHeight, maxWidth, maxHeight] array so the shape has
 * one authoritative definition instead of being destructured by position at
 * every call site.
 */
final readonly class SizeLimits
{
    public function __construct(
        public int $minWidth = 0,
        public int $minHeight = 0,
        public int $maxWidth = PHP_INT_MAX,
        public int $maxHeight = PHP_INT_MAX,
    ) {}

    /** Whether an extent of the given size satisfies these limits. */
    public function allows(int $width, int $height): bool
    {
        return $width >= $this->minWidth
            && $height >= $this->minHeight
            && $width <= $this->maxWidth
            && $height <= $this->maxHeight;
    }

    /** The given width confined to [minWidth, maxWidth]. */
    public function clampWidth(int $width): int
    {
        return IntMath::clamp($width, $this->minWidth, $this->maxWidth);
    }

    /** The given height confined to [minHeight, maxHeight]. */
    public function clampHeight(int $height): int
    {
        return IntMath::clamp($height, $this->minHeight, $this->maxHeight);
    }
}
