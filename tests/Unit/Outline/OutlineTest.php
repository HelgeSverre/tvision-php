<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Outline\Node;
use HelgeSverre\TurboVision\Outline\Outline;
use HelgeSverre\TurboVision\Outline\OutlineViewer;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use LogicException;

final class OutlineRootGroup extends Group
{
    /** @var list<int> */
    public array $commands = [];

    public function __construct(private readonly Screen $testScreen)
    {
        parent::__construct(Rect::of(0, 0, $testScreen->cols(), $testScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->testScreen;
    }

    public function handleEvent(Event $event): void
    {
        if ($event->asMessage() !== null) {
            $this->commands[] = $event->asMessage()->command;
        }
        parent::handleEvent($event);
    }
}

/** @return array{0: OutlineRootGroup, 1: Screen} */
function outlineRoot(int $cols = 40, int $rows = 10): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();

    return [new OutlineRootGroup($screen), $screen];
}

/**
 * Project
 * ├─ src
 * │  └─ OutlineViewer.php
 * └─ tests
 */
function outlineTree(): Node
{
    $src = new Node('src', new Node('OutlineViewer.php'));

    return new Node('Project', Node::siblings($src, new Node('tests')));
}

test('linked Node sibling helper preserves display order', function (): void {
    $one = new Node('one');
    $two = new Node('two');
    $three = new Node('three');

    expect(Node::siblings($one, $two, $three))->toBe($one)
        ->and($one->next)->toBe($two)
        ->and($two->next)->toBe($three)
        ->and($three->next)->toBeNull();
});

test('outline computes visible preorder limits and resolves visible nodes', function (): void {
    $outline = new Outline(Rect::of(0, 0, 28, 5), null, null, outlineTree());

    expect($outline->limit->y)->toBe(4)
        ->and($outline->getNode(0)?->text)->toBe('Project')
        ->and($outline->getNode(1)?->text)->toBe('src')
        ->and($outline->getNode(2)?->text)->toBe('OutlineViewer.php')
        ->and($outline->getNode(3)?->text)->toBe('tests')
        ->and($outline->getNode(4))->toBeNull()
        ->and($outline->limit->x)->toBeGreaterThanOrEqual(18);
});

test('outline draws single-cell unicode tree connectors without shifting text', function (): void {
    [$root, $screen] = outlineRoot();
    $outline = new Outline(Rect::of(0, 0, 28, 5), null, null, outlineTree());
    $root->insert($outline);
    $outline->draw();

    $rows = $screen->back()->rows();
    expect($rows[0])->toStartWith('└──Project')
        ->and($rows[1])->toStartWith('   ├──src')
        ->and($rows[2])->toStartWith('   │  └──OutlineViewer.php')
        ->and($rows[3])->toStartWith('   └──tests');
});

test('graph click and plus/minus keys expand and collapse only nodes with children', function (): void {
    [$root] = outlineRoot();
    $outline = new Outline(Rect::of(0, 0, 28, 5), null, null, outlineTree());
    $root->insert($outline);

    // Click src's graph area (line 1) to collapse it.
    $click = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(0, 1)));
    $outline->handleEvent($click);
    expect($outline->getNode(1)?->expanded)->toBeFalse()
        ->and($outline->limit->y)->toBe(3)
        ->and($click->isNothing())->toBeTrue();

    $plus = Event::keyDown(new KeyDownEvent(0, '+'));
    $outline->handleEvent($plus);
    expect($outline->getNode(1)?->expanded)->toBeTrue()
        ->and($outline->limit->y)->toBe(4)
        ->and($plus->isNothing())->toBeTrue();

    $minus = Event::keyDown(new KeyDownEvent(0, '-'));
    $outline->handleEvent($minus);
    expect($outline->getNode(1)?->expanded)->toBeFalse()
        ->and($outline->limit->y)->toBe(3);
});

test('keyboard navigation keeps focus visible even without a vertical scroll bar', function (): void {
    $root = new Node('0');
    $tail = $root;
    for ($i = 1; $i < 10; $i++) {
        $tail->childList = new Node((string) $i);
        $tail = $tail->childList;
    }
    [$group] = outlineRoot();
    $outline = new Outline(Rect::of(0, 0, 20, 3), null, null, $root);
    $group->insert($outline);

    for ($i = 0; $i < 6; $i++) {
        $outline->handleEvent(Event::key(Key::Down));
    }
    expect($outline->focused)->toBe(6)
        ->and($outline->delta->y)->toBe(4);

    $outline->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageUp->value, '', KeyModifier::Ctrl)));
    expect($outline->focused)->toBe(0)
        ->and($outline->delta->y)->toBe(0);
});

test('selection broadcasts the original outline command and double click consumes the event', function (): void {
    [$root] = outlineRoot();
    $outline = new Outline(Rect::of(0, 0, 28, 5), null, null, outlineTree());
    $root->insert($outline);

    $event = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(8, 1), doubleClick: true));
    $outline->handleEvent($event);

    expect($event->isNothing())->toBeTrue()
        ->and($root->commands)->toContain(OutlineViewer::ItemSelected);
});

test('asterisk recursively expands descendants and node positions update', function (): void {
    $root = outlineTree();
    $root->expanded = false;
    $outline = new Outline(Rect::of(0, 0, 28, 5), null, null, $root);
    expect($outline->limit->y)->toBe(1);

    $event = Event::keyDown(new KeyDownEvent(0, '*'));
    $outline->handleEvent($event);
    expect($outline->limit->y)->toBe(4)
        ->and($event->isNothing())->toBeTrue();
});

test('group routes a captured outline mouse release after the pointer leaves its bounds', function (): void {
    [$root] = outlineRoot();
    $outline = new Outline(Rect::of(2, 2, 24, 6), null, null, outlineTree());
    $root->insert($outline);

    $down = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(4, 3)));
    $root->handleEvent($down);
    expect($down->isNothing())->toBeTrue()
        ->and($outline->getState(State::Dragging))->toBeTrue();

    $up = Event::mouse(EventType::MouseUp, new MouseEvent(new Point(35, 9)));
    $root->handleEvent($up);
    expect($outline->getState(State::Dragging))->toBeFalse();
});

test('outline rejects cyclic and reused public node graphs rather than recursing forever', function (): void {
    $self = new Node('self');
    $self->childList = $self;

    expect(fn (): Outline => new Outline(Rect::of(0, 0, 20, 4), null, null, $self))
        ->toThrow(LogicException::class, 'tree');

    $first = new Node('first');
    $second = new Node('second');
    $first->next = $second;
    $second->next = $first;
    $root = new Node('root', $first);

    expect(fn (): Outline => new Outline(Rect::of(0, 0, 20, 4), null, null, $root))
        ->toThrow(LogicException::class, 'sibling list');
});

test('folded legacy Ctrl+PageUp/PageDown key codes jump to the outline ends', function (): void {
    $root = new Node('0');
    $tail = $root;
    for ($i = 1; $i < 10; $i++) {
        $tail->childList = new Node((string) $i);
        $tail = $tail->childList;
    }
    [$group] = outlineRoot();
    $outline = new Outline(Rect::of(0, 0, 20, 3), null, null, $root);
    $group->insert($outline);

    // Real terminals fold the modifier into the combined legacy code
    // (EscapeDecoder::legacyKeyCode), so this is what actually arrives.
    $outline->handleEvent(Event::keyDown(new KeyDownEvent(Key::CtrlPageDown->value)));
    expect($outline->focused)->toBe(9);

    $outline->handleEvent(Event::keyDown(new KeyDownEvent(Key::CtrlPageUp->value)));
    expect($outline->focused)->toBe(0);
});
