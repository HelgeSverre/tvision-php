<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide06.php';

test('Guide06 interior shows the first lines of the file', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide06App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    expect($joined)->toContain('Line 00')
        ->and($joined)->toContain('Line 01');
});

test('Guide06 does not show lines beyond the window height', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide06App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    // Window is 7 tall (5 interior rows) -> Line 30 is far off-screen.
    expect(implode("\n", $app->backRowsForTest()))->not->toContain('Line 30');
});
