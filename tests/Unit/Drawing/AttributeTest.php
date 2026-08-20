<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Color;

test('default attribute is light gray on black (byte 0x07)', function (): void {
    $a = new Attribute();

    expect($a->fg)->toBe(Color::LightGray)
        ->and($a->bg)->toBe(Color::Black)
        ->and($a->toByte())->toBe(0x07)
        ->and($a->toSgr())->toBe("\e[0;37m");
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
    // White fg (CGA 15 -> bright 97), Blue bg (CGA 1 -> ANSI 4 -> 44)
    expect((new Attribute(Color::White, Color::Blue))->toSgr())->toBe("\e[0;97;44m");
});

test('SGR remaps CGA colour order to ANSI (blue<->red, cyan<->brown swap)', function (): void {
    // CGA orders R-G-B opposite to ANSI; without the remap TV blue would render red.
    expect((new Attribute(Color::Blue, Color::Black))->toSgr())->toBe("\e[0;34m")    // CGA 1 -> 34
        ->and((new Attribute(Color::Red, Color::Black))->toSgr())->toBe("\e[0;31m")  // CGA 4 -> 31
        ->and((new Attribute(Color::Cyan, Color::Black))->toSgr())->toBe("\e[0;36m") // CGA 3 -> 36
        ->and((new Attribute(Color::Brown, Color::Black))->toSgr())->toBe("\e[0;33m")// CGA 6 -> 33
        ->and((new Attribute(Color::Green, Color::Black))->toSgr())->toBe("\e[0;32m")// CGA 2 -> 32 (unchanged)
        ->and((new Attribute(Color::LightGray, Color::Black))->toSgr())->toBe("\e[0;37m"); // CGA 7 -> 37
});

test('black background uses the terminal default while non-black backgrounds remain explicit', function (): void {
    expect((new Attribute(Color::LightGray, Color::Black))->toSgr())->not->toContain(';40')
        ->and((new Attribute(Color::LightGray, Color::Black))->toSgr(false))->toBe("\e[0;37;40m")
        ->and((new Attribute(Color::White, Color::Blue))->toSgr())->toBe("\e[0;97;44m");
});
