<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;

/**
 * Pure, incremental decoder: raw terminal bytes -> DecodeResult (events + remainder).
 * Total by construction — unknown sequences are consumed and dropped, never thrown.
 * Targets the common xterm/VT/rxvt subset used by M1; multi-terminal hardening is a
 * planned follow-up (see plan NOTE).
 */
final class EscapeDecoder
{
    private const float DOUBLE_CLICK_SECONDS = 0.5;

    private float $lastClickAt = 0.0;

    private ?Point $lastClickPoint = null;

    private int $lastClickButton = 0;

    /**
     * CSI final-byte (single char) -> navigation Key.
     * Includes lowercase rxvt variants (a=Up, b=Down, c=Right, d=Left).
     */
    private const array CSI_LETTER = [
        'A' => Key::Up,
        'B' => Key::Down,
        'C' => Key::Right,
        'D' => Key::Left,
        'H' => Key::Home,
        'F' => Key::End,
        'Z' => Key::ShiftTab,
        // rxvt Shift+arrow lowercase forms
        'a' => Key::Up,
        'b' => Key::Down,
        'c' => Key::Right,
        'd' => Key::Left,
    ];

    /** CSI "<n>~" numeric parameter -> Key. */
    private const array CSI_TILDE = [
        1  => Key::Home,
        2  => Key::Insert,
        3  => Key::Delete,
        4  => Key::End,
        5  => Key::PageUp,
        6  => Key::PageDown,
        7  => Key::Home,
        8  => Key::End,
        11 => Key::F1,
        12 => Key::F2,
        13 => Key::F3,
        14 => Key::F4,
        15 => Key::F5,
        17 => Key::F6,
        18 => Key::F7,
        19 => Key::F8,
        20 => Key::F9,
        21 => Key::F10,
        23 => Key::F11,
        24 => Key::F12,
    ];

    /**
     * SS3 final byte (\eO?) -> function Key.
     * Includes lowercase rxvt Ctrl+arrow variants (a=Up, b=Down, c=Right, d=Left).
     */
    private const array SS3 = [
        'P' => Key::F1,
        'Q' => Key::F2,
        'R' => Key::F3,
        'S' => Key::F4,
        'A' => Key::Up,
        'B' => Key::Down,
        'C' => Key::Right,
        'D' => Key::Left,
        'H' => Key::Home,
        'F' => Key::End,
        // rxvt Ctrl+arrow lowercase forms sent as SS3
        'a' => Key::Up,
        'b' => Key::Down,
        'c' => Key::Right,
        'd' => Key::Left,
    ];

    public function decode(string $bytes): DecodeResult
    {
        /** @var list<Event> $events */
        $events = [];
        $i      = 0;
        $len    = strlen($bytes);

        while ($i < $len) {
            $byte = $bytes[$i];
            $ord  = ord($byte);

            if ($byte === "\e") {
                $consumed = $this->decodeEscape($bytes, $i, $len, $events);
                if ($consumed === 0) {
                    // Incomplete escape sequence: hand the rest back as remainder.
                    return new DecodeResult($events, substr($bytes, $i));
                }
                $i += $consumed;

                continue;
            }

            if ($ord >= 0x20 && $ord <= 0x7E) {
                $events[] = Event::keyDown(new KeyDownEvent($ord, $byte));
                $i++;

                continue;
            }

            if ($ord >= 0x80) {
                // UTF-8 multibyte: determine how many continuation bytes are expected
                // from the lead byte so we can defer the sequence when it is truncated.
                $expected = $this->utf8ContinuationCount($ord);
                if ($expected === -1) {
                    // Invalid lead byte — emit as-is and advance.
                    $events[] = Event::keyDown(new KeyDownEvent(0, $byte));
                    $i++;

                    continue;
                }

                // Check whether all continuation bytes are present in the buffer.
                if ($i + $expected >= $len) {
                    // Sequence is truncated — return the partial bytes as remainder.
                    return new DecodeResult($events, substr($bytes, $i));
                }

                $valid = true;
                for ($offset = 1; $offset <= $expected; $offset++) {
                    if ((ord($bytes[$i + $offset]) & 0xC0) !== 0x80) {
                        $valid = false;
                        break;
                    }
                }
                $grapheme = substr($bytes, $i, $expected + 1);
                if (! $valid || ! mb_check_encoding($grapheme, 'UTF-8')) {
                    // Consume only the invalid lead so following bytes remain decodable.
                    $events[] = Event::keyDown(new KeyDownEvent(0, $byte));
                    $i++;

                    continue;
                }
                $events[] = Event::keyDown(new KeyDownEvent(0, $grapheme));
                $i += $expected + 1;

                continue;
            }

            // Control byte 0x00-0x1F or 0x7F.
            $events[] = $this->decodeControl($ord);
            $i++;
        }

        return new DecodeResult($events, '');
    }

