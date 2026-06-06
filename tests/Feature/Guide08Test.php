<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide08.php';

test('Guide08 shows scroll bars on the window', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide08App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    // Vertical arrows present somewhere on the window's right edge.
    expect($joined)->toContain('▲')
        ->and($joined)->toContain('▼');
});

test('Guide08 interior shows the top file lines before scrolling', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide08App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Line 00');
});

test('scrolling the interior down reveals later lines', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide08App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();

    // Drive the interior's vertical scroll directly to offset 10.
    $app->scrollTopWindowTo(0, 10);
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    expect($joined)->toContain('Line 10')
        ->and($joined)->not->toContain('Line 00');
});
