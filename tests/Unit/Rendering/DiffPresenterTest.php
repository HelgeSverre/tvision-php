<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use HelgeSverre\TurboVision\Rendering\DiffPresenter;

test('an identical front and back produce no output', function (): void {
    $front = new Buffer(4, 2);
    $back = new Buffer(4, 2);

    expect((new DiffPresenter())->present($front, $back, new AnsiEncoder()))->toBe('');
});

test('a single changed cell emits move + style + char', function (): void {
    $front = new Buffer(4, 1);
    $back = new Buffer(4, 1);
    $back->put(2, 0, new Cell('X', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    // move to (2,0) -> "\e[1;3H", style 0x07, "X"
    expect($ansi)->toBe("\e[1;3H" . "\e[0;37;40m" . 'X');
});

test('consecutive changed cells of one attr coalesce into a single run', function (): void {
    $front = new Buffer(5, 1);
    $back = new Buffer(5, 1);
    $back->put(1, 0, new Cell('a', 0x07));
    $back->put(2, 0, new Cell('b', 0x07));
    $back->put(3, 0, new Cell('c', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    // one move to col 1, one style, then "abc"
    expect($ansi)->toBe("\e[1;2H" . "\e[0;37;40m" . 'abc');
});

test('an attr change mid-run re-emits style but not a move', function (): void {
    $front = new Buffer(3, 1);
    $back = new Buffer(3, 1);
    $back->put(0, 0, new Cell('a', 0x07));
    $back->put(1, 0, new Cell('b', 0x1F)); // different attr
    $back->put(2, 0, new Cell('c', 0x1F));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    expect($ansi)->toBe(
        "\e[1;1H" . "\e[0;37;40m" . 'a'  // run start: move + style + 'a'
        . "\e[0;97;44m" . 'bc'           // attr changes: re-style, no move, 'bc'
    );
});

test('an unchanged gap splits cells into separate runs', function (): void {
    $front = new Buffer(5, 1);
    $back = new Buffer(5, 1);
    $back->put(0, 0, new Cell('a', 0x07));
    // column 1 unchanged (blank)
    $back->put(2, 0, new Cell('b', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    expect($ansi)->toBe(
        "\e[1;1H" . "\e[0;37;40m" . 'a'   // run 1 at col 0
        . "\e[1;3H" . "\e[0;37;40m" . 'b' // run 2 at col 2 (new move)
    );
});

test('rows are emitted top-to-bottom with independent moves', function (): void {
    $front = new Buffer(2, 2);
    $back = new Buffer(2, 2);
    $back->put(0, 0, new Cell('a', 0x07));
    $back->put(0, 1, new Cell('b', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    expect($ansi)->toBe(
        "\e[1;1H" . "\e[0;37;40m" . 'a'   // row 0
        . "\e[2;1H" . "\e[0;37;40m" . 'b' // row 1
    );
});
