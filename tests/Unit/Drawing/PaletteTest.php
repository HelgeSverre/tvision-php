<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Palette;

test('fromBytes maps 1-based indexes to attribute bytes', function (): void {
    // bytes: index 1 -> 0x71, index 2 -> 0x07, index 3 -> 0x1F
    $p = Palette::fromBytes("\x71\x07\x1F");

    expect($p->size())->toBe(3)
        ->and($p->get(1))->toBe(0x71)
        ->and($p->get(2))->toBe(0x07)
        ->and($p->get(3))->toBe(0x1F);
});

test('out-of-range indexes fall back to 0x07', function (): void {
    $p = Palette::fromBytes("\x71");

    expect($p->get(0))->toBe(0x07)
        ->and($p->get(99))->toBe(0x07);
});

test('can be built directly from an index=>byte map', function (): void {
    $p = new Palette([1 => 0x4E, 2 => 0x20]);

    expect($p->get(1))->toBe(0x4E)
        ->and($p->get(2))->toBe(0x20);
});
