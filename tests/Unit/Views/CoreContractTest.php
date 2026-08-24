<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('plain views expose the original focused-event default mask', function (): void {
    $view = new View(Rect::of(0, 0, 1, 1));

    expect($view->eventMask)->toBe(EventMask::MouseDown | EventMask::Keyboard | EventMask::Command);
});

test('groups center opted-in views before inserting them', function (): void {
    $group = new Group(Rect::of(0, 0, 20, 10));
    $dialog = new View(Rect::of(99, 88, 105, 92));
    $dialog->options = State::Centered;

    $group->insert($dialog);

    expect($dialog->getBounds())->toEqual(Rect::of(7, 3, 13, 7));
});

test('groups propagate active and dragging state through nested composites', function (): void {
    $root = new Group(Rect::of(0, 0, 10, 2));
    $nested = new Group(Rect::of(0, 0, 5, 2));
    $leaf = new View(Rect::of(0, 0, 1, 1));
    $nested->insert($leaf);
    $root->insert($nested);

    $root->setState(State::Active, true);
    $root->setState(State::Dragging, true);

    expect($nested->getState(State::Active))->toBeTrue()
        ->and($leaf->getState(State::Active))->toBeTrue()
        ->and($nested->getState(State::Dragging))->toBeTrue()
        ->and($leaf->getState(State::Dragging))->toBeTrue();

    $root->setState(State::Active | State::Dragging, false);

    expect($nested->getState(State::Active | State::Dragging))->toBeFalse()
        ->and($leaf->getState(State::Active | State::Dragging))->toBeFalse();
});

test('a rear view direct redraw never paints over higher-Z siblings, including nested owners', function (): void {
    $screen = new Screen(new HeadlessDriver(12, 1));
    $screen->init();
    $root = new class(Rect::of(0, 0, 12, 1), $screen) extends Group {
        public function __construct(Rect $bounds, private readonly Screen $rootScreen)
        {
            parent::__construct($bounds);
        }

        public function screen(): Screen
        {
            return $this->rootScreen;
        }
    };
    $base = new Background(Rect::of(0, 0, 12, 1), '░');
    $rearGroup = new Group(Rect::of(1, 0, 9, 1));
    $rear = new Background(Rect::of(0, 0, 8, 1), 'A');
    $front = new Background(Rect::of(4, 0, 10, 1), 'B');
    $rearGroup->insert($rear);
    $root->insert($base);
    $root->insert($rearGroup);
    $root->insert($front);

    $root->draw();
    expect($screen->back()->rows()[0])->toBe('░AAABBBBBB░░');

    // A late direct paint from the nested rear view must preserve front content.
    $rear->drawView();

    expect($screen->back()->rows()[0])->toBe('░AAABBBBBB░░');
});

test('transparent groups occlude only their opaque descendants', function (): void {
    $screen = new Screen(new HeadlessDriver(12, 1));
    $screen->init();
    $root = new class(Rect::of(0, 0, 12, 1), $screen) extends Group {
        public function __construct(Rect $bounds, private readonly Screen $rootScreen)
        {
            parent::__construct($bounds);
        }

        public function screen(): Screen
        {
            return $this->rootScreen;
        }
    };
    $rear = new Background(Rect::of(0, 0, 12, 1), 'A');
    $overlay = new class(Rect::of(0, 0, 12, 1)) extends Group {
        public function isOpaque(): bool
        {
            return false;
        }
    };
    $front = new Background(Rect::of(4, 0, 8, 1), 'B');
    $overlay->insert($front);
    $root->insert($rear);
    $root->insert($overlay);

    $root->draw();
    expect($screen->back()->rows()[0])->toBe('AAAABBBBAAAA');

    // A direct repaint passes through the transparent region while preserving
    // the opaque child drawn above it.
    $rear->drawView();
    expect($screen->back()->rows()[0])->toBe('AAAABBBBAAAA');
});

