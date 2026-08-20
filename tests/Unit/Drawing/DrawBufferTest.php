<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;

/** Helper: render the buffer's characters as a string for readable assertions. */
function dbChars(DrawBuffer $b): string
{
    $s = '';
    foreach ($b->cells() as $cell) {
        $s .= $cell->char;
    }

    return $s;
}

test('a new draw buffer is blank to its width', function (): void {
    $b = new DrawBuffer(5);

    expect($b->cells())->toHaveCount(5)
        ->and(dbChars($b))->toBe('     ');
});

test('moveStr writes a multibyte string at an offset, clipped to width', function (): void {
    $b = new DrawBuffer(5);
    $b->moveStr(1, 'ábc', 0x1F);

    expect(dbChars($b))->toBe(' ábc ')
        ->and($b->cells()[1]->char)->toBe('á')
        ->and($b->cells()[1]->attr)->toBe(0x1F)
        ->and($b->cells()[0]->attr)->toBe(0x07); // untouched default

    $b->moveStr(3, 'XXXX', 0x07); // overruns the right edge
    expect(dbChars($b))->toBe(' ábXX');
});

test('moveStr keeps grapheme clusters in one cell and replaces wide glyphs safely', function (): void {
    $b = new DrawBuffer(5);
    $b->moveStr(0, "Ae\u{0301}🚀B", 0x07);

    expect($b->cells()[0]->char)->toBe('A')
        ->and($b->cells()[1]->char)->toBe("e\u{0301}")
        ->and($b->cells()[2]->char)->toBe('?')
        ->and($b->cells()[3]->char)->toBe('B')
        ->and($b->cells()[4]->char)->toBe(' ');
});

test('moveChar repeats a character N times', function (): void {
    $b = new DrawBuffer(6);
    $b->moveChar(1, '#', 0x07, 3);

    expect(dbChars($b))->toBe(' ###  ');
    // length 6 -> trailing blank kept
    expect($b->cells())->toHaveCount(6);
});

test('moveCStr highlights ~hotkey~ runs and returns the end column', function (): void {
    $b = new DrawBuffer(10);
    $end = $b->moveCStr(0, 'E~x~it', normalAttr: 0x07, highlightAttr: 0x0F);

    expect(dbChars($b))->toBe('Exit      ')   // tildes consumed, not displayed
        ->and($end)->toBe(4)                  // 4 visible chars written
        ->and($b->cells()[0]->attr)->toBe(0x07) // E normal
        ->and($b->cells()[1]->attr)->toBe(0x0F) // x highlighted
        ->and($b->cells()[2]->attr)->toBe(0x07) // i normal
        ->and($b->cells()[3]->attr)->toBe(0x07); // t normal
});

test('putAttribute recolors a column without changing its char', function (): void {
    $b = new DrawBuffer(4);
    $b->moveStr(0, 'abcd', 0x07);
    $b->putAttribute(2, 0x40);

    expect($b->cells()[2]->char)->toBe('c')
        ->and($b->cells()[2]->attr)->toBe(0x40);
});

test('draw buffer width cannot be negative', function (): void {
    expect(fn () => new DrawBuffer(-1))
        ->toThrow(InvalidArgumentException::class, 'non-negative')
        ->and(fn () => new DrawBuffer(Buffer::MAX_CELLS + 1))
        ->toThrow(InvalidArgumentException::class, 'safe cell limit');
});

test('moveChar clips pathological counts to visible work', function (): void {
    $buffer = new DrawBuffer(4);

    $buffer->moveChar(-2, 'X', 0x07, PHP_INT_MAX);
    $buffer->moveChar(PHP_INT_MAX, 'Y', 0x07, PHP_INT_MAX);

    expect(dbChars($buffer))->toBe('XXXX');
});

test('string cursor advancement saturates at the integer boundary', function (): void {
    $buffer = new DrawBuffer(1);

    expect($buffer->moveCStr(PHP_INT_MAX, 'AB', 0x07, 0x08))->toBe(PHP_INT_MAX);
    $buffer->moveStr(PHP_INT_MAX, 'AB', 0x07);

    expect($buffer->cells()[0]->char)->toBe(' ');
});
