<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Window;

/** An app that opens one grow-all window we can watch reflow. */
final class ResizeApp extends Application
{
    public ?Window $window = null;

    public function openDemoWindow(): void
    {
        $desk = $this->desktopForTest();
        $w = new Window(Rect::of(0, 0, 20, 6), 'Demo', 1);
        $w->growMode = \HelgeSverre\TurboVision\Views\State::GrowHiX | \HelgeSverre\TurboVision\Views\State::GrowHiY;
        $desk?->insert($w);
        $this->window = $w;
    }
}

test('resizing the terminal reflows a grow-all window', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new ResizeApp(new Screen($driver));
    $app->bootForTest();
    $app->openDemoWindow();

    $before = $app->window?->getBounds();
    expect($before)->toEqual(Rect::of(0, 0, 20, 6));

    // Shrink the terminal and pump one resize cycle.
    $driver->resizeTo(60, 20);
    $app->pumpResizeForTest();

    // GrowHiX|GrowHiY: high corner follows the (delta) of the desktop change.
    $after = $app->window?->getBounds();
    expect($after?->width())->toBeLessThan(20)  // narrower after shrink
        ->and($after?->height())->toBeLessThanOrEqual(6);
});

test('the back buffer is resized to the new terminal size', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new ResizeApp(new Screen($driver));
    $app->bootForTest();

    $driver->resizeTo(40, 12);
    $app->pumpResizeForTest();

    expect($app->backRowsForTest())->toHaveCount(12)
        ->and(mb_strlen($app->backRowsForTest()[0]))->toBe(40);
});
