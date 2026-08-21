<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide16.php';

test('Guide16 restores initial dialog data and retains accepted changes', function (): void {
    $app = new Guide16App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $dialog = $app->buildDialogWithDataForTest();

    expect($app->cheesesForTest()?->value)->toBe(1)
        ->and($app->consistencyForTest()?->value)->toBe(2)
        ->and($app->deliveryInstructionsForTest()?->text())->toBe('Phone Mum!');

    $dialog->setData([5, 1, 'Ring the bell']);
    $app->acceptDialogForTest($dialog, Cmd::Ok);

    expect($app->dialogDataForTest())->toBe([5, 1, 'Ring the bell']);
});

test('Guide16 does not retain cancelled dialog changes', function (): void {
    $app = new Guide16App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $dialog = $app->buildDialogWithDataForTest();
    $dialog->setData([0, 0, 'Discard this']);
    $app->acceptDialogForTest($dialog, Cmd::Cancel);

    expect($app->dialogDataForTest())->toBe([1, 2, 'Phone Mum!']);
});
