<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\Driver;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Terminal\TerminalCapabilities;

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

test('init shuts down a driver when sizing fails after driver initialization', function (): void {
    $driver = new FailingSizeDriver();
    $screen = new Screen($driver);

    expect(fn () => $screen->init())->toThrow(RuntimeException::class, 'size failed')
        ->and($driver->initialised)->toBeFalse()
        ->and($driver->shutdownCalls)->toBe(1);
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

test('flush falls back to plain ANSI when synchronized updates are unavailable', function (): void {
    $driver = new HeadlessDriver(5, 1, new TerminalCapabilities());
    $screen = new Screen($driver);
    $screen->init();
    $screen->back()->put(0, 0, new Cell('X', 0x07));

    $screen->flush();

    expect($driver->takeOutput())->toStartWith("\e[1;1H");
});

test('screen rejects driver dimensions that would exhaust the process', function (): void {
    $driver = new HeadlessDriver(2000, 1000);
    $screen = new Screen($driver);

    expect(fn () => $screen->init())
        ->toThrow(InvalidArgumentException::class, 'safe screen-buffer limit')
        ->and($driver->isInitialised())->toBeFalse();
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
    $now = 1.0;
    $screen = new Screen($driver, clock: static function () use (&$now): float {
        return $now;
    });
    $screen->init();

    $driver->feedInput("\e");            // lone ESC -> held as remainder
    expect($screen->pollEvents(0))->toBe([]);

    $now += 0.05;
    // no further input after the ambiguity timeout: emit Key::Esc
    $events = $screen->pollEvents(0);
    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->is(Key::Esc))->toBeTrue();
});

test('consecutive ESC presses survive the ambiguity timeout', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $now = 1.0;
    $screen = new Screen($driver, clock: static function () use (&$now): float {
        return $now;
    });
    $screen->init();

    $driver->feedInput("\e\e");
    expect($screen->pollEvents(0))->toBe([]);

    $now += 0.05;
    $events = $screen->pollEvents(0);

    expect($events)->toHaveCount(2)
        ->and($events[0]->asKey()?->is(Key::Esc))->toBeTrue()
        ->and($events[1]->asKey()?->is(Key::Esc))->toBeTrue();
});

test('an incomplete escape sequence expires instead of swallowing the next key', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $now = 1.0;
    $screen = new Screen($driver, clock: static function () use (&$now): float {
        return $now;
    });
    $screen->init();

    $driver->feedInput("\e[");
    expect($screen->pollEvents(0))->toBe([])
        ->and($screen->pollEvents(0))->toBe([]);

    $now += 0.3;
    expect($screen->pollEvents(0))->toBe([]);

    $driver->feedInput('q');
    $events = $screen->pollEvents(0);

    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->char)->toBe('q');
});

test('fragmented escape and UTF-8 input survives quiet polls inside the sequence timeout', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $now = 1.0;
    $screen = new Screen($driver, clock: static function () use (&$now): float {
        return $now;
    });
    $screen->init();

    $driver->feedInput("\e[");
    expect($screen->pollEvents(0))->toBe([]);
    $now += 0.1;
    expect($screen->pollEvents(0))->toBe([]);
    $driver->feedInput('A');
    $arrow = $screen->pollEvents(0);

    $driver->feedInput("\xE2");
    expect($screen->pollEvents(0))->toBe([]);
    $now += 0.1;
    expect($screen->pollEvents(0))->toBe([]);
    $driver->feedInput("\x82\xAC");
    $unicode = $screen->pollEvents(0);

    expect($arrow)->toHaveCount(1)
        ->and($arrow[0]->asKey()?->is(Key::Up))->toBeTrue()
        ->and($unicode)->toHaveCount(1)
        ->and($unicode[0]->asKey()?->char)->toBe('€');
});

test('sequence expiry follows the last fragment rather than the first fragment', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $now = 1.0;
    $screen = new Screen($driver, clock: static function () use (&$now): float {
        return $now;
    });
    $screen->init();

    $driver->feedInput("\e[");
    expect($screen->pollEvents(0))->toBe([]);
    $now += 0.2;
    expect($screen->pollEvents(0))->toBe([]);
    $driver->feedInput('1;');
    expect($screen->pollEvents(0))->toBe([]);
    $now += 0.2;
    expect($screen->pollEvents(0))->toBe([]);
    $driver->feedInput('2A');
    $events = $screen->pollEvents(0);

    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

test('pollEvents rejects a negative timeout', function (): void {
    $screen = new Screen(new HeadlessDriver(5, 1));
    $screen->init();

    expect(fn () => $screen->pollEvents(-1))
        ->toThrow(InvalidArgumentException::class, 'non-negative');
});

test('oversized incomplete input is discarded at the decoder boundary', function (): void {
    $driver = new HeadlessDriver(5, 1);
    $screen = new Screen($driver);
    $screen->init();

    $driver->feedInput("\e[" . str_repeat('1', 5000));
    expect($screen->pollEvents(0))->toBe([]);

    $driver->feedInput('q');
    $events = $screen->pollEvents(0);

    expect($events)->toHaveCount(1)
        ->and($events[0]->asKey()?->char)->toBe('q');
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

test('a driver resize invalidates the front buffer and forces a full repaint', function (): void {
    $driver = new HeadlessDriver(2, 1);
    $screen = new Screen($driver);
    $screen->init();

    $screen->back()->put(0, 0, new Cell('X', 0x07));
    $screen->flush();
    $driver->takeOutput();

    $driver->resizeTo(3, 1);
    $screen->pollEvents(0);
    $screen->flush();

    expect($driver->takeOutput())->toContain('   ');
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

final class FailingSizeDriver implements Driver
{
    public bool $initialised = false;

    public int $shutdownCalls = 0;

    public function init(): void
    {
        $this->initialised = true;
    }

    public function shutdown(): void
    {
        $this->initialised = false;
        $this->shutdownCalls++;
    }

    public function size(): array
    {
        throw new RuntimeException('size failed');
    }

    public function write(string $bytes): void {}

    public function pollInput(int $timeoutMs): string
    {
        return '';
    }

    public function resized(): bool
    {
        return false;
    }
}
