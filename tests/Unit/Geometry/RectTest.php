<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;

test('of() builds from four ints; width/height computed from corners', function (): void {
    $r = Rect::of(2, 3, 12, 8);

    expect($r->a)->toEqual(new Point(2, 3))
        ->and($r->b)->toEqual(new Point(12, 8))
        ->and($r->width())->toBe(10)
        ->and($r->height())->toBe(5)
        ->and($r->isEmpty())->toBeFalse();
});

test('a degenerate rectangle is empty', function (): void {
    expect(Rect::of(5, 5, 5, 9)->isEmpty())->toBeTrue()
        ->and(Rect::of(5, 5, 9, 5)->isEmpty())->toBeTrue();
});

test('contains() uses half-open bounds', function (): void {
    $r = Rect::of(0, 0, 4, 3);

    expect($r->contains(new Point(0, 0)))->toBeTrue()
        ->and($r->contains(new Point(3, 2)))->toBeTrue()
        ->and($r->contains(new Point(4, 2)))->toBeFalse()  // right edge excluded
        ->and($r->contains(new Point(2, 3)))->toBeFalse(); // bottom edge excluded
});

test('move, grow and intersect', function (): void {
    $r = Rect::of(2, 2, 6, 6);

    expect($r->move(3, 1))->toEqual(Rect::of(5, 3, 9, 7))
        ->and($r->grow(1, 2))->toEqual(Rect::of(1, 0, 7, 8))
        ->and($r->intersect(Rect::of(4, 4, 10, 10)))->toEqual(Rect::of(4, 4, 6, 6))
        ->and($r->intersect(Rect::of(20, 20, 30, 30))->isEmpty())->toBeTrue();
});
