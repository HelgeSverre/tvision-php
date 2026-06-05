<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\DecodeResult;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\KeyDownEvent;

test('holds events and a remainder string', function (): void {
    $events = [Event::keyDown(new KeyDownEvent(0x0061, 'a'))];
    $result = new DecodeResult($events, "\e");

    expect($result->events)->toBe($events)
        ->and($result->remainder)->toBe("\e");
});

test('defaults to no events and an empty remainder', function (): void {
    $result = new DecodeResult();

    expect($result->events)->toBe([])
        ->and($result->remainder)->toBe('');
});
