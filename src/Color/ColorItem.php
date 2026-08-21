<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use InvalidArgumentException;

/** A named logical palette entry that can be edited by a colour dialog. */
final readonly class ColorItem
{
    public function __construct(
        public string $name,
        public int $index,
    ) {
        if ($index < 1 || $index > 255) {
            throw new InvalidArgumentException('A color item index must be between 1 and 255.');
        }
    }
}
