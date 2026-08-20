<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\TerminalText;

test('terminal text segments, measures, and slices grapheme clusters', function (): void {
    $text = "Ae\u{0301}B";

    expect(TerminalText::graphemes($text))->toBe(['A', "e\u{0301}", 'B'])
        ->and(TerminalText::length($text))->toBe(3)
        ->and(TerminalText::slice($text, 1, 1))->toBe("e\u{0301}");
});

test('cell glyphs reject values that cannot occupy exactly one terminal column', function (): void {
    expect(TerminalText::cellGlyph('A'))->toBe('A')
        ->and(TerminalText::cellGlyph("e\u{0301}"))->toBe("e\u{0301}")
        ->and(TerminalText::cellGlyph('界'))->toBe('?')
        ->and(TerminalText::cellGlyph('🚀'))->toBe('?')
        ->and(TerminalText::cellGlyph("⌨\u{FE0F}"))->toBe('?')
        ->and(TerminalText::cellGlyph("\n"))->toBe('?')
        ->and(TerminalText::cellGlyph('AB'))->toBe('?');
});
