<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide05.php';

test('Guide05 window interior renders Hello World!', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide05App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Hello World!');
});

test('Guide05 interior text sits inside the window frame', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide05App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();
    // The "Hello World!" line is not on the same row as the title (row of the frame top).
    $helloRow = null;
    foreach ($rows as $y => $row) {
        if (str_contains($row, 'Hello World!')) {
            $helloRow = $y;
            break;
        }
    }
    expect($helloRow)->not->toBeNull();
});