test('row occlusion clips consistently to visibility, extents, and caller bounds', function (): void {
    $view = new View(Rect::of(-4, 2, 6, 5));

    expect($view->occlusionIntervals(2, 0, 10))->toBe([[0, 6]])
        ->and($view->occlusionIntervals(4, 3, 5))->toBe([[3, 5]])
        ->and($view->occlusionIntervals(5, 0, 10))->toBe([])
        ->and($view->occlusionIntervals(2, 5, 5))->toBe([]);

    $view->hide();
    expect($view->occlusionIntervals(2, 0, 10))->toBe([]);

    $edge = new View(Rect::of(PHP_INT_MAX - 4, 0, PHP_INT_MAX, 1));
    expect($edge->occlusionIntervals(0, PHP_INT_MAX - 2, PHP_INT_MAX))
        ->toBe([[PHP_INT_MAX - 2, PHP_INT_MAX]]);
});

test('transparent group descendant occlusion is clipped to the group row interval', function (): void {
    $root = new Group(Rect::of(0, 0, 12, 4));
    $group = new class(Rect::of(3, 1, 9, 3)) extends Group {
        public function isOpaque(): bool
        {
            return false;
        }
    };
    $left = new View(Rect::of(-2, 0, 5, 2));
    $right = new View(Rect::of(4, 0, 10, 2));
    $group->insert($left);
    $group->insert($right);
    $root->insert($group);

    expect($group->occlusionIntervals(1, 0, 12))->toBe([[3, 8], [7, 9]])
        ->and($group->occlusionIntervals(0, 0, 12))->toBe([])
        ->and($group->occlusionIntervals(3, 0, 12))->toBe([]);
});

test('exposed is false when higher-Z siblings cover every visible cell', function (): void {
    $screen = new Screen(new HeadlessDriver(4, 1));
    $screen->init();
    $root = new class(Rect::of(0, 0, 4, 1), $screen) extends Group {
        public function __construct(Rect $bounds, private readonly Screen $rootScreen)
        {
            parent::__construct($bounds);
        }

        public function screen(): Screen
        {
            return $this->rootScreen;
        }
    };
    $rear = new Background(Rect::of(0, 0, 4, 1), 'A');
    $front = new Background(Rect::of(0, 0, 4, 1), 'B');
    $root->insert($rear);
    $root->insert($front);

    expect($rear->exposed())->toBeFalse()
        ->and($front->exposed())->toBeTrue();
});

test('locate restores a moved view old footprint through owner redraw', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 1));
    $screen->init();
    $root = new class(Rect::of(0, 0, 8, 1), $screen) extends Group {
        public function __construct(Rect $bounds, private readonly Screen $rootScreen)
        {
            parent::__construct($bounds);
        }

        public function screen(): Screen
        {
            return $this->rootScreen;
        }
    };
    $base = new Background(Rect::of(0, 0, 8, 1), '.');
    $mover = new Background(Rect::of(1, 0, 3, 1), 'A');
    $root->insert($base);
    $root->insert($mover);
    $root->draw();

    $mover->moveTo(4, 0);

    expect($screen->back()->rows()[0])->toBe('....AA..');
});

test('geometry conveniences clamp size, route mouse containment, and report exposure', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 3));
    $screen->init();
    $root = new class(Rect::of(0, 0, 8, 3), $screen) extends Group {
        public function __construct(Rect $bounds, private readonly Screen $rootScreen)
        {
            parent::__construct($bounds);
        }

        public function screen(): Screen
        {
            return $this->rootScreen;
        }
    };
    $view = new class(Rect::of(1, 1, 2, 2)) extends View {
        public function sizeLimits(): \HelgeSverre\TurboVision\Support\SizeLimits { return new \HelgeSverre\TurboVision\Support\SizeLimits(2, 1, 4, 2); }
    };
    $root->insert($view);

    $view->locate(Rect::of(2, 0, 20, 20));
    expect($view->getBounds())->toEqual(Rect::of(2, 0, 6, 2));
    $view->moveTo(3, 1);
    $view->growTo(1, 9);

    expect($view->getBounds())->toEqual(Rect::of(3, 1, 5, 3))
        ->and($view->containsMouse(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 1)))))->toBeTrue()
        ->and($view->containsMouse(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(5, 1)))))->toBeFalse()
        ->and($view->exposed())->toBeTrue();

    $view->hide();
    expect($view->exposed())->toBeFalse();
});

