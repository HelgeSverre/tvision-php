<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide12.php';

test('Guide12 runs its dialog modally and returns its closing command', function (): void {
    $app = new Guide12App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();

    $app->putEvent(Event::key(Key::Esc));

    expect($app->runDemoDialog())->toBe(Cmd::Cancel)
        ->and($app->desktopForTest()?->subviews())->toHaveCount(1); // only Background remains
});
