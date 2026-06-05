<?php

declare(strict_types=1);

/**
 * EscapeDecoder corpus test.
 *
 * Each entry is a real terminal byte trace; the test verifies that
 * EscapeDecoder::decode() produces the expected Event.
 *
 * SKIPPED entries and reasons are documented inline.
 *
 * DECODER EXTENSIONS made to handle corpus sequences (beyond the plan baseline):
 *  1. CSI_LETTER['Z'] => Key::ShiftTab  — ESC [ Z is ECMA-48 Shift-Tab; standard.
 *  2. SS3['A'..'D']   => Up/Down/Right/Left — application cursor-key mode (DECCKM).
 *  3. SS3['H','F']    => Home/End — application keypad Home/End.
 *  4. emitMouse() wheel branch: Pb=64 -> wheel=-1 (up), Pb=65 -> wheel=1 (down),
 *     emitted as EventType::MouseMove with MouseEvent::$wheel set.
 */

use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

/** Decode $bytes and assert exactly one event (no remainder). Returns the event. */
function corpusDecodeOne(string $bytes): \HelgeSverre\TurboVision\Events\Event
{
    $result = (new EscapeDecoder())->decode($bytes);
    expect($result->events)->toHaveCount(1, 'Expected exactly one decoded event')
        ->and($result->remainder)->toBe('');

    return $result->events[0];
}

// ---------------------------------------------------------------------------
// kind=key — sequences that map to a named Key enum case
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{string, string}>
 */
function corpusKeyEntries(): array
{
    return [
        'Enter (CR)'              => ['0d', 'Enter'],
        'Enter (LF fallback)'     => ['0a', 'Enter'],
        'Tab'                     => ['09', 'Tab'],
        'Shift+Tab (CSI Z)'       => ['1b5b5a', 'ShiftTab'],
        'Backspace (DEL 0x7F)'    => ['7f', 'Backspace'],
        'Backspace (BS 0x08)'     => ['08', 'Backspace'],
        'Up Arrow (CSI A)'        => ['1b5b41', 'Up'],
        'Down Arrow (CSI B)'      => ['1b5b42', 'Down'],
        'Right Arrow (CSI C)'     => ['1b5b43', 'Right'],
        'Left Arrow (CSI D)'      => ['1b5b44', 'Left'],
        'Up Arrow (SS3 A)'        => ['1b4f41', 'Up'],
        'Down Arrow (SS3 B)'      => ['1b4f42', 'Down'],
        'Right Arrow (SS3 C)'     => ['1b4f43', 'Right'],
        'Left Arrow (SS3 D)'      => ['1b4f44', 'Left'],
        'Home (CSI H)'            => ['1b5b48', 'Home'],
        'Home (SS3 H)'            => ['1b4f48', 'Home'],
        'Home (CSI tilde ~1~)'    => ['1b5b317e', 'Home'],
        'End (CSI F)'             => ['1b5b46', 'End'],
        'End (SS3 F)'             => ['1b4f46', 'End'],
        'End (CSI tilde ~4~)'     => ['1b5b347e', 'End'],
        'Insert (CSI tilde ~2~)'  => ['1b5b327e', 'Insert'],
        'Delete (CSI tilde ~3~)'  => ['1b5b337e', 'Delete'],
        'Page Up (CSI tilde ~5~)' => ['1b5b357e', 'PageUp'],
        'Page Down (CSI tilde ~6~)' => ['1b5b367e', 'PageDown'],
        'Alt+A (ESC prefix)'      => ['1b61', 'AltA'],
        'Alt+Z (ESC prefix)'      => ['1b7a', 'AltZ'],
        'Alt+X (ESC prefix)'      => ['1b78', 'AltX'],
        'F1 SS3 form'             => ['1b4f50', 'F1'],
        'F2 SS3 form'             => ['1b4f51', 'F2'],
        'F3 SS3 form'             => ['1b4f52', 'F3'],
        'F4 SS3 form'             => ['1b4f53', 'F4'],
        'F5'                      => ['1b5b31357e', 'F5'],
        'F6'                      => ['1b5b31377e', 'F6'],
        'F7'                      => ['1b5b31387e', 'F7'],
        'F8'                      => ['1b5b31397e', 'F8'],
        'F9'                      => ['1b5b32307e', 'F9'],
        'F10'                     => ['1b5b32317e', 'F10'],
        'F11'                     => ['1b5b32337e', 'F11'],
        'F12'                     => ['1b5b32347e', 'F12'],
        'Alt-B'                   => ['1b62', 'AltB'],
        'Alt-C'                   => ['1b63', 'AltC'],
        'Alt-D'                   => ['1b64', 'AltD'],
        'Alt-E'                   => ['1b65', 'AltE'],
        'Alt-F'                   => ['1b66', 'AltF'],
        'Alt-G'                   => ['1b67', 'AltG'],
        'Alt-H'                   => ['1b68', 'AltH'],
        'Alt-I'                   => ['1b69', 'AltI'],
        'Alt-J'                   => ['1b6a', 'AltJ'],
        'Alt-K'                   => ['1b6b', 'AltK'],
        'Alt-L'                   => ['1b6c', 'AltL'],
        'Alt-M'                   => ['1b6d', 'AltM'],
        'Alt-N'                   => ['1b6e', 'AltN'],
        'Alt-O'                   => ['1b6f', 'AltO'],
        'Alt-P'                   => ['1b70', 'AltP'],
        'Alt-Q'                   => ['1b71', 'AltQ'],
        'Alt-R'                   => ['1b72', 'AltR'],
        'Alt-S'                   => ['1b73', 'AltS'],
        'Alt-T'                   => ['1b74', 'AltT'],
        'Alt-U'                   => ['1b75', 'AltU'],
        'Alt-V'                   => ['1b76', 'AltV'],
        'Alt-W'                   => ['1b77', 'AltW'],
        'Alt-Y'                   => ['1b79', 'AltY'],
    ];
}

