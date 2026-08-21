<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Examples\Workbench\WorkbenchApp;
use HelgeSverre\TurboVision\Examples\Workbench\WorkbenchCommand;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/workbench.php';

function workbenchApp(int $width = 120, int $height = 36): WorkbenchApp
{
    $app = new WorkbenchApp(new Screen(new HeadlessDriver($width, $height)));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    return $app;
}

function workbenchDispatch(WorkbenchApp $app, Event $event): void
{
    $app->dispatchForTest($event);
}

function workbenchKey(WorkbenchApp $app, Key $key): void
{
    workbenchDispatch($app, Event::keyDown(new KeyDownEvent($key->value)));
}

function workbenchDrainOne(WorkbenchApp $app): void
{
    $event = $app->pumpEvent();
    if ($event !== null) {
        workbenchDispatch($app, $event);
    }
}

test('default Workbench showcases menus windows dashboard list and shortcuts', function (): void {
    $app = workbenchApp();
    $text = implode("\n", $app->backRowsForTest());

    expect($text)->toContain('File  Edit  View  Demos  Window  Help')
        ->and($text)->toContain('TURBO WORKBENCH')
        ->and($text)->toContain('Task Board')
        ->and($text)->toContain('Build pipeline')
        ->and($text)->toContain('F10 Menu')
        ->and($app->windowCount())->toBe(2);
});

test('Workbench pull-down menus render and activate commands', function (): void {
    $app = workbenchApp();
    workbenchKey($app, Key::AltF);
    $openMenu = implode("\n", $app->backRowsForTest());

    expect($openMenu)->toContain('New workspace')
        ->and($openMenu)->toContain('Save snapshot')
        // The full-screen menu input shield is transparent: only the compact
        // pull-down may cover the already-rendered application beneath it.
        ->and($openMenu)->toContain('Build pipeline')
        ->and($openMenu)->toContain('Task Board')
        ->and($openMenu)->toContain('F10 Menu');

    workbenchKey($app, Key::Down);
    workbenchKey($app, Key::Enter);
    workbenchDrainOne($app);

    expect($app->windowCount())->toBe(3)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Task Board');
});

test('Workbench new workspace confirmation is modal and resets its windows', function (): void {
    $app = workbenchApp();
    workbenchDispatch($app, Event::command(WorkbenchCommand::OpenActivity));
    expect($app->windowCount())->toBe(3);

    workbenchDispatch($app, Event::command(WorkbenchCommand::NewWorkspace));
    expect($app->dialogOpen())->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Create a fresh workspace?');

    workbenchKey($app, Key::Enter);
    workbenchDrainOne($app);

    expect($app->dialogOpen())->toBeFalse()
        ->and($app->windowCount())->toBe(2);
});

test('Workbench task selection opens a details modal', function (): void {
    $app = workbenchApp();
    workbenchDispatch($app, Event::command(Cmd::Next));
    workbenchKey($app, Key::Enter);
    workbenchDrainOne($app);

    expect($app->dialogOpen())->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Task details')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('real ListViewer');
});

test('Workbench activity viewer palette action and help modal are functional', function (): void {
    $app = workbenchApp();
    workbenchDispatch($app, Event::command(WorkbenchCommand::OpenActivity));
    expect(implode("\n", $app->backRowsForTest()))->toContain('Activity Inspector')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Arrow keys and scrollbars');

    workbenchDispatch($app, Event::command(WorkbenchCommand::CyclePalette));
    workbenchDispatch($app, Event::command(WorkbenchCommand::KeyboardHelp));
    expect($app->dialogOpen())->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Keyboard reference');
});

test('Workbench advertised control-key shortcuts dispatch their actions', function (): void {
    foreach ([
        0x13 => 'Snapshot captured',
        0x10 => 'Command palette',
        0x1A => 'Undo',
        0x19 => 'Redo',
    ] as $keyCode => $dialogTitle) {
        $app = workbenchApp();
        workbenchDispatch($app, Event::keyDown(new KeyDownEvent($keyCode)));
        workbenchDrainOne($app);

        expect($app->dialogOpen())->toBeTrue()
            ->and(implode("\n", $app->backRowsForTest()))->toContain($dialogTitle);
    }
});

test('Workbench reflows safely when the terminal is resized', function (): void {
    $driver = new HeadlessDriver(120, 36);
    $app = new WorkbenchApp(new Screen($driver));
    $app->bootForTest();

    $driver->resizeTo(82, 25);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();

    expect($app->backRowsForTest())->toHaveCount(25)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Turbo Workbench')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('F10 Menu');
});

test('Ctrl-C remains an unconditional escape hatch while a Workbench modal is open', function (): void {
    $app = workbenchApp();
    workbenchDispatch($app, Event::command(WorkbenchCommand::About));
    expect($app->dialogOpen())->toBeTrue();

    workbenchDispatch($app, Event::keyDown(new KeyDownEvent(0x03)));
    expect($app->ended())->toBeTrue();
});

test('Alt-X quit commands remain unconditional while a Workbench modal is open', function (): void {
    $app = workbenchApp();
    workbenchDispatch($app, Event::command(WorkbenchCommand::About));

    workbenchDispatch($app, Event::command(Cmd::Quit));

    expect($app->ended())->toBeTrue();
});
