<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide07.php';

test('Guide07 renders file lines cleanly via per-line DrawBuffer', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide07App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Line 00');
});