test('corpus key: each sequence decodes to the expected Key enum case', function (): void {
    foreach (corpusKeyEntries() as $label => [$bytesHex, $keyName]) {
        $raw = hex2bin($bytesHex);
        if ($raw === false) {
            throw new \RuntimeException("hex2bin failed for {$label}: {$bytesHex}");
        }

        $event = corpusDecodeOne($raw);
        $key = $event->asKey();

        if ($key === null) {
            throw new \RuntimeException("Expected a KeyDownEvent for {$label}");
        }

        $match = null;
        foreach (Key::cases() as $case) {
            if ($case->name === $keyName) {
                $match = $case;
                break;
            }
        }

        if ($match === null) {
            throw new \RuntimeException("Key::{$keyName} does not exist in the enum (for {$label})");
        }

        expect($key->is($match))->toBeTrue(
            "{$label}: expected Key::{$keyName} (0x" . dechex($match->value) . ') but got keyCode=0x' . dechex($key->keyCode)
        );
    }
});

// ---------------------------------------------------------------------------
// kind=text — printable ASCII and UTF-8 sequences
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{string, string}>
 */
function corpusTextEntries(): array
{
    return [
        'Printable A'                        => ['41', 'A'],
        'Printable space'                    => ['20', ' '],
        'ASCII lowercase a'                  => ['61', 'a'],
        'ASCII digit 0'                      => ['30', '0'],
        'ASCII digit 9'                      => ['39', '9'],
        'ASCII exclamation mark'             => ['21', '!'],
        'ASCII plus sign'                    => ['2b', '+'],
        'ASCII tilde'                        => ['7e', '~'],
        'ASCII semicolon'                    => ['3b', ';'],
        'ASCII backquote'                    => ['60', '`'],
        'ASCII slash'                        => ['2f', '/'],
        '2-byte UTF-8 e-acute U+00E9'        => ['c3a9', "\u{00E9}"],
        '2-byte UTF-8 n-tilde U+00F1'        => ['c3b1', "\u{00F1}"],
        '2-byte UTF-8 copyright U+00A9'      => ['c2a9', "\u{00A9}"],
        '2-byte UTF-8 pound sterling U+00A3' => ['c2a3', "\u{00A3}"],
        '2-byte UTF-8 degree sign U+00B0'    => ['c2b0', "\u{00B0}"],
        '2-byte UTF-8 Arabic ba U+0628'      => ['d8a8', "\u{0628}"],
        '3-byte UTF-8 euro sign U+20AC'      => ['e282ac', "\u{20AC}"],
        '3-byte UTF-8 em dash U+2014'        => ['e28094', "\u{2014}"],
        '3-byte UTF-8 left double quote U+201C' => ['e2809c', "\u{201C}"],
        '3-byte UTF-8 CJK zhong U+4E2D'     => ['e4b8ad', "\u{4E2D}"],
        '3-byte UTF-8 hiragana a U+3042'     => ['e38182', "\u{3042}"],
        '3-byte UTF-8 eighth note U+266A'    => ['e299aa', "\u{266A}"],
        '4-byte UTF-8 grinning face U+1F600' => ['f09f9880', "\u{1F600}"],
        '4-byte UTF-8 thumbs up U+1F44D'     => ['f09f918d', "\u{1F44D}"],
        '4-byte UTF-8 rocket U+1F680'        => ['f09f9a80', "\u{1F680}"],
    ];
}

