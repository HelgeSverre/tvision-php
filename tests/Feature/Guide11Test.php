<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide11.php';

test('Guide11 inserts a non-modal dialog from the Window menu command', function (): void {
    $app = new Guide11App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();

    $event = Event::command(CM_G11_NEW_DIALOG);
    $app->handleEvent($event);
    $app->drawAndFlushForTest();
    $dialog = $app->desktopForTest()?->current();

    if (! $dialog instanceof Dialog) {
        throw new RuntimeException('Window command did not select the new dialog.');
    }

    expect($dialog->getState(\HelgeSverre\TurboVision\Views\State::Modal))->toBeFalse()
        ->and($event->isNothing())->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Demo Dialog');
});
