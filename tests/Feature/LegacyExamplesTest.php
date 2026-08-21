<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Examples\Legacy\BackgroundApp;
use HelgeSverre\TurboVision\Examples\Legacy\LifeApp;
use HelgeSverre\TurboVision\Examples\Legacy\ListBoxApp;
use HelgeSverre\TurboVision\Examples\Legacy\LoadApp;
use HelgeSverre\TurboVision\Examples\Legacy\NoMenusApp;
use HelgeSverre\TurboVision\Examples\Legacy\SplashApp;
use HelgeSverre\TurboVision\Examples\Legacy\TerminalApp;
use HelgeSverre\TurboVision\Examples\Legacy\TveditApp;
use HelgeSverre\TurboVision\Examples\Legacy\ValidatorApp;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Terminal\Screen;

test('legacy background example replaces the reusable desktop pattern', function (): void {
    $app = new BackgroundApp(new Screen(new HeadlessDriver(32, 10)));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('????');
});

test('legacy terminal example retains and renders scrollback through the reusable terminal view', function (): void {
    $app = new TerminalApp(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $app->writeDemoLog();
    $app->drawAndFlushForTest();

    expect($app->terminalForTest()->scrollback())->toContain('worker: ready')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('boot: ok');
});

test('legacy life example advances a custom board inside framework windowing', function (): void {
    $app = new LifeApp(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $app->boardForTest()->advance();
    $app->drawAndFlushForTest();

    expect($app->boardForTest()->generation())->toBe(1)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Conway life');
});

test('legacy no-menus example composes a data-bound dialog exclusively from reusable controls', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new NoMenusApp(new Screen($driver));
    $order = $app->createOrderDialog();
    $app->putEvent(Event::command(Cmd::Ok));
    $app->putEvent(Event::command(Cmd::Cancel));

    expect($order->getData())->toBe([1, 2, 'By box'])
        ->and($app->welcomeDialogForTest()->frameTitle())->toBe('Information')
        ->and($app->runOrder())->toBe(0)
        ->and($app->lastDialogResult)->toBe(Cmd::Cancel)
        ->and($driver->isInitialised())->toBeFalse();
});

test('legacy splash example displays a centered startup dialog', function (): void {
    $app = new SplashApp(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    expect(($app->splashForTest()->options & \HelgeSverre\TurboVision\Views\State::Centered) === \HelgeSverre\TurboVision\Views\State::Centered)->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Turbo Vision Demo');
});

test('legacy validator example rejects incomplete values and accepts a complete data record', function (): void {
    $app = new ValidatorApp(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $dialog = $app->createValidatorDialog();
    $app->putEvent(Event::command(Cmd::Cancel));
    $app->handleEvent(Event::command(ValidatorApp::OpenDialog));

    expect($dialog->valid(Cmd::Ok))->toBeFalse()
        ->and($app->lastDialogResult)->toBe(Cmd::Cancel);

    $dialog->setData([1, 12, 2024, 'AB', '12345-123', '12/12/2024']);

    expect($dialog->valid(Cmd::Ok))->toBeTrue();
});

test('legacy listbox example binds a string collection and its selection to a dialog', function (): void {
    $app = new ListBoxApp(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $dialog = $app->createListDialog();
    $data = $dialog->getData();
    $app->putEvent(Event::command(Cmd::Cancel));
    $app->handleEvent(Event::command(ListBoxApp::OpenDialog));

    expect($data)->toBe([['collection' => ListBoxApp::ANIMALS, 'selection' => 2]])
        ->and($app->lastDialogResult)->toBe(Cmd::Cancel);
});

test('legacy load example persists its reusable resource data through an explicit allow-list', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'tvision-legacy-load-');
    assert(is_string($path));
    unlink($path);

    try {
        LoadApp::saveLoads($path, ['0.12', '0.34', '0.56']);

        expect(LoadApp::loadLoads($path))->toBe(['0.12', '0.34', '0.56']);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('legacy tvedit smoke example composes the reusable editor window and file dialog', function (): void {
    $app = new TveditApp(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $editor = $app->editorWindowForTest()->editor;
    $editor->insertText("hello Turbo Vision\n");
    $app->drawAndFlushForTest();

    expect($editor->text())->toBe("hello Turbo Vision\n")
        ->and($app->editorWindowForTest()->frameTitle())->toBe('Untitled')
        ->and($app->createOpenDialog('*.php', __DIR__)->wildCard)->toBe('*.php')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('hello Turbo Vision');
});
