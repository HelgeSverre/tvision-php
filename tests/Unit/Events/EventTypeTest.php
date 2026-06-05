<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;

test('event types carry their faithful evXxx codes', function (): void {
    expect(EventType::Nothing->value)->toBe(0x0000)
        ->and(EventType::MouseDown->value)->toBe(0x0001)
        ->and(EventType::KeyDown->value)->toBe(0x0010)
        ->and(EventType::Command->value)->toBe(0x0100)
        ->and(EventType::Broadcast->value)->toBe(0x0200);
});

test('a type can be tested against a routing mask', function (): void {
    expect(EventType::MouseDown->inMask(EventMask::Mouse))->toBeTrue()
        ->and(EventType::KeyDown->inMask(EventMask::Mouse))->toBeFalse()
        ->and(EventType::Command->inMask(EventMask::Message))->toBeTrue();
});

test('mask constants match the original groupings', function (): void {
    expect(EventMask::Mouse)->toBe(0x000F)
        ->and(EventMask::Keyboard)->toBe(0x0010)
        ->and(EventMask::Message)->toBe(0xFF00);
});
