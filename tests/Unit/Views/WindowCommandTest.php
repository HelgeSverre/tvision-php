<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Commands\CommandTarget;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

final class RejectCloseWindow extends Window
{
    public function valid(int $command): bool
    {
        return $command !== Cmd::Close;
    }
}

/** A minimal command-state root, used to verify Window's selected-state contract. */
final class WindowCommandRoot extends Group implements CommandTarget
{
    /** @var array<int, true> */
    private array $disabled = [];

    public function enableCommand(int $command): void
    {
        unset($this->disabled[$command]);
    }

    public function disableCommand(int $command): void
    {
        $this->disabled[$command] = true;
    }

    public function commandEnabled(int $command): bool
    {
        return !isset($this->disabled[$command]);
    }
}

/** A desktop-like root owning windows. */
final class WinRootGroup extends Group
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
 * @return array{0: WinRootGroup, 1: Screen}
 */
function winRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $g = new WinRootGroup($screen);

    return [$g, $screen];
}

test('cmClose removes the window from its owner', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $desk->insert($w);
    expect($desk->subviews())->toContain($w);

    $w->handleEvent(Event::command(Cmd::Close, $w));

    expect($desk->subviews())->not->toContain($w);
});

test('cmClose honours validation and leaves a rejected window owned', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new RejectCloseWindow(Rect::of(0, 0, 26, 7), 'Unsaved', 1);
    $desk->insert($w);

    $event = Event::command(Cmd::Close, $w);
    $w->handleEvent($event);

    expect($desk->subviews())->toContain($w)
        ->and($event->isNothing())->toBeTrue();
});

test('a modal cmClose is converted to queued cmCancel instead of being detached', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Modal', 1);
    $desk->insert($w);
    $w->setState(State::Modal, true);

    $w->handleEvent(Event::command(Cmd::Close, $w));
    $queued = $desk->pumpEvent();

    expect($desk->subviews())->toContain($w)
        ->and($queued?->isCommand(Cmd::Cancel))->toBeTrue()
        ->and($queued?->asMessage()?->info)->toBe($w);
});

test('cmZoom toggles the window to the desktop extent and back', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(2, 2, 28, 9), 'Demo', 1);
    $desk->insert($w);

    $w->handleEvent(Event::command(Cmd::Zoom, $w));
    // Zoomed: fills the desktop (0,0,80,25).
    expect($w->getBounds())->toEqual(Rect::of(0, 0, 80, 25));

    $w->handleEvent(Event::command(Cmd::Zoom, $w));
    // Restored to the original bounds.
    expect($w->getBounds())->toEqual(Rect::of(2, 2, 28, 9));
});

test('zoom is a safe no-op until the window belongs to a desktop', function (): void {
    $w = new Window(Rect::of(2, 2, 28, 9), 'Detached', 1);

    $w->zoom();

    expect($w->getBounds())->toEqual(Rect::of(2, 2, 28, 9));
});

test('resizeTo keeps the window inside the desktop and clamps to size limits', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(2, 2, 28, 9), 'Demo', 1);
    $desk->insert($w);

    // Resize to a tiny rect — sizeLimits floors width/height to 16x6.
    $w->resizeTo(Rect::of(0, 0, 4, 4));

    expect($w->getBounds()->width())->toBe(16)
        ->and($w->getBounds()->height())->toBe(6);
});

test('Tab cycles focus among selectable subviews', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    // Two selectable children besides the frame.
    $a = new View(Rect::of(1, 1, 5, 5));
    $a->options |= State::Selectable; // ofSelectable is an option flag
    $b = new View(Rect::of(6, 1, 10, 5));
    $b->options |= State::Selectable;
    $w->insert($a);
    $w->insert($b);
    $w->setCurrent($a);
    $desk->insert($w);

    $ev = Event::keyDown(new KeyDownEvent(Key::Tab->value));
    $w->handleEvent($ev);

    expect($w->current())->toBe($b)
        ->and($ev->isNothing())->toBeTrue();
});

