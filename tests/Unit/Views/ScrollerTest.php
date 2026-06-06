<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;

/** A Scroller that paints a row of "delta.y + rowIndex" digits so we can read the offset. */
final class ProbeScroller extends Scroller
{
    public function draw(): void
    {
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $b = new DrawBuffer($this->bounds->width());
            $line = (string) (($this->delta->y + $y) % 10);
            $b->moveChar(0, $line, 0x07, $this->bounds->width());
            $this->writeLine(0, $y, $this->bounds->width(), 1, $b);
        }
    }
}

/** A Group rooted at a real Screen so child writes hit the back buffer. */
final class ScRootGroup extends Group
{
    public function __construct(private readonly Screen $rootScreen)
    {
        parent::__construct(Rect::of(0, 0, $rootScreen->cols(), $rootScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
}

test('a fresh scroller has zero delta/limit and is selectable', function (): void {
    $s = new ProbeScroller(Rect::of(0, 0, 10, 5), null, null);

    expect($s->delta)->toEqual(new Point(0, 0))
        ->and($s->limit)->toEqual(new Point(0, 0))
        ->and($s->getState(State::Selectable))->toBeTrue();
});

test('setLimit stores the limit and parameterises the vertical bar', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 10, 5), null, $vBar);
    $s->setLimit(40, 100);

    expect($s->limit)->toEqual(new Point(40, 100))
        ->and($vBar->minVal)->toBe(0)
        ->and($vBar->maxVal)->toBe(95);   // y - size.y = 100 - 5
});

test('a cmScrollBarChanged broadcast from the vertical bar updates delta and redraws', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 5));
    $screen->init();
    $g = new ScRootGroup($screen);
    $vBar = new ScrollBar(Rect::of(9, 0, 10, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);
    $g->insert($vBar);
    $g->insert($s);

    $vBar->setRange(0, 100);
    $vBar->setValue(3);  // moves bar -> broadcasts cmScrollBarChanged(this)

    // The scroller should have picked up delta.y = 3.
    expect($s->delta->y)->toBe(3);

    $s->draw();
    // Row 0 now shows (3+0)%10 = '3'.
    expect($screen->back()->rows()[0][0])->toBe('3');
});

test('a broadcast from an UNRELATED bar is ignored', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $other = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);

    $s->handleEvent(Event::broadcast(Cmd::ScrollBarChanged, $other));

    expect($s->delta->y)->toBe(0);
});

test('scrollTo drives the bars and clamps via setValue', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);
    $s->setLimit(0, 100);  // maxVal becomes 95
    $s->scrollTo(0, 999);

    expect($vBar->value)->toBe(95)
        ->and($s->delta->y)->toBe(95);
});

test('changeBounds re-clamps the bars (limit reapplied)', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);
    $s->setLimit(0, 100);          // size.y=5 -> maxVal 95
    $s->changeBounds(Rect::of(0, 0, 9, 9)); // size.y=9 -> maxVal 91

    expect($vBar->maxVal)->toBe(91);
});