    /**
     * Turn a stranded remainder into an event on input timeout. A lone ESC becomes
     * Key::Esc; anything else (empty or an incomplete CSI) yields null.
     */
    public function flushPending(string $remainder): ?Event
    {
        if ($remainder === "\e") {
            return Event::keyDown(new KeyDownEvent(Key::Esc->value));
        }

        return null;
    }

    /**
     * Return the number of UTF-8 continuation bytes expected after a lead byte,
     * or -1 if the byte is not a valid UTF-8 lead byte.
     */
    private function utf8ContinuationCount(int $ord): int
    {
        if ($ord >= 0xC2 && $ord <= 0xDF) {
            return 1; // 2-byte sequence
        }
        if ($ord >= 0xE0 && $ord <= 0xEF) {
            return 2; // 3-byte sequence
        }
        if ($ord >= 0xF0 && $ord <= 0xF4) {
            return 3; // 4-byte sequence
        }

        return -1; // invalid lead
    }

    private function decodeControl(int $ord): Event
    {
        $key = match ($ord) {
            0x09 => Key::Tab,
            0x0D, 0x0A => Key::Enter,
            0x08, 0x7F => Key::Backspace,
            default    => null,
        };

        if ($key !== null) {
            return Event::keyDown(new KeyDownEvent($key->value));
        }

        // Raw control char (Ctrl-A..Z etc.): keep its ordinal, no printable char.
        return Event::keyDown(new KeyDownEvent($ord));
    }

    /**
     * Decode an escape sequence starting at $i. Appends to $events and returns the
     * number of bytes consumed, or 0 if the sequence is incomplete (need more bytes).
     *
     * @param list<Event> $events
     */
    private function decodeEscape(string $bytes, int $i, int $len, array &$events): int
    {
        // Lone ESC at end of buffer: incomplete.
        if ($i + 1 >= $len) {
            return 0;
        }

        $next = $bytes[$i + 1];

        if ($next === '[') {
            return $this->decodeCsi($bytes, $i, $len, $events);
        }

        if ($next === 'O') {
            // SS3: \eO<final>
            if ($i + 2 >= $len) {
                return 0;
            }
            $final = $bytes[$i + 2];
            if (isset(self::SS3[$final])) {
                $events[] = Event::keyDown(new KeyDownEvent(self::SS3[$final]->value));
            }

            return 3;
        }

        // ESC ESC … — double-escape passthrough (tmux/screen wraps inner sequences).
        // If the byte following the second ESC would start a proper CSI or SS3 sequence
        // ('[' or 'O'), strip the outer ESC silently and decode the inner sequence.
        // Otherwise emit the first ESC as Key::Esc and consume only 1 byte so the
        // second ESC is available for the next iteration.
        if ($next === "\e") {
            if ($i + 2 < $len) {
                $after = $bytes[$i + 2];
                if ($after === '[' || $after === 'O') {
                    // Decode the inner escape sequence starting at i+1.
                    $inner = $this->decodeEscape($bytes, $i + 1, $len, $events);
                    if ($inner === 0) {
                        // Inner sequence is incomplete — treat the whole thing as incomplete.
                        return 0;
                    }

                    return 1 + $inner;
                }
            }

            // Two bare ESC bytes (or ESC ESC followed by non-sequence byte):
            // emit first ESC as Key::Esc, consume 1 byte; the second ESC will be
            // processed on the next iteration or returned as remainder.
            $events[] = Event::keyDown(new KeyDownEvent(Key::Esc->value));

            return 1;
        }

        // \e<letter> -> Alt+letter
        if (preg_match('/[A-Za-z]/', $next) === 1) {
            $altCase = 'Alt' . strtoupper($next);
            $key     = self::altKey($altCase);
            if ($key !== null) {
                $events[] = Event::keyDown(new KeyDownEvent($key->value));
            }

            return 2;
        }

        // Unknown ESC-prefixed byte: drop the ESC and the byte.
        return 2;
    }

