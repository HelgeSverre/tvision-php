<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;

test('special keys carry their faithful kbXxx codes', function (): void {
    expect(Key::Esc->value)->toBe(0x011B)
        ->and(Key::Enter->value)->toBe(0x1C0D)
        ->and(Key::Tab->value)->toBe(0x0F09)
        ->and(Key::Up->value)->toBe(0x4800)
        ->and(Key::F1->value)->toBe(0x3B00)
        ->and(Key::AltX->value)->toBe(0x2D00)
        ->and(Key::AltF->value)->toBe(0x2100);
});

test('legacy control, modifier, and window-selection key identities match tkeys.h', function (): void {
    expect(Key::None->value)->toBe(0x0000)
        ->and(Key::CtrlA->value)->toBe(0x0001)
        ->and(Key::CtrlZ->value)->toBe(0x001A)
        ->and(Key::AltSpace->value)->toBe(0x0200)
        ->and(Key::CtrlInsert->value)->toBe(0x0400)
        ->and(Key::ShiftDelete->value)->toBe(0x0700)
        ->and(Key::CtrlEnter->value)->toBe(0x1C0A)
        ->and(Key::ShiftF10->value)->toBe(0x5D00)
        ->and(Key::CtrlF10->value)->toBe(0x6700)
        ->and(Key::AltF10->value)->toBe(0x7100)
        ->and(Key::CtrlLeft->value)->toBe(0x7300)
        ->and(Key::CtrlPageDown->value)->toBe(0x7600)
        ->and(Key::CtrlPageUp->value)->toBe(0x8400)
        ->and(Key::Alt1->value)->toBe(0x7800)
        ->and(Key::Alt0->value)->toBe(0x8100)
        ->and(Key::AltEqual->value)->toBe(0x8300);
});

test('all key codes are unique (enum integrity)', function (): void {
    $values = array_map(fn (Key $k): int => $k->value, Key::cases());

    expect($values)->toBe(array_unique($values));
});

test('modern modifier metadata remains independent from legacy key identities', function (): void {
    expect(KeyModifier::None)->toBe(0)
        ->and(KeyModifier::Shift)->toBe(0b00000001)
        ->and(KeyModifier::Alt)->toBe(0b00000010)
        ->and(KeyModifier::Ctrl)->toBe(0b00000100)
        ->and(KeyModifier::Known)->toBe(0b11111111);
});
