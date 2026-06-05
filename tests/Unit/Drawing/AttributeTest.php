<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Color;

test('default attribute is light gray on black (byte 0x07)', function (): void {
    $a = new Attribute();

    expect($a->fg)->toBe(Color::LightGray)
        ->and($a->bg)->toBe(Color::Black)
        ->and($a->toByte())->toBe(0x07)
        ->and($a->toSgr())->toBe("\e[0;37;40m");
});

test('byte packing matches fg | bg<<4 | blink<<7', function (): void {
    $a = new Attribute(Color::White, Color::Blue);          // 15 | 1<<4 = 0x1F
    $b = new Attribute(Color::Yellow, Color::Red, blink: true); // 14 | 4<<4 | 0x80 = 0xCE

    expect($a->toByte())->toBe(0x1F)
        ->and($b->toByte())->toBe(0xCE);
});

test('fromByte reverses toByte for the low-8 background range', function (): void {
    $a = new Attribute(Color::LightCyan, Color::Green, blink: true);

    expect(Attribute::fromByte($a->toByte()))->toEqual($a);
});

test('SGR uses bright codes for the high 8 colors', function (): void {
    // White fg (15 -> 97), Blue bg (1 -> 41)
    expect((new Attribute(Color::White, Color::Blue))->toSgr())->toBe("\e[0;97;41m");
});
