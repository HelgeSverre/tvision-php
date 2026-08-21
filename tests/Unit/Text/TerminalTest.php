<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Text\Terminal;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ScrollBar;

final class TerminalRootGroup extends Group
{
    public function __construct(private readonly Screen $rootScreen)
    {
        parent::__construct(Rect::of(0, 0, $rootScreen->cols(), $rootScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
}

/** @return array{0:Terminal,1:Screen} */
function terminalInScreen(int $width = 8, int $height = 4): array
{
    $screen = new Screen(new HeadlessDriver($width, $height));
    $screen->init();
    $root = new TerminalRootGroup($screen);
    $terminal = new Terminal(Rect::of(0, 0, $width, $height));
    $root->insert($terminal);

    return [$terminal, $screen];
}

test('terminal wraps output to the viewport and reflows retained output after resize', function (): void {
    [$terminal, $screen] = terminalInScreen(4, 3);
    $terminal->write('abcdefgh');
    $terminal->draw();

    expect($screen->back()->rows()[0])->toBe('abcd')
        ->and($screen->back()->rows()[1])->toBe('efgh');

    $terminal->changeBounds(Rect::of(0, 0, 3, 3));
    $terminal->draw();

    // The root screen remains four columns wide, so a parent redraw would own
    // the old fourth column. The terminal's three-cell content is reflowed.
    expect(substr($screen->back()->rows()[0], 0, 3))->toBe('abc')
        ->and(substr($screen->back()->rows()[1], 0, 3))->toBe('def')
        ->and(substr($screen->back()->rows()[2], 0, 3))->toBe('gh ');
});

test('terminal retains a circular logical-line scrollback', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 20, 3), maxBytes: 1_024, maxLines: 3);
    $terminal->write("one\ntwo\nthree\nfour");

    expect($terminal->lineCount())->toBe(3)
        ->and($terminal->scrollback())->toBe(['two', 'three', 'four']);
});

test('terminal keeps its payload inside the configured byte budget even for one long line', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 20, 3), maxBytes: 8, maxLines: 10);
    $terminal->write('123456789');

    expect($terminal->scrollbackBytes())->toBeLessThanOrEqual(8)
        ->and($terminal->scrollback())->toBe(['23456789']);
});

test('terminal accepts large logs while retaining only its bounded circular tail', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 40, 5), maxBytes: 4_096, maxLines: 256);
    $terminal->write(str_repeat("0123456789\n", 10_000));
    $scrollback = $terminal->scrollback();

    expect($terminal->lineCount())->toBeLessThanOrEqual(256)
        ->and($terminal->scrollbackBytes())->toBeLessThanOrEqual(4_096)
        ->and(end($scrollback))->toBe('');
});

test('terminal trims a long no-newline write in bulk rather than per retained character', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 40, 5), maxBytes: 1_024, maxLines: 8);
    $startedAt = hrtime(true);
    $terminal->write(str_repeat('x', 100_000));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

    // Generous for loaded CI. The previous per-character trim took about
    // three seconds locally for this exact input; a bulk trim is well below it.
    expect($terminal->scrollbackBytes())->toBe(1_024)
        ->and($terminal->scrollback())->toBe([str_repeat('x', 1_024)])
        ->and($elapsedSeconds)->toBeLessThan(1.5);
});

test('a large single write keeps transient scrollback storage bounded', function (): void {
    $payload = str_repeat('x', 2 * 1_024 * 1_024);
    $peakAfterInput = memory_get_peak_usage(true);
    $terminal = new Terminal(Rect::of(0, 0, 40, 5), maxBytes: 1_024, maxLines: 8);

    $terminal->write($payload);
    $additionalPeak = memory_get_peak_usage(true) - $peakAfterInput;

    // The caller's two-megabyte input is deliberately excluded. Fixed-size
    // ingestion windows and the 4 KiB retention overrun keep framework-owned
    // temporary storage far below the input size.
    expect($terminal->scrollbackBytes())->toBe(1_024)
        ->and($additionalPeak)->toBeLessThan(16 * 1_024 * 1_024);
});

