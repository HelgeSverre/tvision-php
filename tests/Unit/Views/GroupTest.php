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
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** A view that records every event it handled and can consume on a target command. */
final class RecordingView extends View
{
    /** @var list<EventType> */
    public array $seen = [];

    public ?int $consumeCommand = null;

    public function handleEvent(Event $event): void
    {
        $this->seen[] = $event->what;
        if ($this->consumeCommand !== null && $event->isCommand($this->consumeCommand)) {
            $this->clearEvent($event);
        }
    }
}

/** A Group rooted at a real Screen, for compositing/exposed assertions. */
final class RootGroup extends Group
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

test('insert adds subviews and sets their owner', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $child = new View(Rect::of(1, 1, 4, 4));

    $g->insert($child);

    expect($g->subviews())->toBe([$child])
        ->and($child->owner)->toBe($g);
});

test('a positional event routes to the subview under the mouse', function (): void {
    $g = new Group(Rect::of(0, 0, 20, 10));
    $left = new RecordingView(Rect::of(0, 0, 5, 10));
    $right = new RecordingView(Rect::of(10, 0, 20, 10));
    $g->insert($left);
    $g->insert($right);

    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(12, 2)));
    $g->handleEvent($ev);

    expect($right->seen)->toBe([EventType::MouseDown])
        ->and($left->seen)->toBe([]);
});

test('a positional event is translated through nested group origins', function (): void {
    $root = new Group(Rect::of(0, 0, 80, 25));
    $nested = new Group(Rect::of(30, 5, 50, 15));
    $leaf = new RecordingView(Rect::of(0, 0, 10, 4));
    $nested->insert($leaf);
    $root->insert($nested);

    $root->handleEvent(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(31, 6)),
    ));

    expect($leaf->seen)->toBe([EventType::MouseDown]);
});

test('a focused event routes to current first', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 5));
    $b = new RecordingView(Rect::of(0, 5, 10, 10));
    $a->options |= State::Selectable; // ofSelectable is an option flag
    $b->options |= State::Selectable;
    $g->insert($a);
    $g->insert($b);
    $g->setCurrent($b);

    $ev = Event::keyDown(new KeyDownEvent(Key::Enter->value));
    $g->handleEvent($ev);

    expect($b->seen)->toBe([EventType::KeyDown]);
});

test('a broadcast event fans out to every subview', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 5));
    $b = new RecordingView(Rect::of(0, 5, 10, 10));
    $g->insert($a);
    $g->insert($b);

    $g->handleEvent(Event::broadcast(Cmd::FirstUser));

    expect($a->seen)->toBe([EventType::Broadcast])
        ->and($b->seen)->toBe([EventType::Broadcast]);
});

test('draw() draws each subview via drawView (visible only)', function (): void {
    $screen = new Screen(new HeadlessDriver(6, 2));
    $screen->init();
    $g = new RootGroup($screen);

    $child = new View(Rect::of(0, 0, 3, 1));
    $g->insert($child);
    $child->writeStr(0, 0, '...', 0x07); // pre-seed so we can see it survives a draw
    // default View::draw fills with blanks, so after group draw the child area is blank
    $g->draw();

    expect($screen->back()->rows())->toBe(['      ', '      ']);
});

test('selectNext moves focus across selectable subviews', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 3));
    $b = new RecordingView(Rect::of(0, 3, 10, 6));
    $a->options |= State::Selectable; // ofSelectable is an option flag
    $b->options |= State::Selectable;
    $g->insert($a);
    $g->insert($b);

    $g->setCurrent($a);
    $g->selectNext();
    expect($g->current())->toBe($b);

    $g->selectNext();
    expect($g->current())->toBe($a); // wraps

    $g->selectPrevious();
    expect($g->current())->toBe($b); // reverse wraps
});

test('execView pumps a modal view until it ends modal, returning the command', function (): void {
    // A modal view that ends modal with Cmd::Ok the first time it sees any key.
    $modal = new class(Rect::of(0, 0, 4, 2)) extends View {
        public function handleEvent(Event $event): void
        {
            if ($event->asKey() !== null) {
                $this->owner?->endModal(\HelgeSverre\TurboVision\Events\Cmd::Ok);
                $this->clearEvent($event);
            }
        }
    };

    $driver = new HeadlessDriver(10, 5);
    $screen = new Screen($driver);
    $screen->init();
    $g = new RootGroup($screen);

    // Feed one keystroke so the modal handler fires and ends modal.
    $driver->feedInput("\r");

    $result = $g->execView($modal);

    expect($result)->toBe(Cmd::Ok)
        ->and($g->subviews())->not->toContain($modal); // removed after modal ends
});
