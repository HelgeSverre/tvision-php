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
use HelgeSverre\TurboVision\Views\Group;
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

test('view bounds cannot have a negative extent', function (): void {
    expect(fn () => new View(Rect::of(4, 0, 3, 1)))
        ->toThrow(InvalidArgumentException::class, 'non-negative')
        ->and(fn () => (new View(Rect::of(0, 0, 1, 1)))->setBounds(Rect::of(0, 2, 1, 1)))
        ->toThrow(InvalidArgumentException::class, 'non-negative');
});

test('view bounds reject process-exhausting draw extents before rendering', function (): void {
    expect(fn () => new View(Rect::of(0, 0, 1, PHP_INT_MAX)))
        ->toThrow(InvalidArgumentException::class, 'safe drawable-cell limit')
        ->and(fn () => new View(Rect::of(0, 0, 2_000, 2_000)))
        ->toThrow(InvalidArgumentException::class, 'safe drawable-cell limit');
});

test('validated ownership rejects direct cycles', function (): void {
    $parent = new View(Rect::of(0, 0, 2, 2));
    $child = new View(Rect::of(0, 0, 1, 1));
    $child->setOwner($parent);

    expect(fn () => $parent->setOwner($child))
        ->toThrow(InvalidArgumentException::class, 'cannot own itself');
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

test('screen writes are clipped to every ancestor extent', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 3));
    $screen->init();
    $root = new RootStub($screen);
    $parent = new Group(Rect::of(3, 1, 7, 2));
    $child = new View(Rect::of(-2, 0, 8, 1));
    $root->insert($parent);
    $parent->insert($child);

    $child->writeStr(0, 0, 'ABCDEFGHIJ', 0x07);

    expect($screen->back()->rows())->toBe([
        '          ',
        '   CDEF   ',
        '          ',
    ]);
});

test('drawView does not paint through a hidden ancestor', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 2));
    $screen->init();
    $root = new RootStub($screen);
    $parent = new Group(Rect::of(0, 0, 8, 2));
    $child = new class(Rect::of(0, 0, 4, 1)) extends View {
        public function draw(): void
        {
            $this->writeStr(0, 0, 'LEAK', 0x07);
        }
    };
    $root->insert($parent);
    $parent->insert($child);
    $parent->setState(State::Visible, false);

    $child->drawView();

    expect($screen->back()->rows()[0])->toBe('        ');
});

test('writeLine copies from source column zero when the destination x is non-zero', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 2));
    $screen->init();
    $root = new RootStub($screen);
    $view = new View(Rect::of(0, 0, 8, 2));
    $root->insert($view);
    $buffer = new DrawBuffer(3);
    $buffer->moveStr(0, 'ABC', 0x07);

    $view->writeLine(3, 0, 3, 1, $buffer);

    expect($screen->back()->rows()[0])->toBe('   ABC  ');
});

test('screen writes bound pathological dimensions to the visible intersection', function (): void {
    $screen = new Screen(new HeadlessDriver(4, 2));
    $screen->init();
    $root = new RootStub($screen);
    $view = new View(Rect::of(0, 0, 4, 2));
    $root->insert($view);
    $buffer = new DrawBuffer(2);
    $buffer->moveStr(0, 'AB', 0x07);

    $view->writeBuf(-1, -1, PHP_INT_MAX, PHP_INT_MAX, $buffer);

    expect($screen->back()->rows())->toBe(['B   ', 'B   ']);
});

test('nested views with extreme off-screen origins clip without coordinate overflow', function (): void {
    $screen = new Screen(new HeadlessDriver(4, 2));
    $screen->init();
    $root = new RootStub($screen);
    $parent = new Group(Rect::of(PHP_INT_MAX - 2, 0, PHP_INT_MAX, 1));
    $child = new View(Rect::of(10, 0, 11, 1));
    $root->insert($parent);
    $parent->insert($child);

    $child->writeStr(0, 0, 'X', 0x07);

    expect($child->absoluteOrigin())->toEqual(new Point(PHP_INT_MAX, 0))
        ->and($screen->back()->rows())->toBe(['    ', '    ']);
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
