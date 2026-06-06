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
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\FrameOwner;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/** A FrameOwner that records the events the Frame puts back into the queue. */
final class CapturingWindow extends Group implements FrameOwner
{
    /** @var list<Event> */
    public array $puts = [];

    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }

    public function putEvent(Event $event): void
    {
        $this->puts[] = clone $event;
    }

    public function frameTitle(): string
    {
        return 'Demo';
    }

    public function frameFlags(): int
    {
        return WindowFlags::Default;
    }

    public function frameNumber(): int
    {
        return 1;
    }

    public function frameIsZoomed(): bool
    {
        return false;
    }
}

test('a click on the close icon puts a cmClose command and consumes the event', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // Local x=3 (inside close zone 2..4), y=0.
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 0)));
    $frame->handleEvent($ev);

    expect($win->puts)->toHaveCount(1)
        ->and($win->puts[0]->what)->toBe(EventType::Command)
        ->and($win->puts[0]->asMessage()?->command)->toBe(Cmd::Close)
        ->and($ev->isNothing())->toBeTrue();
});

test('a click on the zoom icon puts a cmZoom command', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // Zoom zone is x in [w-5, w-3] = [15,17].
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(16, 0)));
    $frame->handleEvent($ev);

    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Zoom);
});

test('a double-click on the title line puts a cmZoom command', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // x=10 (title area, not close/zoom), doubleClick=true.
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(10, 0), buttons: 1, doubleClick: true));
    $frame->handleEvent($ev);

    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Zoom);
});

test('a single click on the title area requests a move (cmResize with move flag)', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(10, 0)));
    $frame->handleEvent($ev);

    // The frame emits a cmResize command carrying the drag intent for the window to run.
    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Resize)
        ->and($ev->isNothing())->toBeTrue();
});

test('a click on the bottom-right resize zone requests a resize', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // Bottom-right: x >= w-2 (18), y >= h-1 (5).
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(19, 5)));
    $frame->handleEvent($ev);

    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Resize);
});

test('an inactive frame ignores icon clicks', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, false);

    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 0)));
    $frame->handleEvent($ev);

    // Inactive frames still allow a move (TV behaviour), but no close/zoom command.
    expect($win->puts)->not->toBeEmpty()
        ->and($win->puts[0]->asMessage()?->command)->toBe(Cmd::Resize);
});
