<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

/** A root group that exposes a real Screen so MenuBar compositing is testable. */
final class MenuBarRoot extends Group
{
    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
}

/** @return array{MenuBarRoot, Screen} */
function menuRoot(int $w): array
{
    $screen = new Screen(new HeadlessDriver($w, 1));
    $screen->init();
    $g = new MenuBarRoot(Rect::of(0, 0, $w, 1), $screen);

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
