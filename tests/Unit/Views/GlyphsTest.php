<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\Glyphs;

test('single-line box glyphs', function (): void {
    expect(Glyphs::SINGLE_TOP_LEFT)->toBe('┌')
        ->and(Glyphs::SINGLE_TOP_RIGHT)->toBe('┐')
        ->and(Glyphs::SINGLE_BOTTOM_LEFT)->toBe('└')
        ->and(Glyphs::SINGLE_BOTTOM_RIGHT)->toBe('┘')
        ->and(Glyphs::SINGLE_HORIZONTAL)->toBe('─')
        ->and(Glyphs::SINGLE_VERTICAL)->toBe('│')
        ->and(Glyphs::SINGLE_TEE_DOWN)->toBe('┬')
        ->and(Glyphs::SINGLE_TEE_UP)->toBe('┴')
        ->and(Glyphs::SINGLE_TEE_RIGHT)->toBe('├')
        ->and(Glyphs::SINGLE_TEE_LEFT)->toBe('┤')
        ->and(Glyphs::SINGLE_CROSS)->toBe('┼');
});

test('double-line box glyphs', function (): void {
    expect(Glyphs::DOUBLE_TOP_LEFT)->toBe('╔')
        ->and(Glyphs::DOUBLE_TOP_RIGHT)->toBe('╗')
        ->and(Glyphs::DOUBLE_BOTTOM_LEFT)->toBe('╚')
        ->and(Glyphs::DOUBLE_BOTTOM_RIGHT)->toBe('╝')
        ->and(Glyphs::DOUBLE_HORIZONTAL)->toBe('═')
        ->and(Glyphs::DOUBLE_VERTICAL)->toBe('║');
});

test('scroll bar glyphs', function (): void {
    expect(Glyphs::ARROW_LEFT)->toBe('◄')
        ->and(Glyphs::ARROW_RIGHT)->toBe('►')
        ->and(Glyphs::ARROW_UP)->toBe('▲')
        ->and(Glyphs::ARROW_DOWN)->toBe('▼')
        ->and(Glyphs::SCROLL_TRACK)->toBe('░')
        ->and(Glyphs::SCROLL_THUMB)->toBe('▒');
});

test('frame icon strings carry ~highlight~ markers', function (): void {
    expect(Glyphs::CLOSE_ICON)->toBe('[~■~]')
        ->and(Glyphs::ZOOM_ICON)->toBe('[~↑~]')
        ->and(Glyphs::UNZOOM_ICON)->toBe('[~↓~]')
        ->and(Glyphs::DRAG_ICON)->toBe('~──~');
});
