<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide02.php';

test('Guide02 renders both status hints on the last row', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide02App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $last = $app->backRowsForTest()[24];

    expect($last)->toContain('Alt-X Exit')
        ->and($last)->toContain('Close');
});

test('Guide02 quits cleanly on Alt-X', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide02App(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
