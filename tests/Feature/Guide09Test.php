<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide09.php';

test('Guide09 renders two scroller panes with their own scroll bars', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide09App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    // Both panes show file content; at least two vertical bars (4 arrow glyphs total).
    expect(substr_count($joined, '▲'))->toBeGreaterThanOrEqual(2)
        ->and($joined)->toContain('Line 00');
});

test('Guide09 panes scroll independently', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide09App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();

    $app->scrollLeftPaneTo(0, 5);
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Line 05');
});
