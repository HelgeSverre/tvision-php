<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide15.php';

test('Guide15 adds a 128-byte delivery instructions input line', function (): void {
    $app = new Guide15App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    expect($app->openModalDialogForTest(Cmd::Cancel))->toBe(Cmd::Cancel);

    $input = $app->deliveryInstructionsForTest();
    $input?->setText('Leave at reception');

    expect($input?->maxLen)->toBe(127)
        ->and($input?->text())->toBe('Leave at reception');
});
