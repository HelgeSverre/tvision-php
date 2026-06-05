<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Point;

test('point exposes x and y with a (0,0) default', function (): void {
    expect(new Point())->toEqual(new Point(0, 0))
        ->and((new Point(3, 4))->x)->toBe(3)
        ->and((new Point(3, 4))->y)->toBe(4);
});

test('point arithmetic and equality are value-based', function (): void {
    $a = new Point(2, 3);
    $b = new Point(5, 7);

    expect($a->add($b))->toEqual(new Point(7, 10))
        ->and($b->subtract($a))->toEqual(new Point(3, 4))
        ->and($a->equals(new Point(2, 3)))->toBeTrue()
        ->and($a->equals($b))->toBeFalse();
});