test('groups honour child event masks before dispatching positional and focused events', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 2));
    $child = new class(Rect::of(0, 0, 4, 1)) extends View {
        public int $handled = 0;

        public function handleEvent(Event $event): void
        {
            $this->handled++;
        }
    };
    $child->eventMask = EventMask::Keyboard;
    $child->options = State::Selectable;
    $group->insert($child);

    $group->handleEvent(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(1, 0), buttons: 1)));
    $group->handleEvent(Event::key(\HelgeSverre\TurboVision\Events\Key::Enter));

    expect($child->handled)->toBe(1);
});

test('group form transfer aggregates participating controls in insertion order', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 2));
    $first = new class(Rect::of(0, 0, 1, 1)) extends View {
        public string $value = 'first';
        public function dataSize(): int { return 5; }
        public function getData(): mixed { return $this->value; }
        public function setData(mixed $data): void
        {
            if (! is_string($data)) {
                throw new InvalidArgumentException('Expected string form data.');
            }
            $this->value = $data;
        }
    };
    $ignored = new View(Rect::of(1, 0, 2, 1));
    $second = new class(Rect::of(2, 0, 3, 1)) extends View {
        public string $value = 'second';
        public function dataSize(): int { return 6; }
        public function getData(): mixed { return $this->value; }
        public function setData(mixed $data): void
        {
            if (! is_string($data)) {
                throw new InvalidArgumentException('Expected string form data.');
            }
            $this->value = $data;
        }
    };
    $group->insert($first);
    $group->insert($ignored);
    $group->insert($second);

    expect($group->dataSize())->toBe(11)
        ->and($group->getData())->toBe(['first', 'second']);

    $group->setData(['one', 'two']);

    expect($first->value)->toBe('one')
        ->and($second->value)->toBe('two');
});

test('group validation checks all controls but focus release validates only opted-in current control', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 2));
    $current = new class(Rect::of(0, 0, 1, 1)) extends View {
        public bool $allowed = false;
        public function valid(int $command): bool { return $this->allowed; }
    };
    $current->options = State::Selectable | State::Validate;
    $other = new class(Rect::of(1, 0, 2, 1)) extends View {
        public function valid(int $command): bool { return false; }
    };
    $group->insert($current);
    $group->insert($other);

    expect($group->valid(Cmd::ReleasedFocus))->toBeFalse()
        ->and($group->valid(Cmd::Ok))->toBeFalse();

    $current->allowed = true;

    expect($group->valid(Cmd::ReleasedFocus))->toBeTrue();
});

test('focus, visibility, help context, and z-order helpers follow owner contracts', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 2));
    $first = new View(Rect::of(0, 0, 1, 1));
    $first->options = State::Selectable;
    $first->helpCtx = 42;
    $second = new View(Rect::of(1, 0, 2, 1));
    $second->options = State::Selectable;
    $group->insert($first);
    $group->insert($second);

    expect($second->focus())->toBeTrue()
        ->and($group->current())->toBe($second)
        ->and($group->getHelpCtx())->toBe(0);

    $first->select();
    $first->makeFirst();
    $subviews = $group->subviews();

    expect($group->current())->toBe($first)
        ->and($group->getHelpCtx())->toBe(42)
        ->and(end($subviews))->toBe($first);

    $first->hide();

    expect($group->current())->toBe($second)
        ->and($first->getState(State::Visible))->toBeFalse();
});

test('a grouped modal owns its completion and validates before execView returns', function (): void {
    $root = new Group(Rect::of(0, 0, 10, 2));
    $dialog = new class(Rect::of(0, 0, 4, 1)) extends Group {
        public int $validations = 0;

        public function handleEvent(Event $event): void
        {
            if ($event->isCommand(Cmd::Ok)) {
                $this->endModal(Cmd::Ok);
            }
        }

        public function valid(int $command): bool
        {
            $this->validations++;

            return true;
        }
    };
    $root->putEvent(Event::command(Cmd::Ok));

    expect($root->execView($dialog))->toBe(Cmd::Ok)
        ->and($dialog->validations)->toBe(1)
        ->and($dialog->owner)->toBeNull()
        ->and($dialog->getState(State::Modal))->toBeFalse();
});
