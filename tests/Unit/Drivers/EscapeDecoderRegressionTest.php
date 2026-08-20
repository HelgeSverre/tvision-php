<?php

declare(strict_types=1);

/**
 * Regression tests for verified decoder defects fixed in M1 driver layer.
 *
 * Each test is labelled with the original defect ID and covers the exact
 * hex byte sequence that was previously decoded incorrectly.
 */

use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function regressionDecode(string $hex): \HelgeSverre\TurboVision\Drivers\DecodeResult
{
    $raw = hex2bin($hex);
    if ($raw === false) {
        throw new \RuntimeException("hex2bin failed for: {$hex}");
    }

    return (new EscapeDecoder())->decode($raw);
}

// ---------------------------------------------------------------------------
// SS3 lowercase — rxvt Ctrl+arrow keys
// ---------------------------------------------------------------------------

test('rxvt Ctrl+Up ESC O a (SS3 lowercase a) decodes to Key::Up', function (): void {
    // Defect: SS3 table had only uppercase; 0x61 ('a') was silently dropped.
    // Sequence: 1b 4f 61
    $result = regressionDecode('1b4f61');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $key = $result->events[0]->asKey();
    expect($key)->not->toBeNull();
    expect($key?->is(Key::Up))->toBeTrue();
});

test('rxvt Ctrl+Down ESC O b decodes to Key::Down', function (): void {
    $result = regressionDecode('1b4f62');

    expect($result->events)->toHaveCount(1);
    expect($result->events[0]->asKey()?->is(Key::Down))->toBeTrue();
});

test('rxvt Ctrl+Right ESC O c decodes to Key::Right', function (): void {
    $result = regressionDecode('1b4f63');

    expect($result->events)->toHaveCount(1);
    expect($result->events[0]->asKey()?->is(Key::Right))->toBeTrue();
});

test('rxvt Ctrl+Left ESC O d decodes to Key::Left', function (): void {
    $result = regressionDecode('1b4f64');

    expect($result->events)->toHaveCount(1);
    expect($result->events[0]->asKey()?->is(Key::Left))->toBeTrue();
});

// ---------------------------------------------------------------------------
// CSI lowercase — rxvt Shift+arrow keys
// ---------------------------------------------------------------------------

test('rxvt Shift+Up ESC [ a decodes to Key::Up', function (): void {
    // Defect: CSI_LETTER table had only uppercase; 'a' was silently dropped.
    // Sequence: 1b 5b 61
    $result = regressionDecode('1b5b61');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $key = $result->events[0]->asKey();
    expect($key?->is(Key::Up))->toBeTrue();
});

test('rxvt Shift+Down ESC [ b decodes to Key::Down', function (): void {
    $result = regressionDecode('1b5b62');

    expect($result->events)->toHaveCount(1);
    expect($result->events[0]->asKey()?->is(Key::Down))->toBeTrue();
});

// ---------------------------------------------------------------------------
// CSI with modifier parameter — xterm modifier-parameter family
// ---------------------------------------------------------------------------

