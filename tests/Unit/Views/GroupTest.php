<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
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
use HelgeSverre\TurboVision\Views\Window;

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

test('ownership operations reject cycles, duplicate ownership, and foreign focus', function (): void {
    $parent = new Group(Rect::of(0, 0, 10, 10));
    $child = new Group(Rect::of(0, 0, 5, 5));
    $foreign = new View(Rect::of(0, 0, 1, 1));
    $parent->insert($child);

    expect(fn () => $parent->insert($parent))
        ->toThrow(InvalidArgumentException::class, 'cannot own itself')
        ->and(fn () => $child->insert($parent))
        ->toThrow(InvalidArgumentException::class, 'ownership cycle')
        ->and(fn () => $parent->insert($child))
        ->toThrow(InvalidArgumentException::class, 'must be unowned')
        ->and(fn () => $parent->setCurrent($foreign))
        ->toThrow(InvalidArgumentException::class, 'must belong');
});

test('removing a foreign view does not detach it from its real owner', function (): void {
    $owner = new Group(Rect::of(0, 0, 10, 10));
    $other = new Group(Rect::of(0, 0, 10, 10));
    $view = new View(Rect::of(0, 0, 1, 1));
    $owner->insert($view);

    $other->remove($view);

    expect($view->owner)->toBe($owner)
        ->and($owner->subviews())->toContain($view);
});

test('a root group queues putEvent events for its modal pump', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 5));
    $screen->init();
    $group = new RootGroup($screen);
    $group->putEvent(Event::command(Cmd::FirstUser));

    expect($group->pumpEvent()?->isCommand(Cmd::FirstUser))->toBeTrue();
});

test('a root group preserves every event decoded by one screen poll', function (): void {
    $driver = new HeadlessDriver(10, 5);
    $screen = new Screen($driver);
    $screen->init();
    $group = new RootGroup($screen);
    $driver->feedInput('ab');

    expect($group->pumpEvent()?->asKey()?->char)->toBe('a')
        ->and($group->pumpEvent()?->asKey()?->char)->toBe('b');
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

test('focused routing honors pre-process, current, and post-process phases', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $current = new RecordingView(Rect::of(0, 0, 10, 2));
    $ordinary = new RecordingView(Rect::of(0, 2, 10, 4));
    $pre = new RecordingView(Rect::of(0, 4, 10, 6));
    $post = new RecordingView(Rect::of(0, 6, 10, 8));
    $current->options |= State::Selectable;
    $pre->options |= State::PreProcess;
    $post->options |= State::PostProcess;
    $g->insert($current);
    $g->insert($ordinary);
    $g->insert($pre);
    $g->insert($post);

    $event = Event::command(Cmd::FirstUser);
    $g->handleEvent($event);

    expect($pre->seen)->toBe([EventType::Command])
        ->and($current->seen)->toBe([EventType::Command])
        ->and($post->seen)->toBe([EventType::Command])
        ->and($ordinary->seen)->toBe([]);
});

test('a pre-process handler can consume an event before the current view', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $current = new RecordingView(Rect::of(0, 0, 10, 2));
    $pre = new RecordingView(Rect::of(0, 2, 10, 4));
    $current->options |= State::Selectable;
    $pre->options |= State::PreProcess;
    $pre->consumeCommand = Cmd::FirstUser;
    $g->insert($current);
    $g->insert($pre);

    $g->handleEvent(Event::command(Cmd::FirstUser));

    expect($pre->seen)->toBe([EventType::Command])
        ->and($current->seen)->toBe([]);
});

test('hidden and disabled views are excluded from focused routing', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $hiddenCurrent = new RecordingView(Rect::of(0, 0, 10, 2));
    $disabledPre = new RecordingView(Rect::of(0, 2, 10, 4));
    $hiddenCurrent->options |= State::Selectable;
    $disabledPre->options |= State::PreProcess;
    $g->insert($hiddenCurrent);
    $g->insert($disabledPre);
    $hiddenCurrent->setState(State::Visible, false);
    $disabledPre->setState(State::Disabled, true);

    $g->handleEvent(Event::keyDown(new KeyDownEvent(Key::Enter->value)));

    expect($hiddenCurrent->seen)->toBe([])
        ->and($disabledPre->seen)->toBe([]);
});

test('a broadcast event fans out to every subview', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 5));
    $b = new RecordingView(Rect::of(0, 5, 10, 10));
    $a->eventMask |= EventMask::Broadcast;
    $b->eventMask |= EventMask::Broadcast;
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

test('execView restores modal ownership and state when a handler throws', function (): void {
    $modal = new class(Rect::of(0, 0, 4, 2)) extends View {
        public function handleEvent(Event $event): void
        {
            throw new RuntimeException('modal failed');
        }
    };
    $driver = new HeadlessDriver(10, 5);
    $screen = new Screen($driver);
    $screen->init();
    $group = new RootGroup($screen);
    $driver->feedInput("\r");

    expect(fn (): int => $group->execView($modal))
        ->toThrow(RuntimeException::class, 'modal failed')
        ->and($modal->owner)->toBeNull()
        ->and($modal->getState(State::Modal))->toBeFalse()
        ->and($group->subviews())->not->toContain($modal);
});

test('execView selects a dialog while modal and restores the preceding window afterwards', function (): void {
    $screen = new Screen(new HeadlessDriver(50, 20));
    $screen->init();
    $root = new RootGroup($screen);
    $window = new Window(Rect::of(0, 0, 20, 8), 'Existing');
    $dialog = new class(Rect::of(5, 4, 28, 12), 'Modal') extends Dialog {
        public bool $wasCurrent = false;
        public bool $frameWasActive = false;

        public function handleEvent(Event $event): void
        {
            $this->wasCurrent = $this->owner instanceof Group && $this->owner->current() === $this;
            $this->frameWasActive = $this->subviews()[0]->getState(State::Active);
            parent::handleEvent($event);
        }
    };
    $root->insert($window);
    $root->setCurrent($window);
    $root->putEvent(Event::command(Cmd::Cancel));

    expect($root->execView($dialog))->toBe(Cmd::Cancel)
        ->and($dialog->wasCurrent)->toBeTrue()
        ->and($dialog->frameWasActive)->toBeTrue()
        ->and($root->current())->toBe($window)
        ->and($window->getState(State::Selected))->toBeTrue();
});

test('execView presents the modal before waiting for its first event', function (): void {
    $driver = new HeadlessDriver(40, 12);
    $screen = new Screen($driver);
    $screen->init();
    $root = new RootGroup($screen);
    $dialog = new Dialog(Rect::of(5, 2, 35, 10), 'Physically Presented');
    $root->putEvent(Event::command(Cmd::Cancel));

    expect($root->execView($dialog))->toBe(Cmd::Cancel)
        ->and($driver->output())->toContain('Physically')
        ->and($driver->output())->toContain('Presented');
});