test('corpus text: each sequence decodes to a KeyDown with the expected char', function (): void {
    foreach (corpusTextEntries() as $label => [$bytesHex, $text]) {
        $raw = hex2bin($bytesHex);
        if ($raw === false) {
            throw new \RuntimeException("hex2bin failed for {$label}: {$bytesHex}");
        }

        $event = corpusDecodeOne($raw);
        $key = $event->asKey();

        if ($key === null) {
            throw new \RuntimeException("Expected a KeyDownEvent for '{$label}'");
        }

        expect($key->char)->toBe($text, "char mismatch for {$label}");
    }
});

// ---------------------------------------------------------------------------
// kind=mouse — SGR 1006 button press / release
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{string, int, int, int, bool}>
 *         [label => [bytesHex, buttonIndex, col1based, row1based, isPress]]
 */
function corpusMouseButtonEntries(): array
{
    return [
        'SGR1006 left-button press at (1,1)'     => ['1b5b3c303b313b314d',     0, 1,  1,  true],
        'SGR1006 left-button release at (1,1)'   => ['1b5b3c303b313b316d',     0, 1,  1,  false],
        'SGR1006 left-button press at (10,5)'    => ['1b5b3c303b31303b354d',   0, 10, 5,  true],
        'SGR1006 left-button release at (10,5)'  => ['1b5b3c303b31303b356d',   0, 10, 5,  false],
        'SGR1006 middle-button press at (40,12)' => ['1b5b3c313b34303b31324d', 1, 40, 12, true],
        'SGR1006 middle-button release at (40,12)' => ['1b5b3c313b34303b31326d', 1, 40, 12, false],
        'SGR1006 right-button press at (80,24)'  => ['1b5b3c323b38303b32344d', 2, 80, 24, true],
        'SGR1006 right-button release at (80,24)' => ['1b5b3c323b38303b32346d', 2, 80, 24, false],
        'SGR1006 right-button press at (1,1)'    => ['1b5b3c323b313b314d',     2, 1,  1,  true],
        'SGR1006 middle-button press at (1,1)'   => ['1b5b3c313b313b314d',     1, 1,  1,  true],
        'SGR1006 left-button press at large coords (200,50)' => ['1b5b3c303b3230303b35304d', 0, 200, 50, true],
    ];
}

test(
    'corpus mouse button: SGR press/release decodes to correct EventType, position, and button bit',
    function (): void {
        foreach (corpusMouseButtonEntries() as $label => [$bytesHex, $buttonIndex, $col1, $row1, $isPress]) {
            $raw = hex2bin($bytesHex);
            if ($raw === false) {
                throw new \RuntimeException("hex2bin failed for {$label}: {$bytesHex}");
            }

            $event = corpusDecodeOne($raw);
            $mouse = $event->asMouse();

            if ($mouse === null) {
                throw new \RuntimeException("Expected a MouseEvent for {$label}");
            }

            $expectedType = $isPress ? EventType::MouseDown : EventType::MouseUp;
            $expectedX = $col1 - 1;
            $expectedY = $row1 - 1;
            // MouseUp preserves the releasing button bit so callers can match it to the
            // prior MouseDown (fixes multi-button ambiguity; previously always 0 on release).
            $expectedButtons = 1 << $buttonIndex;

            expect($event->what)->toBe($expectedType, "{$label}: wrong EventType")
                ->and($mouse->where->x)->toBe($expectedX, "{$label}: wrong x")
                ->and($mouse->where->y)->toBe($expectedY, "{$label}: wrong y")
                ->and($mouse->buttons)->toBe($expectedButtons, "{$label}: wrong buttons");
        }
    }
);

// ---------------------------------------------------------------------------
// kind=mouse — SGR 1006 wheel events
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{string, int, int, int}>
 *         [label => [bytesHex, wheelDelta (-1=up +1=down), col1based, row1based]]
 */
function corpusMouseWheelEntries(): array
{
    return [
        'SGR1006 wheel-up at (20,10)'   => ['1b5b3c36343b32303b31304d', -1, 20, 10],
        'SGR1006 wheel-down at (20,10)' => ['1b5b3c36353b32303b31304d',  1, 20, 10],
        'SGR1006 wheel-up at (1,1)'     => ['1b5b3c36343b313b314d',     -1,  1,  1],
        'SGR1006 wheel-down at (80,24)' => ['1b5b3c36353b38303b32344d',  1, 80, 24],
    ];
}

