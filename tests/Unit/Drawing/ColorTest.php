<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Color;

test('colors carry their classic CGA index', function (): void {
    expect(Color::Black->value)->toBe(0)
        ->and(Color::LightGray->value)->toBe(7)
        ->and(Color::White->value)->toBe(15)
        ->and(Color::from(1))->toBe(Color::Blue);
});

test('isBright() distinguishes the high 8 colors', function (): void {
    expect(Color::Blue->isBright())->toBeFalse()
        ->and(Color::LightBlue->isBright())->toBeTrue();
});