test('Shift-Tab cycles focus backward', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $a = new View(Rect::of(1, 1, 5, 5));
    $a->options |= State::Selectable;
    $b = new View(Rect::of(6, 1, 10, 5));
    $b->options |= State::Selectable;
    $w->insert($a);
    $w->insert($b);
    $w->setCurrent($a);
    $desk->insert($w);

    $event = Event::keyDown(new KeyDownEvent(Key::ShiftTab->value));
    $w->handleEvent($event);

    expect($w->current())->toBe($b)
        ->and($event->isNothing())->toBeTrue();
});

test('title-bar drag moves a window through captured mouse events', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(10, 4, 36, 11), 'Demo', 1);
    $desk->insert($w);

    $down = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(20, 4), 1));
    $desk->handleEvent($down);
    expect($w->getState(State::Dragging))->toBeTrue();

    $desk->handleEvent(Event::mouse(EventType::MouseMove, new MouseEvent(new Point(25, 7), 1)));
    $desk->handleEvent(Event::mouse(EventType::MouseUp, new MouseEvent(new Point(25, 7), 1)));

    expect($w->getBounds())->toEqual(Rect::of(15, 7, 41, 14))
        ->and($w->getState(State::Dragging))->toBeFalse();
});

test('bottom-right drag resizes a window through captured mouse events', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(10, 4, 36, 11), 'Demo', 1);
    $desk->insert($w);

    $desk->handleEvent(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(35, 10), 1),
    ));
    $desk->handleEvent(Event::mouse(
        EventType::MouseMove,
        new MouseEvent(new Point(40, 13), 1),
    ));
    $desk->handleEvent(Event::mouse(
        EventType::MouseUp,
        new MouseEvent(new Point(40, 13), 1),
    ));

    expect($w->getBounds())->toEqual(Rect::of(10, 4, 41, 14));
});

test('selecting the window marks its frame active', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $desk->insert($w);

    $w->setState(State::Selected, true);

    $frame = $w->subviews()[0];
    expect($frame)->toBeInstanceOf(Frame::class)
        ->and($frame->getState(State::Active))->toBeTrue();
});

test('window-number broadcasts select the matching numbered window and consume the broadcast', function (): void {
    [$desk] = winRoot(80, 25);
    $one = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $two = new Window(Rect::of(2, 2, 28, 9), 'Two', 2);
    $desk->insert($one);
    $desk->insert($two);
    $desk->setCurrent($one);

    $event = Event::broadcast(Cmd::SelectWindowNum, 2);
    $desk->handleEvent($event);

    expect($desk->current())->toBe($two)
        ->and($event->isNothing())->toBeTrue();
});

test('selected windows enable their allowed command set and disable it when deselected', function (): void {
    $root = new WindowCommandRoot(Rect::of(0, 0, 80, 25));
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $root->insert($w);

    expect($root->commandEnabled(Cmd::Next))->toBeTrue()
        ->and($root->commandEnabled(Cmd::Prev))->toBeTrue()
        ->and($root->commandEnabled(Cmd::Resize))->toBeTrue()
        ->and($root->commandEnabled(Cmd::Close))->toBeTrue()
        ->and($root->commandEnabled(Cmd::Zoom))->toBeTrue();

    $root->setCurrent(null);

    expect($root->commandEnabled(Cmd::Next))->toBeFalse()
        ->and($root->commandEnabled(Cmd::Prev))->toBeFalse()
        ->and($root->commandEnabled(Cmd::Resize))->toBeFalse()
        ->and($root->commandEnabled(Cmd::Close))->toBeFalse()
        ->and($root->commandEnabled(Cmd::Zoom))->toBeFalse();
});

test('title, number, flags, and palette setters refresh window metadata safely', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Old', 1);

    $w->setTitle('New');
    $w->setNumber(8);
    $w->setFlags(WindowFlags::Move | WindowFlags::Close);
    $w->setPalette(999);

    expect($w->getTitle())->toBe('New')
        ->and($w->frameTitle())->toBe('New')
        ->and($w->frameNumber())->toBe(8)
        ->and($w->frameFlags())->toBe(WindowFlags::Move | WindowFlags::Close)
        ->and($w->paletteIndex())->toBe(0)
        ->and($w->frame())->toBeInstanceOf(Frame::class);
});
