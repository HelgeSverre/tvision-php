<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Key;

test('F11 and F12 carry their faithful CGA scancodes', function (): void {
    expect(Key::F11->value)->toBe(0x8500)
        ->and(Key::F12->value)->toBe(0x8600);
});

test('all key codes remain unique after adding F11/F12', function (): void {
    $values = array_map(fn (Key $k): int => $k->value, Key::cases());

    expect($values)->toBe(array_unique($values));
});