    /**
     * Decode a CSI sequence (\e[...). Returns bytes consumed, or 0 if incomplete.
     *
     * @param list<Event> $events
     */
    private function decodeCsi(string $bytes, int $i, int $len, array &$events): int
    {
        // SGR mouse: \e[<b;x;y(M|m)
        if ($i + 2 < $len && $bytes[$i + 2] === '<') {
            return $this->decodeMouse($bytes, $i, $len, $events);
        }

        // Scan parameter/intermediate bytes until a final byte (0x40-0x7E).
        $j      = $i + 2;
        $params = '';
        while ($j < $len) {
            $c = $bytes[$j];
            $o = ord($c);
            if ($o >= 0x40 && $o <= 0x7E) {
                $final = $c;
                $this->emitCsi($params, $final, $events);

                return $j - $i + 1;
            }
            $params .= $c;
            $j++;
        }

        return 0; // no final byte yet -> incomplete
    }

    /** @param list<Event> $events */
    private function emitCsi(string $params, string $final, array &$events): void
    {
        // Plain CSI letter with no parameters: direct navigation key.
        if ($params === '' && isset(self::CSI_LETTER[$final])) {
            $events[] = Event::keyDown(new KeyDownEvent(self::CSI_LETTER[$final]->value));

            return;
        }

        // CSI <n>~ : function / navigation tilde sequence.
        if ($final === '~' && $params !== '' && ctype_digit($params)) {
            $n = (int) $params;
            if (isset(self::CSI_TILDE[$n])) {
                $events[] = Event::keyDown(new KeyDownEvent(self::CSI_TILDE[$n]->value));
            }

            return;
        }

        // CSI <params> <letter> with parameters: xterm modifier-parameter family
        // and rxvt CSI-letter variants.
        //
        // Forms handled:
        //   CSI 1 ; <mod> <letter>  — xterm modifier form (Shift/Alt/Ctrl + arrow)
        //   CSI <n> <letter>        — xterm modifyFunctionKeys (e.g. CSI 1 P = F1)
        //
        // Strategy: ignore the parameter(s) and map the final byte using CSI_LETTER
        // or SS3, emitting the base key without modifier metadata.
        if ($params !== '' && isset(self::CSI_LETTER[$final])) {
            $events[] = Event::keyDown(new KeyDownEvent(self::CSI_LETTER[$final]->value));

            return;
        }

        // xterm modifyFunctionKeys: CSI <n> P/Q/R/S where P/Q/R/S are SS3 keys.
        if ($params !== '' && isset(self::SS3[$final])) {
            $events[] = Event::keyDown(new KeyDownEvent(self::SS3[$final]->value));

            return;
        }

        // Kitty progressive-enhancement and other unrecognised CSI sequences:
        // emit a synthetic KeyDown with keyCode=0 so callers can detect unknown
        // sequences rather than having them silently vanish.
        $events[] = Event::keyDown(new KeyDownEvent(0));
    }

