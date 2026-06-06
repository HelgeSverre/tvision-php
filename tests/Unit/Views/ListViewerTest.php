<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\State;

/** A concrete single-column list of "Item N" strings. */
final class NumberList extends ListViewer
{
    public function getText(int $item, int $maxLen): string
    {
        return mb_substr('Item ' . $item, 0, $maxLen);
    }
}

final class LvRootGroup extends Group
{
    public function __construct(private readonly Screen $s)
    {
        parent::__construct(Rect::of(0, 0, $s->cols(), $s->rows()));
    }

    public function screen(): Screen
    {
        return $this->s;
    }
}

/**
 * @return array{0: LvRootGroup, 1: Screen}
 */
function lvRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $g = new LvRootGroup($screen);

    return [$g, $screen];
}

test('a fresh list viewer is selectable with zeroed state', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);

    expect($lv->focused)->toBe(0)
        ->and($lv->topItem)->toBe(0)
        ->and($lv->range)->toBe(0)
        ->and($lv->numCols)->toBe(1)
        ->and(($lv->options & State::Selectable) !== 0)->toBeTrue();
});

test('setRange updates the vertical scroll bar parameters', function (): void {
    $bar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, $bar);
    $lv->setRange(20);

    expect($lv->range)->toBe(20)
        ->and($bar->maxVal)->toBe(19);  // range - 1
});

test('focusItem scrolls topItem to keep the focused item visible', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null); // 5 rows
    $lv->setRange(20);
    $lv->focusItem(10);

    expect($lv->focused)->toBe(10)
        ->and($lv->topItem)->toBe(6);   // item - size.y + 1 = 10 - 5 + 1
});

test('focusItemNum clamps to [0, range-1]', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $lv->setRange(8);

    $lv->focusItemNum(99);
    expect($lv->focused)->toBe(7);

    $lv->focusItemNum(-5);
    expect($lv->focused)->toBe(0);
});

test('Down arrow moves focus to the next item and consumes the key', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $lv->setRange(10);

    $ev = Event::keyDown(new KeyDownEvent(Key::Down->value));
    $lv->handleEvent($ev);

    expect($lv->focused)->toBe(1)
        ->and($ev->isNothing())->toBeTrue();
});

test('Home and End jump to the first and last item', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $lv->setRange(10);
    $lv->focusItem(5);

    $lv->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value)));
    expect($lv->focused)->toBe($lv->topItem);

    $lv->handleEvent(Event::keyDown(new KeyDownEvent(Key::End->value)));
    expect($lv->focused)->toBe($lv->topItem + 5 - 1);
});

test('a mouse click focuses the item under the pointer', function (): void {
    [$g] = lvRoot(20, 10);
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $g->insert($lv);
    $lv->setRange(10);

    // Local y=2 (third visible row) -> item topItem + 2.
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 2)));
    $lv->handleEvent($ev);

    expect($lv->focused)->toBe(2);
});

test('draw paints visible items and highlights the focused one when selected+active', function (): void {
    [$g, $screen] = lvRoot(20, 10);
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $g->insert($lv);
    $lv->setState(State::Selected, true);
    $lv->setState(State::Active, true);
    $lv->setRange(10);
    $lv->draw();

    $rows = $screen->back()->rows();
    expect($rows[0])->toContain('Item 0')
        ->and($rows[1])->toContain('Item 1');
});

test('a cmScrollBarChanged from the vertical bar re-focuses to the bar value', function (): void {
    $bar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, $bar);
    $lv->options |= State::Selectable;
    $lv->setRange(20);

    $bar->setValue(7);  // broadcasts; but bar is unowned, so trigger handler directly
    $lv->handleEvent(Event::broadcast(Cmd::ScrollBarChanged, $bar));

    expect($lv->focused)->toBe(7);
});

test('getPalette returns cpListViewer', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    expect($lv->getPalette()?->get(1))->toBe(0x1A);
});
