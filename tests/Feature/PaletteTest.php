<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

/**
 * Regression test for the monochrome bug: the per-view palettes are remap tables
 * that must resolve into the application root palette. Without it, View::mapColor()
 * dead-ends at the 0x07 fallback and the whole UI is monochrome.
 */
test('the desktop renders with the modern dark palette', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new class(new Screen($driver)) extends Application {};

    $app->bootForTest();
    $app->drawAndFlushForTest();

    // A cell well inside the desktop backdrop.
    $cell = $app->screen()?->back()->at(10, 10);

    expect($cell?->char)->toBe('░')      // sparse light pattern
        ->and($cell?->attr)->toBe(0x08); // dark-gray texture on black
});

test('the application root palette resolves index 1 to the dark desktop colour', function (): void {
    $app = new class(new Screen(new HeadlessDriver(80, 25))) extends Application {};

    // mapColor(1) at the program root must hit the application palette.
    expect($app->mapColor(1))->toBe(0x08);
});
