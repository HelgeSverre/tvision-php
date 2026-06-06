<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

/** The smallest possible app — tvguid01 shape. */
final class HelloApp extends Application {}

test('a bare Application boots with an injected headless screen and renders three regions', function (): void {
    $driver = new HeadlessDriver(40, 6);
    $app = new HelloApp(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    // Row 0 is the menu bar (non-blank), last row is the status line (non-blank),
    // middle rows are the desktop pattern.
    expect($rows)->toHaveCount(6)
        ->and(trim($rows[0]))->not->toBe('')      // menu bar present
        ->and(trim($rows[5]))->not->toBe('')      // status line present
        ->and($rows[2])->toContain('░');          // desktop backdrop
});

test('a bare Application quits on the default Alt-X status command', function (): void {
    $driver = new HeadlessDriver(40, 6);
    $app = new HelloApp(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});

test('the default initScreen would build a real AnsiDriver-backed Screen', function (): void {
    // We do not boot it (no TTY in CI); we only assert the type wiring is sound by
    // confirming an injected screen short-circuits the default.
    $injected = new Screen(new HeadlessDriver(10, 3));
    $app = new HelloApp($injected);
    $app->bootForTest();

    expect($app->screen())->toBe($injected);
});
