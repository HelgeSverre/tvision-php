<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;

it('rect intersects, unions, contains, insets, centers, and clamps', function (): void {
    $a = Rect::of(0, 0, 20, 10);
    $b = Rect::of(15, 8, 30, 20);
    $c = Rect::of(50, 50, 60, 60);

    expect($a->intersects($b))->toBeTrue()
        ->and($a->intersects($c))->toBeFalse()
        ->and($a->union($b)->equals(Rect::of(0, 0, 30, 20)))->toBeTrue()
        ->and($a->containsRect(Rect::of(1, 1, 19, 9)))->toBeTrue()
        ->and($a->containsRect($b))->toBeFalse()
        ->and($a->inset(2, 1)->equals(Rect::of(2, 1, 18, 9)))->toBeTrue()
        ->and(Rect::of(0, 0, 10, 4)->centeredIn($a)->equals(Rect::of(5, 3, 15, 7)))->toBeTrue()
        ->and(Rect::of(-5, -5, 5, 5)->clampInto($a)->equals(Rect::of(0, 0, 10, 10)))->toBeTrue()
        ->and(Rect::fromSize(3, 4, 6, 5)->equals(Rect::of(3, 4, 9, 9)))->toBeTrue()
        ->and($a->size()->equals(new Point(20, 10)))->toBeTrue()
        ->and((string) $a)->toBe('[(0, 0) - (20, 10)]');
});

it('point scales, negates, mins, maxes, and clamps to a rect', function (): void {
    $p = new Point(5, -7);

    expect($p->scale(2)->equals(new Point(10, -14)))->toBeTrue()
        ->and($p->negate()->equals(new Point(-5, 7)))->toBeTrue()
        ->and(Point::min(new Point(1, 9), new Point(4, 2))->equals(new Point(1, 2)))->toBeTrue()
        ->and(Point::max(new Point(1, 9), new Point(4, 2))->equals(new Point(4, 9)))->toBeTrue()
        ->and(new Point(99, -3)->clampTo(Rect::of(0, 0, 10, 10))->equals(new Point(9, 0)))->toBeTrue();
});

it('IntMath clamp confines values without float promotion', function (): void {
    expect(IntMath::clamp(5, 1, 3))->toBe(3)
        ->and(IntMath::clamp(-5, 1, 3))->toBe(1)
        ->and(IntMath::clamp(2, 1, 3))->toBe(2)
        ->and(IntMath::clamp(2, 3, 1))->toBe(3);
});

it('cells offer blank, sentinel, and immutable with() construction', function (): void {
    $cell = HelgeSverre\TurboVision\Drawing\Cell::blank();

    expect($cell->char)->toBe(' ')
        ->and($cell->attr)->toBe(0x07)
        ->and(HelgeSverre\TurboVision\Drawing\Cell::sentinel()->attr)->toBe(-1)
        ->and($cell->with('*', 0x1F)->attr)->toBe(0x1F)
        ->and($cell->attr)->toBe(0x07);
});
