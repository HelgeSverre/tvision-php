<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide14.php';

test('Guide14 composes cheese checkboxes and consistency radio buttons', function (): void {
    $app = new Guide14App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    expect($app->openModalDialogForTest(Cmd::Cancel))->toBe(Cmd::Cancel);

    expect($app->cheesesForTest()?->items)->toBe(['~H~varti', '~T~ilset', '~J~arlsberg'])
        ->and($app->consistencyForTest()?->items)->toBe(['~S~olid', '~R~unny', '~M~elted']);
});
