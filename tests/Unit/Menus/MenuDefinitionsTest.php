<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Menus\Menu;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\SubMenu;

test('a MenuItem carries name, command, key and help', function (): void {
    $item = new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit the program');

    expect($item->name)->toBe('E~x~it')
        ->and($item->command)->toBe(Cmd::Quit)
        ->and($item->key)->toBe(Key::AltX)
        ->and($item->help)->toBe('Exit the program')
        ->and($item->subMenu)->toBeNull();
});

test('MenuItem::separator creates a non-command separator entry', function (): void {
    $item = MenuItem::separator();

    expect($item->name)->toBe('')
        ->and($item->command)->toBe(0)
        ->and($item->key)->toBeNull()
        ->and($item->subMenu)->toBeNull();
});

test('SubMenu->items() is fluent and collects items into a Menu', function (): void {
    $sub = new SubMenu('~F~ile', Key::AltF)->items(
        new MenuItem('~O~pen', Cmd::FirstUser, Key::F3, 'F3'),
        new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
    );

    expect($sub)->toBeInstanceOf(SubMenu::class)
        ->and($sub->name)->toBe('~F~ile')
        ->and($sub->key)->toBe(Key::AltF)
        ->and($sub->menu())->toBeInstanceOf(Menu::class)
        ->and($sub->menu()->items())->toHaveCount(2)
        ->and($sub->menu()->items()[1]->command)->toBe(Cmd::Quit);
});

test('a Menu built from several SubMenus exposes its top-level items as MenuItems', function (): void {
    $file = new SubMenu('~F~ile', Key::AltF)->items(
        new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
    );
    $window = new SubMenu('~W~indow', Key::AltW)->items(
        new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
    );

    $menu = Menu::of($file, $window);

    expect($menu->items())->toHaveCount(2)
        ->and($menu->items()[0]->name)->toBe('~F~ile')
        ->and($menu->items()[0]->key)->toBe(Key::AltF)
        ->and($menu->items()[0]->subMenu)->toBeInstanceOf(Menu::class)
        ->and($menu->items()[1]->name)->toBe('~W~indow');
});

test('a StatusItem carries text, key and command', function (): void {
    $item = new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit);

    expect($item->text)->toBe('~Alt-X~ Exit')
        ->and($item->key)->toBe(Key::AltX)
        ->and($item->command)->toBe(Cmd::Quit);
});

test('StatusDef->items() is fluent and scopes items to a help-context range', function (): void {
    $def = new StatusDef(0, 0xFFFF);
    $def->items(
        new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
    );

    expect($def)->toBeInstanceOf(StatusDef::class)
        ->and($def->min)->toBe(0)
        ->and($def->max)->toBe(0xFFFF)
        ->and($def->getItems())->toHaveCount(2)
        ->and($def->getItems()[0]->command)->toBe(Cmd::Quit);
});

test('StatusDef::all creates a full-range definition', function (): void {
    $def = StatusDef::all(
        new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
    );

    expect($def->min)->toBe(0)
        ->and($def->max)->toBe(0xFFFF)
        ->and($def->getItems())->toHaveCount(1);
});
