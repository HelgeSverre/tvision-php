<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Geometry\Rect;

test('a new buffer is filled with blanks and reports its rows', function (): void {
    $b = new Buffer(3, 2);

    expect($b->width)->toBe(3)
        ->and($b->height)->toBe(2)
        ->and($b->rows())->toBe(['   ', '   ']);
});

test('put/at write and read a single cell', function (): void {
    $b = new Buffer(3, 2);
    $b->put(1, 0, new Cell('X', 0x1F));

    expect($b->at(1, 0)->char)->toBe('X')
        ->and($b->at(1, 0)->attr)->toBe(0x1F)
        ->and($b->rows())->toBe([' X ', '   ']);
});

test('out-of-bounds put is ignored, out-of-bounds at returns a blank', function (): void {
    $b = new Buffer(2, 2);
    $b->put(5, 5, new Cell('Z'));

    expect($b->rows())->toBe(['  ', '  '])
        ->and($b->at(-1, 0))->toEqual(new Cell())
        ->and($b->at(99, 99))->toEqual(new Cell());
});

test('fill paints a clipped rectangle', function (): void {
    $b = new Buffer(4, 3);
    $b->fill(Rect::of(1, 1, 3, 5), new Cell('#', 0x07)); // bottom clipped to height

    expect($b->rows())->toBe(['    ', ' ## ', ' ## ']);
});