test('terminal makes wide and control graphemes safe single cells without corrupting UTF-8', function (): void {
    [$terminal, $screen] = terminalInScreen(8, 2);
    $terminal->write("A界🙂e\u{0301}B\tC");
    $terminal->draw();

    expect($terminal->scrollback())->toBe(["A??e\u{0301}B   C"])
        ->and($screen->back()->rows()[0])->toContain('A??')
        ->and($screen->back()->rows()[1])->toBe('C       ');
});

test('terminal follows the tail while output arrives and supports keyboard scrollback', function (): void {
    [$terminal, $screen] = terminalInScreen(6, 2);
    $terminal->write("one\ntwo\nthree\nfour");
    $terminal->draw();

    expect($screen->back()->rows())->toBe(['three ', 'four  ']);

    $up = Event::key(Key::Up);
    $terminal->handleEvent($up);
    $terminal->draw();
    expect($up->isNothing())->toBeTrue()
        ->and($screen->back()->rows())->toBe(['two   ', 'three ']);

    $terminal->write("\nfive");
    $terminal->draw();
    // Reading old output disables follow-tail; incoming text must not jump it.
    expect($screen->back()->rows())->toBe(['two   ', 'three ']);
});

test('terminal can scroll vertically without requiring a vertical scrollbar', function (): void {
    $hBar = ScrollBar::horizontal(Rect::of(0, 0, 8, 1));
    $terminal = new Terminal(Rect::of(0, 0, 8, 2), hScrollBar: $hBar);
    $terminal->write("one\ntwo\nthree\nfour");

    $terminal->handleEvent(Event::key(Key::Up));

    expect($terminal->delta->y)->toBe(1);
});

test('terminal handles carriage-return overwrite in the current line', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 20, 2));
    $terminal->write("downloading 99%\rDone");

    expect($terminal->scrollback())->toBe(['Doneloading 99%']);
});

test('terminal treats CRLF as one line break when consumed through UTF-8 windows', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 20, 2));
    $terminal->write("first\r\nsecond");

    expect($terminal->scrollback())->toBe(['first', 'second']);
});

test('output stream adapter forwards writes, formatted output and flushes', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 20, 3));
    $out = $terminal->output();

    expect($out->write('build '))->toBe(6)
        ->and($out->printf('#%d', 42))->toBe(3)
        ->and($out->writeln(' done'))->toBe(6);
    $out->flush();

    expect($terminal->scrollback())->toBe(['build #42 done', '']);
});

test('invalid UTF-8 bytes cost one fallback glyph each without discarding surrounding output', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 40, 5));
    $terminal->write("caf\xe9 ok \xff MORE\nsecond line");

    expect($terminal->scrollback())->toBe(['caf? ok ? MORE', 'second line']);
});

test('a multi-byte glyph split across writes still renders intact', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 40, 5));
    $terminal->write('caf');
    $terminal->write("\xc3");
    $terminal->write("\xa9 caf\xc3\xa9 done");

    expect($terminal->scrollback())->toBe(['café café done']);
});

test('an invalid byte at the chunk window edge does not swallow the next write', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 200, 5), maxBytes: 65_536);
    $prefix = str_repeat('a', 8_191);

    $terminal->write($prefix . "\xff");
    $terminal->write('tail');

    expect($terminal->scrollback())->toBe([$prefix . '?tail']);
});

test('carriage-return overwrite renders the rewritten line', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 40, 5));

    $terminal->write('hello');
    $terminal->write("\rHE");

    expect($terminal->scrollback())->toBe(['HEllo']);
});

test('carriage-return rewrite stays linear across many overwritten columns', function (): void {
    $terminal = new Terminal(Rect::of(0, 0, 60, 5), maxBytes: 1_000_000);
    $columns = 20_000;

    $started = hrtime(true);
    $terminal->write(str_repeat('.', $columns));
    $terminal->write("\r" . str_repeat('#', $columns));
    $elapsedMs = (hrtime(true) - $started) / 1e6;

    expect($terminal->scrollback()[0])->toBe(str_repeat('#', $columns))
        ->and($elapsedMs)->toBeLessThan(1500.0);
});