test('xterm Shift+Up ESC [ 1 ; 2 A decodes to Key::Up', function (): void {
    // Defect: non-empty params caused CSI_LETTER check to be skipped; sequence dropped.
    // Sequence: 1b 5b 31 3b 32 41
    $result = regressionDecode('1b5b313b3241');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    expect($result->events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

test('xterm Alt+Up ESC [ 1 ; 3 A decodes to Key::Up', function (): void {
    // Sequence: 1b 5b 31 3b 33 41
    $result = regressionDecode('1b5b313b3341');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    expect($result->events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

test('xterm Ctrl+Up ESC [ 1 ; 5 A decodes to Key::Up', function (): void {
    // Sequence: 1b 5b 31 3b 35 41
    $result = regressionDecode('1b5b313b3541');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    expect($result->events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

// ---------------------------------------------------------------------------
// xterm modifyFunctionKeys — CSI <n> P/Q/R/S form
// ---------------------------------------------------------------------------

test('xterm modifyFunctionKeys F1 ESC [ 1 P ~ decodes to Key::F1 not text tilde', function (): void {
    // Defect: 'P' ended the CSI at params='1'; the trailing '~' leaked as text:~.
    // Sequence: 1b 5b 31 50 7e
    $result = regressionDecode('1b5b31507e');

    // Expect exactly 2 events: F1 from the CSI + tilde as ASCII text
    // (the CSI ends at 'P', and '~' is a separate printable byte).
    // The key fix is that F1 is now emitted instead of being silently dropped.
    expect($result->events)->toHaveCount(2)
        ->and($result->remainder)->toBe('');

    $f1event = $result->events[0]->asKey();
    expect($f1event)->not->toBeNull();
    expect($f1event?->is(Key::F1))->toBeTrue('First event should be Key::F1');

    // Second event is the trailing '~' as printable ASCII.
    $tildeEvent = $result->events[1]->asKey();
    expect($tildeEvent?->char)->toBe('~');
});

// ---------------------------------------------------------------------------
// Mouse: MouseUp button preservation
// ---------------------------------------------------------------------------

test('SGR right-button release preserves button bit (b=2 release)', function (): void {
    // Defect: MouseUp always had buttons=0 regardless of which button was released.
    // Sequence: 1b 5b 3c 32 3b 38 30 3b 32 34 6d  = ESC[<2;80;24m
    $result = regressionDecode('1b5b3c323b38303b32346d');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $event = $result->events[0];
    expect($event->what)->toBe(EventType::MouseUp);

    $mouse = $event->asMouse();
    expect($mouse)->not->toBeNull();
    // Button index 2 (right) -> bit 2 -> buttons = 1<<2 = 4
    expect($mouse?->buttons)->toBe(4, 'Right-button release should carry buttons=4');
});

test('SGR middle-button release preserves button bit (b=1 release)', function (): void {
    // Sequence: ESC[<1;40;12m
    $result = regressionDecode('1b5b3c313b34303b31326d');

    expect($result->events)->toHaveCount(1);

    $event = $result->events[0];
    expect($event->what)->toBe(EventType::MouseUp);
    // Button index 1 (middle) -> 1<<1 = 2
    expect($event->asMouse()?->buttons)->toBe(2, 'Middle-button release should carry buttons=2');
});

// ---------------------------------------------------------------------------
// Mouse: modifier bits stripped before wheel/button dispatch
// ---------------------------------------------------------------------------

test('SGR ctrl+wheel-down b=81 decodes as wheel-down not spurious MouseDown', function (): void {
    // Defect: b=81 (65|16) missed the b===64||b===65 check and became MouseDown/middle.
    // Sequence: 1b 5b 3c 38 31 3b 31 30 3b 35 4d  = ESC[<81;10;5M
    $result = regressionDecode('1b5b3c38313b31303b354d');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $event = $result->events[0];
    expect($event->what)->toBe(EventType::MouseMove, 'ctrl+wheel should be MouseMove, not MouseDown');

    $mouse = $event->asMouse();
    expect($mouse)->not->toBeNull();
    expect($mouse?->wheel)->toBe(1, 'wheel-down should give wheel=+1');
    expect($mouse?->where->x)->toBe(9);
    expect($mouse?->where->y)->toBe(4);
});

test('SGR shift+wheel-up b=68 decodes as wheel-up not spurious MouseDown', function (): void {
    // Defect: b=68 (64|4) missed exact b===64 check; became left-button MouseDown.
    // Sequence: 1b 5b 3c 36 38 3b 31 30 3b 35 4d  = ESC[<68;10;5M
    $result = regressionDecode('1b5b3c36383b31303b354d');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $event = $result->events[0];
    expect($event->what)->toBe(EventType::MouseMove, 'shift+wheel should be MouseMove, not MouseDown');

    $mouse = $event->asMouse();
    expect($mouse?->wheel)->toBe(-1, 'wheel-up should give wheel=-1');
});

// ---------------------------------------------------------------------------
// Mouse: motion flag (bit 5) detection
// ---------------------------------------------------------------------------

test('SGR motion report b=32 ESC[<32;15;8M decodes as MouseMove not MouseDown', function (): void {
    // Defect: motion flag (bit 5 = 0x20) was never tested; fell to button-press path.
    // Sequence: 1b 5b 3c 33 32 3b 31 35 3b 38 4d
    $result = regressionDecode('1b5b3c33323b31353b384d');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $event = $result->events[0];
    expect($event->what)->toBe(EventType::MouseMove, 'Motion report (b=32) must decode as MouseMove');

    $mouse = $event->asMouse();
    expect($mouse)->not->toBeNull();
    expect($mouse?->where->x)->toBe(14); // col 15 -> 0-based 14
    expect($mouse?->where->y)->toBe(7);  // row  8 -> 0-based 7
    expect($mouse?->buttons)->toBe(1); // left button remains held during b=32 motion
    expect($mouse?->wheel)->toBe(0);
});

test('SGR motion report b=35 ESC[<35;15;8M decodes as MouseMove', function (): void {
    // b=35 = 0x20 | 0x03 (motion flag, bits0-1=3 means no button held).
    // Sequence: 1b 5b 3c 33 35 3b 31 35 3b 38 4d
    $result = regressionDecode('1b5b3c33353b31353b384d');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $event = $result->events[0];
    expect($event->what)->toBe(EventType::MouseMove, 'b=35 has motion bit set; must be MouseMove');

    $mouse = $event->asMouse();
    expect($mouse?->buttons)->toBe(0);
    expect($mouse?->where->x)->toBe(14);
    expect($mouse?->where->y)->toBe(7);
});

// ---------------------------------------------------------------------------
// Double-ESC passthrough — tmux/screen wrapping
// ---------------------------------------------------------------------------

test('double-ESC passthrough ESC ESC [ A decodes to single Key::Up event', function (): void {
    // Defect: next=0x1B fell to the "unknown" branch, consuming ESC ESC; "[A" became text.
    // Sequence: 1b 1b 5b 41
    $result = regressionDecode('1b1b5b41');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    expect($result->events[0]->asKey()?->is(Key::Up))->toBeTrue();
});

test('double-ESC ESC ESC emits one Esc event and returns second ESC as remainder', function (): void {
    // Defect: both ESC bytes were silently swallowed.
    // Sequence: 1b 1b
    $result = regressionDecode('1b1b');

    // First ESC is emitted as Key::Esc; second ESC is returned as remainder
    // (to be resolved by flushPending after timeout).
    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe("\e");

    expect($result->events[0]->asKey()?->is(Key::Esc))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Kitty progressive-enhancement — CSI 'u' final byte
// ---------------------------------------------------------------------------

test('Kitty CSI u final ESC[13;2u emits a synthetic keyCode=0 event not silent discard', function (): void {
    // Defect: CSI_LETTER and SS3 both skip 'u'; sequence was consumed with 0 events.
    // Sequence: 1b 5b 31 33 3b 32 75
    $result = regressionDecode('1b5b31333b3275');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $key = $result->events[0]->asKey();
    expect($key)->not->toBeNull();
    expect($key?->keyCode)->toBe(0, 'Unknown CSI final should yield keyCode=0 sentinel');
});

// ---------------------------------------------------------------------------
// Truncated UTF-8 — partial sequences deferred as remainder
// ---------------------------------------------------------------------------

test('truncated 2-byte UTF-8 lead byte alone (0xC2) is returned as remainder not emitted as event', function (): void {
    // Defect: greedy loop emitted the lead byte as a 1-byte invalid grapheme.
    $result = regressionDecode('c2');

    expect($result->events)->toBe([], '0xC2 alone must produce no events')
        ->and($result->remainder)->toBe("\xc2", 'incomplete sequence must be held as remainder');
});

test('truncated 2-byte UTF-8 (0xC3 alone) is returned as remainder', function (): void {
    $result = regressionDecode('c3');

    expect($result->events)->toBe([])
        ->and($result->remainder)->toBe("\xc3");
});

test('truncated 4-byte UTF-8 sequence (F0 9F 91 missing last byte) is returned as remainder', function (): void {
    // Defect: greedy loop slurped 3 bytes and emitted an incomplete 3-byte grapheme.
    // Sequence: f0 9f 91 (missing 8d)
    $result = regressionDecode('f09f91');

    expect($result->events)->toBe([], '3 of 4 UTF-8 bytes must produce no events')
        ->and($result->remainder)->toBe("\xf0\x9f\x91", 'truncated 4-byte sequence must be held as remainder');
});

test('complete 4-byte UTF-8 emoji decodes correctly after fix', function (): void {
    // Ensure the UTF-8 fix did not break complete sequences.
    // U+1F44D THUMBS UP = f0 9f 91 8d
    $result = regressionDecode('f09f918d');

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');

    $key = $result->events[0]->asKey();
    expect($key?->char)->toBe("\u{1F44D}");
    expect($key?->keyCode)->toBe(0);
});
