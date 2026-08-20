<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Examples\OpenCode\OpenCodeApp;
use HelgeSverre\TurboVision\Examples\OpenCode\OpenCodeDemoState;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/opencode.php';

function openCodeDemoApp(int $width = 120, int $height = 34): OpenCodeApp
{
    $app = new OpenCodeApp(new Screen(new HeadlessDriver($width, $height)));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    return $app;
}

function openCodeKey(OpenCodeApp $app, Key $key): void
{
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent($key->value)));
}

function openCodeChar(OpenCodeApp $app, string $char): void
{
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord($char), $char)));
}

function openCodeControl(OpenCodeApp $app, int $controlCode): void
{
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent($controlCode)));
}

test('OpenCode home reproduces the logo centered composer metadata and footer', function (): void {
    $app = openCodeDemoApp(100, 30);
    $rows = $app->backRowsForTest();
    $text = implode("\n", $rows);

    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Home)
        ->and($text)->toContain('█▀▀█ █▀▀█')
        ->and($text)->toContain('█  █ █  █ █▀▀▀ █  █')
        ->and($text)->toContain('Ask anything... "Fix a TODO in the codebase"')
        ->and($text)->toContain('Hy3 (8x usage)')
        ->and($text)->toContain('OpenCode Go')
        ->and($text)->toContain('~/code/tvision-php:main')
        ->and($text)->toContain('shift+tab agents')
        ->and($text)->toContain('MCP server failed: asana')
        ->and($text)->toContain('⊙ 1 MCP failed /mcps')
        ->and($text)->toContain('0.0.0-beta-17728');
});

test('OpenCode home uses Shift-Tab to switch the mock agent without leaving the route', function (): void {
    $driver = new HeadlessDriver(100, 30);
    $app = new OpenCodeApp(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();
    $driver->takeOutput();
    openCodeKey($app, Key::ShiftTab);

    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Home)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Plan')
        ->and($driver->output())->toContain("\e[0;33;100m");
});

test('OpenCode session renders user message assistant transcript tools and prompt', function (): void {
    $app = openCodeDemoApp();
    openCodeKey($app, Key::Enter);
    $text = implode("\n", $app->backRowsForTest());

    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Session)
        ->and($text)->toContain('# Rebuild the OpenCode demo')
        ->and($text)->toContain('Make the demo actually look')
        ->and($text)->toContain('✱ Grep')
        ->and($text)->toContain('→ Read')
        ->and($text)->toContain('▣  Build');
});

test('OpenCode working state shows active tool output and interrupt affordance', function (): void {
    $app = openCodeDemoApp();
    openCodeKey($app, Key::F3);
    openCodeKey($app, Key::F3);
    $text = implode("\n", $app->backRowsForTest());

    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Working)
        ->and($text)->toContain('✱ Edit')
        ->and($text)->toContain('Running tests')
        ->and($text)->toContain('esc interrupt');
});

test('OpenCode model picker is categorized navigable and dismissible', function (): void {
    $app = openCodeDemoApp();
    openCodeKey($app, Key::F2);
    $text = implode("\n", $app->backRowsForTest());

    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::ModelPicker)
        ->and($text)->toContain('Select model')
        ->and($text)->toContain('Recent')
        ->and($text)->toContain('OpenCode Zen')
        ->and($text)->toContain('Anthropic')
        ->and($text)->toContain('Connect provider');

    openCodeKey($app, Key::Down);
    openCodeKey($app, Key::Enter);
    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Home)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Big Pickle');
});

test('OpenCode Ctrl-P command palette navigates and executes commands', function (): void {
    $app = openCodeDemoApp();
    openCodeControl($app, 0x10);

    expect(implode("\n", $app->backRowsForTest()))->toContain('Commands')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('New session');

    openCodeKey($app, Key::Down);
    openCodeKey($app, Key::Enter);
    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Working);
});

test('OpenCode Ctrl-T variant picker persists the selected variant', function (): void {
    $app = openCodeDemoApp();
    openCodeControl($app, 0x14);

    expect(implode("\n", $app->backRowsForTest()))->toContain('Select variant');

    openCodeKey($app, Key::Down);
    openCodeKey($app, Key::Enter);
    expect(implode("\n", $app->backRowsForTest()))->toContain('· max');
});

test('OpenCode slash mcps command opens and closes MCP details', function (): void {
    $app = openCodeDemoApp();
    foreach (str_split('/mcps') as $char) {
        openCodeChar($app, $char);
    }
    openCodeKey($app, Key::Enter);

    expect(implode("\n", $app->backRowsForTest()))->toContain('MCP servers')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Connection closed while starting');

    openCodeKey($app, Key::Esc);
    expect(implode("\n", $app->backRowsForTest()))->not->toContain('Connection closed while starting');
});

test('OpenCode model picker returns to the screen that opened it', function (): void {
    $app = openCodeDemoApp();
    openCodeKey($app, Key::Enter);
    openCodeKey($app, Key::F2);
    openCodeKey($app, Key::Esc);

    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Session);
});

test('OpenCode permission and error screens layer over the session', function (): void {
    $app = openCodeDemoApp();
    foreach (range(1, 4) as $_) {
        openCodeKey($app, Key::F3);
    }
    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Permission)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Permission required');

    openCodeKey($app, Key::F3);
    expect($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Error)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Provider connection closed');
});

test('OpenCode demo accepts edits and submits the home prompt into a session', function (): void {
    $app = openCodeDemoApp();
    foreach (str_split('query42') as $char) {
        openCodeChar($app, $char);
    }
    openCodeKey($app, Key::Backspace);

    expect($app->openCodeView()->prompt())->toBe('query4');

    openCodeKey($app, Key::Enter);
    expect($app->openCodeView()->prompt())->toBe('')
        ->and($app->openCodeView()->demoState())->toBe(OpenCodeDemoState::Session);
});

test('OpenCode slash quit command exits the real application loop cleanly', function (): void {
    $driver = new HeadlessDriver(100, 30);
    $app = new OpenCodeApp(new Screen($driver));
    $driver->feedInput("/quit\r");

    expect($app->run())->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});

test('OpenCode demo reflows to its compact minimum-size notice', function (): void {
    $driver = new HeadlessDriver(100, 30);
    $app = new OpenCodeApp(new Screen($driver));
    $app->bootForTest();

    $driver->resizeTo(60, 16);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();

    expect($app->openCodeView()->getBounds()->width())->toBe(60)
        ->and($app->openCodeView()->getBounds()->height())->toBe(16)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Resize to at least 68 × 24');
});
