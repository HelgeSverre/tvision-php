<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Color;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;

test('moveCursor emits a 1-based CUP sequence', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->moveCursor(0, 0))->toBe("\e[1;1H")
        ->and($enc->moveCursor(4, 2))->toBe("\e[3;5H")   // col 4 -> 5, row 2 -> 3
        ->and($enc->moveCursor(79, 23))->toBe("\e[24;80H");
});

test('moveCursor rejects coordinates outside the screen model', function (): void {
    $encoder = new AnsiEncoder();

    expect(fn () => $encoder->moveCursor(-1, 0))
        ->toThrow(InvalidArgumentException::class, 'non-negative')
        ->and(fn () => $encoder->moveCursor(0, -1))
        ->toThrow(InvalidArgumentException::class, 'non-negative');
});

test('moveCursor never emits float notation at the integer boundary', function (): void {
    $encoder = new AnsiEncoder();

    expect($encoder->moveCursor(PHP_INT_MAX, PHP_INT_MAX))
        ->toBe("\e[" . PHP_INT_MAX . ';' . PHP_INT_MAX . 'H');
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

test('style renders an extended bright background cell value', function (): void {
    $attribute = new Attribute(Color::White, Color::DarkGray);

    expect((new AnsiEncoder())->style($attribute->toCellValue()))
        ->toBe("\e[0;97;100m");
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

test('Kitty keyboard helpers use stack-safe push and pop sequences', function (): void {
    $encoder = new AnsiEncoder();

    expect($encoder->pushKittyKeyboard())->toBe("\e[>1u")
        ->and($encoder->popKittyKeyboard())->toBe("\e[<u");
});
