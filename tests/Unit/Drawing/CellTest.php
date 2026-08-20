<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\Color;

test('default cell is a blank with attribute 0x07', function (): void {
    $c = new Cell();

    expect($c->char)->toBe(' ')
        ->and($c->attr)->toBe(0x07);
});

test('a cell can be built from an Attribute object', function (): void {
    $c = Cell::of('A', new Attribute(Color::White, Color::Blue));

    expect($c->char)->toBe('A')
        ->and($c->attr)->toBe(0x1F);
});

test('equals() is value-based', function (): void {
    expect((new Cell('X', 0x1F))->equals(new Cell('X', 0x1F)))->toBeTrue()
        ->and((new Cell('X', 0x1F))->equals(new Cell('X', 0x07)))->toBeFalse()
        ->and((new Cell('X', 0x1F))->equals(new Cell('Y', 0x1F)))->toBeFalse();
});

test('cells enforce the single-column rendering invariant', function (): void {
    expect((new Cell('🚀'))->char)->toBe('?')
        ->and((new Cell("e\u{0301}"))->char)->toBe("e\u{0301}");
});
