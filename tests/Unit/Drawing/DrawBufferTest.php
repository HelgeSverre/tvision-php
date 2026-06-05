<?php

declare(strict_types=1);

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
