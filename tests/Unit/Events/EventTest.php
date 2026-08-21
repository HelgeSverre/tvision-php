<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Events\MessageEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Point;

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

test('key() creates a named key event with modifiers', function (): void {
    $e = Event::key(Key::Enter, KeyModifier::Ctrl | KeyModifier::Alt);

    expect($e->what)->toBe(EventType::KeyDown)
        ->and($e->asKey()?->is(Key::Enter))->toBeTrue()
        ->and($e->asKey()?->modifiers)->toBe(KeyModifier::Ctrl | KeyModifier::Alt);
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

test('broadcast() carries a command that isCommand() also matches', function (): void {
    $e = Event::broadcast(Cmd::Ok, info: 'ctx');

    expect($e->what)->toBe(EventType::Broadcast)
        ->and($e->what->inMask(EventMask::Message))->toBeTrue()
        ->and($e->asMessage()?->command)->toBe(Cmd::Ok)
        ->and($e->asMessage()?->info)->toBe('ctx')
        ->and($e->isCommand(Cmd::Ok))->toBeTrue()   // isCommand matches Broadcast too
        ->and($e->isCommand(Cmd::Cancel))->toBeFalse();
});

test('mouse() wraps a mouse payload exposed via asMouse()', function (): void {
    $e = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(7, 3), buttons: 1));

    expect($e->what)->toBe(EventType::MouseDown)
        ->and($e->asMouse())->toBeInstanceOf(MouseEvent::class)
        ->and($e->asMouse()?->where)->toEqual(new Point(7, 3))
        ->and($e->asMouse()?->buttons)->toBe(1)
        ->and($e->asKey())->toBeNull()
        ->and($e->asMessage())->toBeNull();
});
