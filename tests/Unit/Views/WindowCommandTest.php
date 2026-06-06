<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

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

test('cmResize moves the window inside the desktop, clamped to size limits', function (): void {
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

test('selecting the window marks its frame active', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $desk->insert($w);

    $w->setState(State::Selected, true);

    $frame = $w->subviews()[0];
    expect($frame)->toBeInstanceOf(Frame::class)
        ->and($frame->getState(State::Active))->toBeTrue();
});
