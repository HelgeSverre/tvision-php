<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Key;

test('special keys carry their faithful kbXxx codes', function (): void {
    expect(Key::Esc->value)->toBe(0x011B)
        ->and(Key::Enter->value)->toBe(0x1C0D)
        ->and(Key::Tab->value)->toBe(0x0F09)
        ->and(Key::Up->value)->toBe(0x4800)
        ->and(Key::F1->value)->toBe(0x3B00)
        ->and(Key::AltX->value)->toBe(0x2D00)
        ->and(Key::AltF->value)->toBe(0x2100);
});

test('all key codes are unique (enum integrity)', function (): void {
    $values = array_map(fn (Key $k): int => $k->value, Key::cases());

    expect($values)->toBe(array_unique($values));
});
