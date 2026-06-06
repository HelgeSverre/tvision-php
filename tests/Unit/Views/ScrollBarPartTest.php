<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;

test('scroll bar part codes are faithful to views.h', function (): void {
    expect(ScrollBarPart::LeftArrow)->toBe(0)
        ->and(ScrollBarPart::RightArrow)->toBe(1)
        ->and(ScrollBarPart::PageLeft)->toBe(2)
        ->and(ScrollBarPart::PageRight)->toBe(3)
        ->and(ScrollBarPart::UpArrow)->toBe(4)
        ->and(ScrollBarPart::DownArrow)->toBe(5)
        ->and(ScrollBarPart::PageUp)->toBe(6)
        ->and(ScrollBarPart::PageDown)->toBe(7)
        ->and(ScrollBarPart::Indicator)->toBe(8);
});

test('scroll bar option flags are faithful', function (): void {
    expect(ScrollBarPart::Horizontal)->toBe(0x000)
        ->and(ScrollBarPart::Vertical)->toBe(0x001)
        ->and(ScrollBarPart::HandleKeyboard)->toBe(0x002);
});
