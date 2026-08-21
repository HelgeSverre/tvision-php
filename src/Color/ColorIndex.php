<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

/** Persistent selection state for a ColorDialog's group and item lists. */
final readonly class ColorIndex
{
    /** @param list<int> $itemIndexes */
    public function __construct(
        public int $groupIndex = 0,
        public array $itemIndexes = [],
    ) {}
}
