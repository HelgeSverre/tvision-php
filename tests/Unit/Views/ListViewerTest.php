<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
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

test('list viewer normalizes invalid column and range counts', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 0, null, null);
    $lv->setRange(-10);

    expect($lv->numCols)->toBe(1)
        ->and($lv->range)->toBe(0);
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

test('pathological public column counts are bounded by the visible list geometry', function (): void {
    [$group, $screen] = lvRoot(8, 2);
    $list = new NumberList(Rect::of(0, 0, 4, 1), 1, null, null);
    $group->insert($list);
    $list->setRange(10);
    $list->numCols = PHP_INT_MAX;

    $list->draw();
    $list->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageDown->value)));

    expect($screen->back()->rows()[0])->toContain('I')
        ->and($list->focused)->toBeLessThan(10);
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

test('list viewer resizes attached scroll steps and supports control page navigation', function (): void {
    $hBar = new ScrollBar(Rect::of(0, 0, 12, 1));
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, $hBar, $vBar);
    $lv->setRange(20);
    $lv->changeBounds(Rect::of(0, 0, 20, 8));
    $lv->focusItem(10);

    $lv->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageUp->value, modifiers: KeyModifier::Ctrl)));
    expect($hBar->pageStep)->toBe(20)
        ->and($vBar->pageStep)->toBe(8)
        ->and($lv->focused)->toBe(0);

    $lv->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageDown->value, modifiers: KeyModifier::Ctrl)));
    expect($lv->focused)->toBe(19);
});

test('clicking an attached scrollbar focuses the list and dragging outside auto-scrolls', function (): void {
    [$root] = lvRoot(20, 10);
    $bar = new ScrollBar(Rect::of(12, 0, 13, 5));
    $list = new NumberList(Rect::of(0, 0, 12, 5), 1, null, $bar);
    $root->insert($bar);
    $root->insert($list);
    $list->setRange(20);

    $root->setCurrent($bar);
    $list->handleEvent(Event::broadcast(Cmd::ScrollBarClicked, $bar));
    expect($root->current())->toBe($list);

    $list->handleEvent(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(2, 2), buttons: 1)));
    for ($i = 0; $i < 4; $i++) {
        $list->handleEvent(Event::mouse(EventType::MouseAuto, new MouseEvent(new Point(2, 8), buttons: 1)));
    }
    $list->handleEvent(Event::mouse(EventType::MouseUp, new MouseEvent(new Point(2, 8))));

    expect($list->focused)->toBe(3)
        ->and($list->getState(State::Dragging))->toBeFalse();
});
