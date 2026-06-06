<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide04.php';

test('Guide04 opens a demo window when the New command runs', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide04App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    // The window frame title appears somewhere on the desktop.
    $joined = implode("\n", $rows);
    expect($joined)->toContain('Demo Window');
});

test('Guide04 window draws a double-line active frame', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide04App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());

    // Active window => double-line corners present.
    expect($joined)->toContain('╔')
        ->and($joined)->toContain('╗')
        ->and($joined)->toContain('╚')
        ->and($joined)->toContain('╝');
});

test('Guide04 closing the window removes it', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide04App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->closeTopWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->not->toContain('Demo Window');
});
