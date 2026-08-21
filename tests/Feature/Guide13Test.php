<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide13.php';

test('Guide13 composes default OK and normal Cancel buttons in its dialog', function (): void {
    $app = new Guide13App(new Screen(new HeadlessDriver(80, 25)));
    $app->bootForTest();
    $app->putEvent(Event::key(Key::Enter));
    expect($app->runDemoDialog())->toBe(Cmd::Ok);

    $buttons = array_values(array_filter(
        $app->dialogForTest()?->subviews() ?? [],
        static fn (mixed $view): bool => $view instanceof Button,
    ));

    expect($buttons)->toHaveCount(2)
        ->and($buttons[0]->title)->toBe('~O~K')
        ->and($buttons[0]->amDefault)->toBeTrue()
        ->and($buttons[1]->title)->toBe('~C~ancel');
});
