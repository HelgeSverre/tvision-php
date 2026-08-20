<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('makeLocal subtracts the absolute origin', function (): void {
    $outer = new Group(Rect::of(0, 0, 40, 20));
    $inner = new View(Rect::of(5, 3, 15, 10));
    $outer->insert($inner);

    expect($inner->makeLocal(new Point(7, 5)))->toEqual(new Point(2, 2));
});

test('mouseInView is true only for points inside the view bounds', function (): void {
    $outer = new Group(Rect::of(0, 0, 40, 20));
    $v = new View(Rect::of(5, 3, 15, 10));
    $outer->insert($v);

    expect($v->mouseInView(new Point(7, 5)))->toBeTrue()
        ->and($v->mouseInView(new Point(0, 0)))->toBeFalse();
});

test('getClipRect returns the extent in M2', function (): void {
    $v = new View(Rect::of(5, 3, 15, 10));

    expect($v->getClipRect())->toEqual(Rect::of(0, 0, 10, 7));
});

test('calcBounds with gfGrowHiX|gfGrowHiY follows the delta on the high corner', function (): void {
    $v = new View(Rect::of(2, 2, 12, 8));
    $v->growMode = State::GrowHiX | State::GrowHiY;

    // owner grew by (4, 2): only the bottom-right corner moves.
    expect($v->calcBounds(new Point(4, 2)))->toEqual(Rect::of(2, 2, 16, 10));
});

test('calcBounds with gfGrowAll moves both corners', function (): void {
    $v = new View(Rect::of(2, 2, 12, 8));
    $v->growMode = State::GrowAll;

    expect($v->calcBounds(new Point(4, 2)))->toEqual(Rect::of(6, 4, 16, 10));
});

test('calcBounds with no grow mode keeps bounds unchanged', function (): void {
    $v = new View(Rect::of(2, 2, 12, 8));

    expect($v->calcBounds(new Point(4, 2)))->toEqual(Rect::of(2, 2, 12, 8));
});

test('changeBounds replaces bounds', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));
    $v->changeBounds(Rect::of(1, 1, 9, 9));

    expect($v->getBounds())->toEqual(Rect::of(1, 1, 9, 9));
});

test('dragView shrinks an oversized view so it remains inside its limits', function (): void {
    $view = new View(Rect::of(0, 0, 20, 20));

    $view->dragView(
        Rect::of(-5, -5, 25, 25),
        Rect::of(0, 0, 10, 8),
        new Point(1, 1),
        new Point(100, 100),
    );

    expect($view->getBounds())->toEqual(Rect::of(0, 0, 10, 8));
});
