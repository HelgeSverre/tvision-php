<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MessageEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;

test('a printable key carries its character', function (): void {
    $k = new KeyDownEvent(keyCode: 0x1E61, char: 'a');

    expect($k->char)->toBe('a')
        ->and($k->keyCode)->toBe(0x1E61)
        ->and($k->modifiers)->toBe(0);
});

test('a special key has no char and matches a Key via is()', function (): void {
    $k = new KeyDownEvent(keyCode: Key::AltX->value);

    expect($k->char)->toBe('')
        ->and($k->is(Key::AltX))->toBeTrue()
        ->and($k->is(Key::Enter))->toBeFalse();
});

test('mouse event carries position and buttons', function (): void {
    $m = new MouseEvent(where: new Point(10, 4), buttons: 1, doubleClick: true);

    expect($m->where)->toEqual(new Point(10, 4))
        ->and($m->buttons)->toBe(1)
        ->and($m->doubleClick)->toBeTrue()
        ->and($m->wheel)->toBe(0);
});

test('message event carries a command and optional info', function (): void {
    $msg = new MessageEvent(command: Cmd::Quit, info: ['x' => 1]);

    expect($msg->command)->toBe(Cmd::Quit)
        ->and($msg->info)->toBe(['x' => 1]);
});
