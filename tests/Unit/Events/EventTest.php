<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MessageEvent;

test('nothing() is the empty event', function (): void {
    $e = Event::nothing();

    expect($e->what)->toBe(EventType::Nothing)
        ->and($e->isNothing())->toBeTrue()
        ->and($e->asKey())->toBeNull()
        ->and($e->asMessage())->toBeNull();
});

test('keyDown() wraps a key payload and exposes it via asKey()', function (): void {
    $e = Event::keyDown(new KeyDownEvent(Key::AltX->value));

    expect($e->what)->toBe(EventType::KeyDown)
        ->and($e->asKey())->toBeInstanceOf(KeyDownEvent::class)
        ->and($e->asKey()?->is(Key::AltX))->toBeTrue()
        ->and($e->asMessage())->toBeNull();
});

test('command() wraps a message payload', function (): void {
    $e = Event::command(Cmd::Quit);

    expect($e->what)->toBe(EventType::Command)
        ->and($e->asMessage())->toBeInstanceOf(MessageEvent::class)
        ->and($e->asMessage()?->command)->toBe(Cmd::Quit);
});

test('clear() consumes the event so it stops propagating', function (): void {
    $e = Event::command(Cmd::Quit);
    $e->clear();

    expect($e->what)->toBe(EventType::Nothing)
        ->and($e->isNothing())->toBeTrue()
        ->and($e->asMessage())->toBeNull();
});

test('isCommand() recognises a specific command, isKey() a specific key', function (): void {
    expect(Event::command(Cmd::Quit)->isCommand(Cmd::Quit))->toBeTrue()
        ->and(Event::command(Cmd::Quit)->isCommand(Cmd::Cancel))->toBeFalse()
        ->and(Event::keyDown(new KeyDownEvent(Key::Enter->value))->isKey(Key::Enter))->toBeTrue()
        ->and(Event::keyDown(new KeyDownEvent(Key::Enter->value))->isKey(Key::Esc))->toBeFalse();
});
