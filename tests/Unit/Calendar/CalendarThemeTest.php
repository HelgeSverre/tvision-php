<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Calendar\CalendarTheme;

test('modern dark calendar theme inherits its canvas and uses restrained accents', function (): void {
    $theme = CalendarTheme::modernDark();

    expect($theme->canvas)->toBe(0x07)
        ->and($theme->muted)->toBe(0x08)
        ->and($theme->accent)->toBe(0x0B)
        ->and($theme->selection)->toBe(0x0B)
        ->and($theme->error)->toBe(0x0C)
        ->and($theme->eventColor('Work') >> 4)->toBe(0);
});

test('calendar themes reject invalid or empty attribute sets', function (): void {
    expect(fn (): CalendarTheme => new CalendarTheme(canvas: 0x100))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): CalendarTheme => new CalendarTheme(eventColors: []))
        ->toThrow(InvalidArgumentException::class);
});
