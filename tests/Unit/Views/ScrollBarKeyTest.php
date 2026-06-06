<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;

test('scrollStep yields signed arrow/page steps', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(10, 2);

    expect($b->scrollStep(ScrollBarPart::UpArrow))->toBe(-2)
        ->and($b->scrollStep(ScrollBarPart::DownArrow))->toBe(2)
        ->and($b->scrollStep(ScrollBarPart::PageUp))->toBe(-10)
        ->and($b->scrollStep(ScrollBarPart::PageDown))->toBe(10);
});

test('vertical bar Down/Up moves by arrowStep and consumes the key', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(10, 2);
    $b->setValue(50);

    $down = Event::keyDown(new KeyDownEvent(Key::Down->value));
    $b->handleEvent($down);
    expect($b->value)->toBe(52)
        ->and($down->isNothing())->toBeTrue();

    $up = Event::keyDown(new KeyDownEvent(Key::Up->value));
    $b->handleEvent($up);
    expect($b->value)->toBe(50);
});

test('vertical bar PageDown moves by pageStep, clamped to max', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(40, 2);
    $b->setValue(80);

    $b->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageDown->value)));
    expect($b->value)->toBe(100);   // 80+40 clamped
});

test('Home and End jump to min/max', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setValue(50);

    $b->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value)));
    expect($b->value)->toBe(0);

    $b->handleEvent(Event::keyDown(new KeyDownEvent(Key::End->value)));
    expect($b->value)->toBe(100);
});

test('a horizontal bar ignores vertical keys', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 10, 1));
    $b->setRange(0, 100);
    $b->setValue(50);

    $ev = Event::keyDown(new KeyDownEvent(Key::Down->value));
    $b->handleEvent($ev);

    expect($b->value)->toBe(50)            // unchanged
        ->and($ev->isNothing())->toBeFalse(); // not consumed
});
