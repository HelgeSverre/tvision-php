<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide10.php';

test('Guide10 enforces a minimum width via sizeLimits', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide10App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();

    $win = $app->lastWindowForTest();
    [$minW, $minH] = $win->sizeLimits();

    // minWidth = left interior width + 9; with a 26-wide window the left pane is ~13 wide.
    expect($minW)->toBeGreaterThan(16);   // larger than the default 16 minimum
});

test('Guide10 refuses to shrink below the minimum width', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide10App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $win = $app->lastWindowForTest();

    [$minW] = $win->sizeLimits();
    $win->resizeTo(Rect::of(0, 0, 4, 4)); // try to shrink tiny

    expect($win->getBounds()->width())->toBe($minW);
});
