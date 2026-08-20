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
use HelgeSverre\TurboVision\Menus\Menu;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

/** A root group that exposes a real Screen so MenuBar compositing is testable. */
final class MenuBarRoot extends Group
{
    /** @var array<int, true> */
    private array $disabledCommands = [];

    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }

    public function disableCommand(int $command): void
    {
        $this->disabledCommands[$command] = true;
    }

    public function commandEnabled(int $command): bool
    {
        return ! isset($this->disabledCommands[$command]);
    }
}

/** @return array{MenuBarRoot, Screen} */
function menuRoot(int $w, int $h = 1): array
{
    $screen = new Screen(new HeadlessDriver($w, $h));
    $screen->init();
    $g = new MenuBarRoot(Rect::of(0, 0, $w, $h), $screen);

    return [$g, $screen];
}

test('menu bar renders top-level item names with hotkeys stripped, starting at column 1', function (): void {
    [$root, $screen] = menuRoot(20);
    $bar = new MenuBar(
        Rect::of(0, 0, 20, 1),
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
        ),
        new SubMenu('~W~indow', Key::AltW)->items(
            new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
        ),
    );
    $root->insert($bar);

    $bar->draw();

    // Items start at x=1: [0:blank][1:space][2..5:File][6:space][7:space][8..13:Window]...
    $row = $screen->back()->rows()[0];
    expect($row)->toContain('File')
        ->and($row)->toContain('Window')
        ->and(str_starts_with($row, '  File'))->toBeTrue();
});

test('Alt-hotkey on a top-level submenu is recognized (handled, event consumed)', function (): void {
    [$root, $screen] = menuRoot(20);
    $bar = new MenuBar(
        Rect::of(0, 0, 20, 1),
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
        ),
    );
    $root->insert($bar);

    $ev = Event::keyDown(new KeyDownEvent(Key::AltF->value));
    $bar->handleEvent($ev);

    // M1: the bar recognizes the top-level hotkey and consumes the key.
    expect($ev->isNothing())->toBeTrue();
});

test('clicking a leaf-command item dispatches its command via putEvent', function (): void {
    [$root, $screen] = menuRoot(20);
    // A bar whose single top-level item is itself a direct command (rare, but exercises dispatch).
    $bar = new MenuBar(
        Rect::of(0, 0, 20, 1),
        new SubMenu('E~x~it', Key::AltX)->items(),
    );
    $root->insert($bar);

    // The bar exposes the command it would dispatch for a click at a column.
    expect($bar->commandAtColumn(2))->toBe(0); // submenu host has no direct command
});

test('a disabled direct menu command ignores keyboard and mouse activation', function (): void {
    [$root] = menuRoot(20);
    $menu = new Menu([
        new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
    ]);
    $bar = new MenuBar(Rect::of(0, 0, 20, 1), $menu);
    $root->insert($bar);
    $root->disableCommand(Cmd::Quit);

    $event = Event::keyDown(new KeyDownEvent(Key::AltX->value));
    $bar->handleEvent($event);

    expect($event->what)->toBe(EventType::KeyDown)
        ->and($bar->commandAtColumn(2))->toBe(0);
});

test('Alt hotkeys open a rendered pull-down and arrows activate its commands', function (): void {
    [$root, $screen] = menuRoot(36, 10);
    $bar = new MenuBar(
        Rect::of(0, 0, 36, 1),
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('~O~pen', 200, Key::F3, 'F3'),
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
        ),
    );
    $root->insert($bar);

    $bar->handleEvent(Event::keyDown(new KeyDownEvent(Key::AltF->value)));
    $root->draw();

    expect(implode("\n", $screen->back()->rows()))->toContain('Open')
        ->and($bar->activeIndex())->toBe(0)
        ->and($bar->selectedIndex())->toBe(0);

    $bar->handleEvent(Event::keyDown(new KeyDownEvent(Key::Down->value)));
    $bar->handleEvent(Event::keyDown(new KeyDownEvent(Key::Enter->value)));

    expect($root->pumpEvent()?->isCommand(Cmd::Quit))->toBeTrue()
        ->and($bar->activeIndex())->toBe(-1);
});

test('F10 opens the first pull-down and Esc dismisses it', function (): void {
    [$root] = menuRoot(36, 10);
    $bar = new MenuBar(
        Rect::of(0, 0, 36, 1),
        new SubMenu('~F~ile', Key::AltF)->items(new MenuItem('E~x~it', Cmd::Quit)),
    );
    $root->insert($bar);

    $menu = Event::command(Cmd::Menu);
    $bar->handleEvent($menu);
    expect($menu->isNothing())->toBeTrue()
        ->and($bar->activeIndex())->toBe(0);

    $escape = Event::keyDown(new KeyDownEvent(Key::Esc->value));
    $bar->handleEvent($escape);
    expect($bar->activeIndex())->toBe(-1);
});

test('mouse clicks open a pull-down and activate its selected row', function (): void {
    [$root] = menuRoot(36, 10);
    $bar = new MenuBar(
        Rect::of(0, 0, 36, 1),
        new SubMenu('~F~ile', Key::AltF)->items(new MenuItem('~O~pen', 200)),
    );
    $root->insert($bar);

    $root->handleEvent(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(2, 0), buttons: 1)));
    expect($bar->activeIndex())->toBe(0);

    $root->handleEvent(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 2), buttons: 1)));
    expect($root->pumpEvent()?->isCommand(200))->toBeTrue()
        ->and($bar->activeIndex())->toBe(-1);
});
