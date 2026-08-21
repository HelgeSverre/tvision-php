<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\PaletteMode;
use HelgeSverre\TurboVision\Application\Palettes;

test('each runtime palette mode resolves to its expected root table', function (): void {
    expect(Palettes::for(PaletteMode::Color))->toBe(Palettes::COLOR)
        ->and(Palettes::for(PaletteMode::ClassicColor))->toBe(Palettes::CLASSIC_COLOR)
        ->and(Palettes::for(PaletteMode::BlackWhite))->toBe(Palettes::BLACK_WHITE)
        ->and(Palettes::for(PaletteMode::Monochrome))->toBe(Palettes::MONOCHROME)
        ->and(strlen(Palettes::MONOCHROME))->toBe(strlen(Palettes::CLASSIC_COLOR));
});

test('palette modes have stable configuration values', function (): void {
    expect(PaletteMode::Color->value)->toBe('color')
        ->and(PaletteMode::BlackWhite->value)->toBe('black-white')
        ->and(PaletteMode::Monochrome->value)->toBe('monochrome')
        ->and(PaletteMode::from('classic-color'))->toBe(PaletteMode::ClassicColor);
});
