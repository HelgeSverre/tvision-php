<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('a new view stores its bounds and is visible+selectable by default off', function (): void {
    $v = new View(Rect::of(2, 3, 12, 8));

    expect($v->getBounds())->toEqual(Rect::of(2, 3, 12, 8))
        ->and($v->getExtent())->toEqual(Rect::of(0, 0, 10, 5))
        ->and($v->getState(State::Visible))->toBeTrue()
        ->and($v->getState(State::Focused))->toBeFalse();
});

test('setState toggles a single flag bit', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));

    $v->setState(State::Focused, true);
    expect($v->getState(State::Focused))->toBeTrue();

    $v->setState(State::Focused, false);
    expect($v->getState(State::Focused))->toBeFalse()
        ->and($v->getState(State::Visible))->toBeTrue(); // unaffected
});

test('setBounds replaces bounds and recomputes the extent', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));
    $v->setBounds(Rect::of(5, 5, 15, 9));

    expect($v->getBounds())->toEqual(Rect::of(5, 5, 15, 9))
        ->and($v->getExtent())->toEqual(Rect::of(0, 0, 10, 4));
});

test('clearEvent consumes an event (sets what=Nothing)', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));
    $e = Event::command(Cmd::Quit);

    $v->clearEvent($e);
    expect($e->isNothing())->toBeTrue();
});

test('default draw() fills the extent with blanks when owned by a Screen-backed root', function (): void {
    // A tiny root that exposes a real Screen so writes have somewhere to land.
    $screen = new Screen(new HeadlessDriver(6, 3));
    $screen->init();
    $root = new RootStub($screen);

    $v = new View(Rect::of(1, 1, 5, 2)); // 4 wide, 1 tall, at (1,1)
    $root->insert($v);

    $v->draw();

    // Row 1, columns 1..4 are blanked (already blank, so still spaces) — assert shape.
    expect($screen->back()->rows())->toBe(['      ', '      ', '      ']);
});

test('writeStr composites a string into the root back buffer at the absolute origin', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 3));
    $screen->init();
    $root = new RootStub($screen);

    $v = new View(Rect::of(2, 1, 7, 2)); // origin (2,1), 5 wide
    $root->insert($v);

    $v->writeStr(0, 0, 'Hi', 0x07);

    expect($screen->back()->rows())->toBe(['        ', '  Hi    ', '        ']);
});

test('writeBuf blits a DrawBuffer row, clipped to the view extent', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 2));
    $screen->init();
    $root = new RootStub($screen);

    $v = new View(Rect::of(1, 0, 4, 1)); // origin (1,0), only 3 columns wide
    $root->insert($v);

    $b = new DrawBuffer(6);
    $b->moveStr(0, 'ABCDEF', 0x07);   // 6 chars, but view is 3 wide
    $v->writeBuf(0, 0, 3, 1, $b);

    // Only A,B,C land, at columns 1,2,3 of row 0.
    expect($screen->back()->rows())->toBe([' ABC    ', '        ']);
});

test('mapColor resolves through the view own palette', function (): void {
    $v = new PalettedView(Rect::of(0, 0, 4, 4));

    // PalettedView::getPalette() returns bytes [1=>0x71, 2=>0x1F]
    expect($v->mapColor(1))->toBe(0x71)
        ->and($v->mapColor(2))->toBe(0x1F)
        ->and($v->mapColor(9))->toBe(0x07); // out of range -> fallback
});

/** A minimal Group-less root that owns views and exposes a Screen. */
final class RootStub extends View
{
    /** @var list<View> */
    private array $children = [];

    public function __construct(private readonly Screen $screen)
    {
        parent::__construct(Rect::of(0, 0, $screen->cols(), $screen->rows()));
    }

    public function insert(View $view): void
    {
        $view->setOwner($this);
        $this->children[] = $view;
    }

    /** @return list<View> */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function screen(): Screen
    {
        return $this->screen;
    }
}

/** A view carrying its own two-entry palette, for mapColor tests. */
final class PalettedView extends View
{
    public function getPalette(): Palette
    {
        return new Palette([1 => 0x71, 2 => 0x1F]);
    }
}
