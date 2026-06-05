<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Terminal\Screen;

test('init sizes the back buffer from the driver and delegates lifecycle', function (): void {
    $driver = new HeadlessDriver(10, 4);
    $screen = new Screen($driver);
    $screen->init();

    expect($driver->isInitialised())->toBeTrue()
        ->and($screen->size())->toEqual(new Point(10, 4))
        ->and($screen->cols())->toBe(10)
        ->and($screen->rows())->toBe(4)
        ->and($screen->back()->width)->toBe(10)
        ->and($screen->back()->height)->toBe(4);

    $screen->shutdown();
    expect($driver->isInitialised())->toBeFalse();
});

test('flush writes the diff then makes the next flush a no-op', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $screen = new Screen($driver);
    $screen->init();

    $screen->back()->put(0, 0, new Cell('H', 0x07));
    $screen->back()->put(1, 0, new Cell('i', 0x07));
    $screen->flush();

    $first = $driver->takeOutput();
    expect($first)->toContain('Hi')
        ->and($first)->toContain("\e[1;1H");

    // nothing changed -> second flush emits nothing
    $screen->flush();
    expect($driver->takeOutput())->toBe('');
});

test('flush wraps a non-empty frame in DEC 2026 synchronized-update markers', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $screen = new Screen($driver);
    $screen->init();

    $screen->back()->put(0, 0, new Cell('X', 0x07));
    $screen->flush();

    $out = $driver->takeOutput();
    expect($out)->toStartWith("\e[?2026h")  // begin sync
        ->and($out)->toEndWith("\e[?2026l") // end sync
        ->and($out)->toContain('X');
});

test('clear() blanks the back buffer', function (): void {
    $driver = new HeadlessDriver(3, 1);
    $screen = new Screen($driver);
    $screen->init();

    $screen->back()->put(0, 0, new Cell('Z', 0x07));
    $screen->clear();

    expect($screen->back()->rows())->toBe(['   ']);
});

test('pollEvents decodes a fed arrow key into a Key::Up event', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $screen = new Screen($driver);
    $screen->init();

    $driver->feedInput("\e[A");
    $events = $screen->pollEvents(0);

    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

test('pollEvents reassembles a split escape sequence across two polls', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $screen = new Screen($driver);
    $screen->init();

    $driver->feedInput("\e[");          // incomplete CSI
    expect($screen->pollEvents(0))->toBe([]);

    $driver->feedInput('A');            // completes it
    $events = $screen->pollEvents(0);
    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

test('a stranded ESC becomes Key::Esc when the next poll is empty', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $screen = new Screen($driver);
    $screen->init();

    $driver->feedInput("\e");            // lone ESC -> held as remainder
    expect($screen->pollEvents(0))->toBe([]);

    // no further input: the pending ESC is flushed as Key::Esc
    $events = $screen->pollEvents(0);
    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->is(Key::Esc))->toBeTrue();
});

test('a driver resize reflows the buffers and sets wasResized', function (): void {
    $driver = new HeadlessDriver(5, 2);
    $screen = new Screen($driver);
    $screen->init();

    $driver->resizeTo(8, 3);
    $screen->pollEvents(0);

    expect($screen->wasResized())->toBeTrue()
        ->and($screen->wasResized())->toBeFalse() // cleared after read
        ->and($screen->back()->width)->toBe(8)
        ->and($screen->back()->height)->toBe(3);
});

test('end-to-end: draw a bordered box, flush, assert the rendered glyphs', function (): void {
    $driver = new HeadlessDriver(6, 3);
    $screen = new Screen($driver);
    $screen->init();

    $back = $screen->back();
    // top/bottom borders
    $back->put(0, 0, new Cell('+', 0x07));
    $back->put(1, 0, new Cell('-', 0x07));
    $back->put(2, 0, new Cell('+', 0x07));
    $back->put(0, 2, new Cell('+', 0x07));
    $back->put(1, 2, new Cell('-', 0x07));
    $back->put(2, 2, new Cell('+', 0x07));
    // sides
    $back->put(0, 1, new Cell('|', 0x07));
    $back->put(2, 1, new Cell('|', 0x07));

    $screen->flush();
    $ansi = $driver->takeOutput();

    expect($ansi)->toContain("\e[1;1H")   // first run starts at top-left
        ->and($ansi)->toContain('+-+')     // top border coalesced into one run
        ->and($ansi)->toContain('|');      // a side glyph present
});
