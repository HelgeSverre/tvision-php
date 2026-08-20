<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

function deskFor(int $cols, int $rows): Desktop
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $desk = new class(Rect::of(0, 1, $cols, $rows - 1), $screen) extends Desktop {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    return $desk;
}

test('inserting a window makes it the current view and selects it', function (): void {
    $desk = deskFor(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $desk->insertWindow($w);

    expect($desk->current())->toBe($w)
        ->and($w->getState(State::Selected))->toBeTrue();
});

test('cmNext cycles the current window to the next', function (): void {
    $desk = deskFor(80, 25);
    $w1 = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $w2 = new Window(Rect::of(2, 2, 28, 9), 'Two', 2);
    $desk->insertWindow($w1);
    $desk->insertWindow($w2);   // w2 current now

    $desk->handleEvent(Event::command(Cmd::Next));

    expect($desk->current())->toBe($w1);
});

test('cmPrev cycles in the opposite direction', function (): void {
    $desk = deskFor(80, 25);
    $w1 = new Window(Rect::of(0, 0, 20, 6), 'One', 1);
    $w2 = new Window(Rect::of(22, 0, 42, 6), 'Two', 2);
    $w3 = new Window(Rect::of(44, 0, 64, 6), 'Three', 3);
    $desk->insertWindow($w1);
    $desk->insertWindow($w2);
    $desk->insertWindow($w3);

    $desk->handleEvent(Event::command(Cmd::Prev));

    expect($desk->current())->toBe($w2);
});

test('clicking an inactive window selects and raises it before handling the click', function (): void {
    $desk = deskFor(80, 25);
    $back = new Window(Rect::of(0, 0, 20, 6), 'Back', 1);
    $front = new Window(Rect::of(30, 0, 50, 6), 'Front', 2);
    $desk->insertWindow($back);
    $desk->insertWindow($front);

    $event = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(2, 1), 1));
    $desk->handleEvent($event);
    $subviews = $desk->subviews();

    expect($desk->current())->toBe($back)
        ->and(end($subviews))->toBe($back)
        ->and($event->isNothing())->toBeTrue();
});

test('removing the current window restores focus to another window', function (): void {
    $desk = deskFor(80, 25);
    $w1 = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $w2 = new Window(Rect::of(2, 2, 28, 9), 'Two', 2);
    $desk->insertWindow($w1);
    $desk->insertWindow($w2);

    $desk->remove($w2);

    expect($desk->current())->toBe($w1);
});
