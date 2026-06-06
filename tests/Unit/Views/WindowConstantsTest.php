<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

test('window flags match views.h wf* values', function (): void {
    expect(WindowFlags::Move)->toBe(0x01)
        ->and(WindowFlags::Grow)->toBe(0x02)
        ->and(WindowFlags::Close)->toBe(0x04)
        ->and(WindowFlags::Zoom)->toBe(0x08)
        ->and(WindowFlags::Default)->toBe(0x0F);
});

test('window palette indices match wp* values', function (): void {
    expect(WindowPalette::Blue)->toBe(0)
        ->and(WindowPalette::Cyan)->toBe(1)
        ->and(WindowPalette::Gray)->toBe(2);
});

test('window palette byte strings are verbatim from views.h', function (): void {
    expect(WindowPalette::BLUE_WINDOW)->toBe("\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F")
        ->and(WindowPalette::CYAN_WINDOW)->toBe("\x10\x11\x12\x13\x14\x15\x16\x17")
        ->and(WindowPalette::GRAY_WINDOW)->toBe("\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F");
});

test('byteFor returns the correct palette string per index', function (): void {
    expect(WindowPalette::byteFor(WindowPalette::Blue))->toBe(WindowPalette::BLUE_WINDOW)
        ->and(WindowPalette::byteFor(WindowPalette::Cyan))->toBe(WindowPalette::CYAN_WINDOW)
        ->and(WindowPalette::byteFor(WindowPalette::Gray))->toBe(WindowPalette::GRAY_WINDOW)
        ->and(WindowPalette::byteFor(99))->toBe(WindowPalette::BLUE_WINDOW); // fallback
});
