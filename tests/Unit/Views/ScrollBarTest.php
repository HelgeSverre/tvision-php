<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\State;

test('a 1-wide bar is vertical, a wide bar is horizontal', function (): void {
    $v = new ScrollBar(Rect::of(0, 0, 1, 10));
    $h = new ScrollBar(Rect::of(0, 0, 20, 1));

    expect($v->isVertical())->toBeTrue()
        ->and($h->isVertical())->toBeFalse();
});

test('default value model is zeroed with step 1', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));

    expect($b->value)->toBe(0)
        ->and($b->minVal)->toBe(0)
        ->and($b->maxVal)->toBe(0)
        ->and($b->pageStep)->toBe(1)
        ->and($b->arrowStep)->toBe(1);
});

test('setParams clamps value into [min, max] and normalises max>=min', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setParams(value: 50, min: 0, max: 20, pageStep: 5, arrowStep: 1);
    expect($b->value)->toBe(20);   // clamped to max

    $b->setParams(value: -7, min: 0, max: 20, pageStep: 5, arrowStep: 1);
    expect($b->value)->toBe(0);    // clamped to min
});

test('setValue/setRange/setStep are setParams shortcuts', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(10, 2);
    $b->setValue(40);

    expect($b->value)->toBe(40)
        ->and($b->minVal)->toBe(0)
        ->and($b->maxVal)->toBe(100)
        ->and($b->pageStep)->toBe(10)
        ->and($b->arrowStep)->toBe(2);
});

test('getPos: zero range parks the thumb at 1', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    expect($b->getPos())->toBe(1);
});

test('getPos: value scales across the track (faithful integer arithmetic)', function (): void {
    // Vertical bar of length 10: getSize()=10, track span getSize()-3 = 7.
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);

    $b->setValue(0);
    expect($b->getPos())->toBe(1);          // (0*7 + 50)/100 + 1 = 1

    $b->setValue(50);
    expect($b->getPos())->toBe(5);          // (50*7 + 50)/100 + 1 = 4.0->4 +1 = 4? verify below

    $b->setValue(100);
    expect($b->getPos())->toBe(8);          // (100*7 + 50)/100 + 1 = 7 + 1 = 8
});

test('full-range values and hostile step sizes cannot overflow scroll arithmetic', function (): void {
    $bar = new ScrollBar(Rect::of(0, 0, 1, 10));
    $bar->setParams(0, PHP_INT_MIN, PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MIN);

    expect($bar->getPos())->toBe(5)
        ->and($bar->pageStep)->toBe(0)
        ->and($bar->arrowStep)->toBe(0);

    $bar->pageStep = PHP_INT_MAX;
    $bar->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageDown->value)));
    $bar->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageDown->value)));

    expect($bar->value)->toBe(PHP_INT_MAX);
});

test('a maximum drawable track retains an in-range thumb position', function (): void {
    $bar = new ScrollBar(Rect::of(0, 0, 1, Buffer::MAX_CELLS));
    $bar->setRange(0, 1);
    $bar->setValue(1);

    expect($bar->getPos())->toBe(Buffer::MAX_CELLS - 2);
});

test('ScrollBar growMode follows orientation', function (): void {
    $v = new ScrollBar(Rect::of(0, 0, 1, 10));
    $h = new ScrollBar(Rect::of(0, 0, 20, 1));

    expect($v->growMode)->toBe(State::GrowLoX | State::GrowHiX | State::GrowHiY)
        ->and($h->growMode)->toBe(State::GrowLoY | State::GrowHiX | State::GrowHiY);
});
