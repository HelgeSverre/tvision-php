<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide03.php';

test('Guide03 renders the File and Window menus on row 0', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide03App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    expect($rows[0])->toContain('File')
        ->and($rows[0])->toContain('Window');
});

test('Guide03 renders the status hints on the last row', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide03App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $last = $app->backRowsForTest()[24];

    expect($last)->toContain('Alt-X Exit')
        ->and($last)->toContain('Close');
});

test('Guide03 quits cleanly on Alt-X', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide03App(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