test(
    'corpus mouse wheel: SGR wheel events decode to MouseMove with correct wheel delta and position',
    function (): void {
        foreach (corpusMouseWheelEntries() as $label => [$bytesHex, $wheelDelta, $col1, $row1]) {
            $raw = hex2bin($bytesHex);
            if ($raw === false) {
                throw new \RuntimeException("hex2bin failed for {$label}: {$bytesHex}");
            }

            $event = corpusDecodeOne($raw);
            $mouse = $event->asMouse();

            if ($mouse === null) {
                throw new \RuntimeException("Expected a MouseEvent for {$label}");
            }

            expect($event->what)->toBe(EventType::MouseMove, "{$label}: wrong EventType")
                ->and($mouse->where->x)->toBe($col1 - 1, "{$label}: wrong x")
                ->and($mouse->where->y)->toBe($row1 - 1, "{$label}: wrong y")
                ->and($mouse->wheel)->toBe($wheelDelta, "{$label}: wrong wheel delta");
        }
    }
);

// ---------------------------------------------------------------------------
// kind=unknown — raw Ctrl characters (no named Key enum case)
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{string, int}>
 *         [label => [bytesHex, expectedKeyCode]]
 */
function corpusCtrlEntries(): array
{
    return [
        'Ctrl-A'         => ['01', 0x01],
        'Ctrl-B'         => ['02', 0x02],
        'Ctrl-C'         => ['03', 0x03],
        'Ctrl-D'         => ['04', 0x04],
        'Ctrl-E'         => ['05', 0x05],
        'Ctrl-F'         => ['06', 0x06],
        'Ctrl-G (BEL)'   => ['07', 0x07],
        'Ctrl-K'         => ['0b', 0x0B],
        'Ctrl-L (FF)'    => ['0c', 0x0C],
        'Ctrl-N'         => ['0e', 0x0E],
        'Ctrl-O'         => ['0f', 0x0F],
        'Ctrl-P'         => ['10', 0x10],
        'Ctrl-Q'         => ['11', 0x11],
        'Ctrl-R'         => ['12', 0x12],
        'Ctrl-S'         => ['13', 0x13],
        'Ctrl-T'         => ['14', 0x14],
        'Ctrl-U'         => ['15', 0x15],
        'Ctrl-V'         => ['16', 0x16],
        'Ctrl-W'         => ['17', 0x17],
        'Ctrl-X'         => ['18', 0x18],
        'Ctrl-Y'         => ['19', 0x19],
        'Ctrl-Z'         => ['1a', 0x1A],
    ];
}

test(
    'corpus ctrl: raw control bytes decode to KeyDown with matching keyCode, no char, and no named Key case',
    function (): void {
        foreach (corpusCtrlEntries() as $label => [$bytesHex, $keyCode]) {
            $raw = hex2bin($bytesHex);
            if ($raw === false) {
                throw new \RuntimeException("hex2bin failed for {$label}: {$bytesHex}");
            }

            $event = corpusDecodeOne($raw);
            $key = $event->asKey();

            if ($key === null) {
                throw new \RuntimeException("Expected a KeyDownEvent for {$label}");
            }

            expect($event->what)->toBe(EventType::KeyDown, "{$label}: wrong EventType")
                ->and($key->keyCode)->toBe($keyCode, "{$label}: wrong keyCode")
                ->and($key->char)->toBe('', "{$label}: char should be empty")
                ->and(Key::tryFrom($keyCode))->toBeNull("{$label}: Ctrl char should have no named Key case");
        }
    }
);

// ---------------------------------------------------------------------------
// SKIPPED — corpus entries that contradict the decoder's documented contract
// ---------------------------------------------------------------------------

// Use it() (returns TestCall directly) so PHPStan can resolve ->skip() on the chain.
it(
    'skips corpus entry: Escape (standalone) — lone ESC is held as remainder per decoder contract',
    function (): void {
        // The corpus entry {bytesHex:"1b", kind:"key", keyName:"Esc"} expects Key::Esc
        // from a bare 0x1B byte. The EscapeDecoder's contract (documented in the plan and
        // confirmed by the EscapeDecoderTest suite) is that a lone trailing ESC is ALWAYS
        // returned as remainder — the decoder cannot know if more bytes follow until a
        // timeout fires. The caller must call flushPending("\e") after the timeout to
        // produce Key::Esc. This corpus entry conflates the timed-out path with the
        // inline-decode path, so it is skipped rather than distorting the decoder.
        $result = (new EscapeDecoder())->decode(hex2bin('1b') ?: '');
        expect($result->events)->toBe([])
            ->and($result->remainder)->toBe("\e");
    }
)->skip('Lone ESC is held as remainder by design; Key::Esc requires flushPending() after timeout');
