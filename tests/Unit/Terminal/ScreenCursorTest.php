<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Terminal\Screen;

test('screen presents a requested cursor after painting and hides it when cleared', function (): void {
    $driver = new HeadlessDriver(8, 3);
    $screen = new Screen($driver);
    $screen->init();

    $screen->setCursor(new Point(4, 1));
    $screen->flush();

    expect($driver->takeOutput())->toContain("\e[2;5H\e[?25h");

    // A second unchanged frame does not emit redundant terminal cursor state.
    $screen->flush();
    expect($driver->takeOutput())->toBe('');

    $screen->setCursor(null);
    $screen->flush();
    expect($driver->takeOutput())->toContain("\e[?25l");
});

test('screen rejects an out-of-bounds cursor request by keeping the cursor hidden', function (): void {
    $driver = new HeadlessDriver(2, 2);
    $screen = new Screen($driver);
    $screen->init();

    $screen->setCursor(new Point(2, 0));
    $screen->flush();

    expect($driver->takeOutput())->toBe('');
});

test('screen reconciles cursor presentation after resize and hides an invalidated cursor', function (): void {
    $driver = new HeadlessDriver(10, 10);
    $screen = new Screen($driver);
    $screen->init();
    $screen->setCursor(new Point(9, 9));
    $screen->flush();
    $driver->takeOutput();

    $driver->resizeTo(2, 2);
    $screen->pollEvents(0);
    $screen->flush();

    expect($driver->takeOutput())->toContain("\e[?25l");
});
