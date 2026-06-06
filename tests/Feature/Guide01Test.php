<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide01.php';

test('Guide01 renders the default menu bar and status line headless', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide01App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    expect($rows)->toHaveCount(25)
        ->and($rows[0])->toContain('File')   // default menu bar
        ->and($rows[24])->toContain('Exit')  // default status line
        ->and($rows[12])->toContain('▓');     // desktop backdrop
});

test('Guide01 quits cleanly on Alt-X', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide01App(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
