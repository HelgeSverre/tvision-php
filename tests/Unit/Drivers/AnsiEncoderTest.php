<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\AnsiEncoder;

test('moveCursor emits a 1-based CUP sequence', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->moveCursor(0, 0))->toBe("\e[1;1H")
        ->and($enc->moveCursor(4, 2))->toBe("\e[3;5H")   // col 4 -> 5, row 2 -> 3
        ->and($enc->moveCursor(79, 23))->toBe("\e[24;80H");
});

test('style mirrors Attribute::fromByte(byte)->toSgr()', function (): void {
    $enc = new AnsiEncoder();

    // 0x07 = light gray on black
    expect($enc->style(0x07))->toBe("\e[0;37m")
        // 0x1F = white (15) on blue (CGA 1 -> ANSI 4 -> 44) -> bright fg
        ->and($enc->style(0x1F))->toBe("\e[0;97;44m");
});

test('encoder can request literal ANSI black for classic rendering', function (): void {
    expect((new AnsiEncoder(useDefaultBackgroundForBlack: false))->style(0x07))
        ->toBe("\e[0;37;40m");
});

test('run combines move + style + text in order', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->run(2, 1, 'Hi', 0x07))
        ->toBe("\e[2;3H" . "\e[0;37m" . 'Hi');
});

test('screen-control helpers are exact constant strings', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->reset())->toBe("\e[0m")
        ->and($enc->clearScreen())->toBe("\e[2J\e[H")
        ->and($enc->hideCursor())->toBe("\e[?25l")
        ->and($enc->showCursor())->toBe("\e[?25h")
        ->and($enc->enterAltScreen())->toBe("\e[?1049h")
        ->and($enc->leaveAltScreen())->toBe("\e[?1049l")
        ->and($enc->enableMouse())->toBe("\e[?1000h\e[?1002h\e[?1006h")
        ->and($enc->disableMouse())->toBe("\e[?1006l\e[?1002l\e[?1000l")
        ->and($enc->enableMouse(true))->toBe("\e[?1000h\e[?1002h\e[?1003h\e[?1006h")
        ->and($enc->disableMouse(true))->toBe("\e[?1006l\e[?1003l\e[?1002l\e[?1000l");
});

test('synchronized-output markers use DEC 2026 (atomic frames on modern terminals)', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->beginSyncUpdate())->toBe("\e[?2026h")
        ->and($enc->endSyncUpdate())->toBe("\e[?2026l");
});
