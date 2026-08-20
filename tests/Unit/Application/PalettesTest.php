<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Palettes;

test('modern dark is the default while the original palette remains available', function (): void {
    $modernAttributes = array_map(ord(...), str_split(Palettes::MODERN_DARK));

    expect(Palettes::COLOR)->toBe(Palettes::MODERN_DARK)
        ->and(Palettes::COLOR)->not->toBe(Palettes::CLASSIC_COLOR)
        ->and(ord(Palettes::COLOR[0]))->toBe(0x08)
        ->and(ord(Palettes::CLASSIC_COLOR[0]))->toBe(0x71)
        ->and(strlen(Palettes::MODERN_DARK))->toBe(strlen(Palettes::CLASSIC_COLOR))
        ->and(array_filter($modernAttributes, static fn (int $attr): bool => (($attr >> 4) & 0x07) !== 0))->toBe([]);
});