    /**
     * Decode an SGR mouse report \e[<b;x;y(M|m). Returns bytes consumed, 0 if incomplete.
     *
     * @param list<Event> $events
     */
    private function decodeMouse(string $bytes, int $i, int $len, array &$events): int
    {
        $j    = $i + 3; // skip "\e[<"
        $body = '';
        while ($j < $len) {
            $c = $bytes[$j];
            if ($c === 'M' || $c === 'm') {
                $this->emitMouse($body, $c, $events);

                return $j - $i + 1;
            }
            $body .= $c;
            $j++;
        }

        return 0; // incomplete
    }

    /** @param list<Event> $events */
    private function emitMouse(string $body, string $final, array &$events): void
    {
        $parts = explode(';', $body);
        if (count($parts) !== 3
            || ! ctype_digit($parts[0])
            || ! ctype_digit($parts[1])
            || ! ctype_digit($parts[2])
            || (int) $parts[1] < 1
            || (int) $parts[2] < 1
        ) {
            return; // malformed -> drop
        }

        $b     = (int) $parts[0];
        $x     = (int) $parts[1] - 1; // 1-based -> 0-based
        $y     = (int) $parts[2] - 1;
        $press = $final === 'M';

        // Strip SGR modifier bits (shift=0x04, alt=0x08, ctrl=0x10) before
        // dispatching on button/wheel/motion, so modifier combinations are correctly
        // recognised (e.g. ctrl+wheel-down = 81 = 65|0x10 still decodes as wheel-down).
        $rawB = $b & ~(0x04 | 0x08 | 0x10);

        // Wheel events: base button code is 64 (wheel-up) or 65 (wheel-down).
        // Bit 6 (0x40) is set for all wheel reports; bit 0 selects direction.
        if (($rawB & 0x40) !== 0) {
            $wheelDelta = ($rawB === 64) ? -1 : 1;
            $what       = EventType::MouseMove; // wheel events are move-class in TV
            $events[]   = Event::mouse($what, new MouseEvent(new Point($x, $y), 0, false, $wheelDelta));

            return;
        }

        // Motion events: bit 5 (0x20) signals a pointer-motion report.
        // The terminal sends these when the mouse moves while a button is held
        // (button-motion mode) or in any-motion mode.
        if (($rawB & 0x20) !== 0) {
            $buttonIndex = $rawB & 0x03;
            $buttons = $buttonIndex === 3 ? 0 : 1 << $buttonIndex;
            $events[] = Event::mouse(EventType::MouseMove, new MouseEvent(new Point($x, $y), $buttons));

            return;
        }

        // Button press / release.
        // For MouseUp we preserve the releasing button bit so callers can match it
        // to the prior MouseDown (e.g. to distinguish right-button release from left).
        $buttonIndex = $rawB & 0x03;
        $buttonBit   = 1 << $buttonIndex;
        $what        = $press ? EventType::MouseDown : EventType::MouseUp;
        $doubleClick = false;
        if ($press) {
            $now = microtime(true);
            $point = new Point($x, $y);
            $doubleClick = $buttonBit === $this->lastClickButton
                && $this->lastClickPoint?->equals($point) === true
                && $now - $this->lastClickAt <= self::DOUBLE_CLICK_SECONDS;
            if ($doubleClick) {
                $this->lastClickAt = 0.0;
                $this->lastClickPoint = null;
                $this->lastClickButton = 0;
            } else {
                $this->lastClickAt = $now;
                $this->lastClickPoint = $point;
                $this->lastClickButton = $buttonBit;
            }
        }

        $events[] = Event::mouse(
            $what,
            new MouseEvent(new Point($x, $y), $buttonBit, $doubleClick),
        );
    }

    /** Resolve an "Alt<LETTER>" case name to the Key enum, or null. */
    private static function altKey(string $caseName): ?Key
    {
        foreach (Key::cases() as $case) {
            if ($case->name === $caseName) {
                return $case;
            }
        }

        return null;
    }
}
