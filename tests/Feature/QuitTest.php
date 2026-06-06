<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Terminal\Screen;

/**
 * Ctrl-C is a universal escape hatch. In raw mode the terminal does not raise
 * SIGINT (Ctrl-C arrives as the byte 0x03), so an app that only quits on its own
 * command would be unquittable. The program treats Ctrl-C as quit so a user can
 * always get out (and the terminal is restored on the resulting shutdown).
 */
test('Ctrl-C quits the application', function (): void {
    $app = new class(new Screen(new HeadlessDriver(80, 25))) extends Application {};
    $app->bootForTest();

    expect($app->ended())->toBeFalse();

    $app->handleEvent(Event::keyDown(new KeyDownEvent(0x03))); // Ctrl-C

    expect($app->ended())->toBeTrue();
});

test('Ctrl-C drives the real run loop to a clean exit', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new class(new Screen($driver)) extends Application {};

    $driver->feedInput("\x03"); // Ctrl-C
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
