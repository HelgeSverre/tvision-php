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
 * Targets the common xterm/VT subset used by M1; multi-terminal hardening is a
 * planned follow-up (see plan NOTE).
 */
final class EscapeDecoder
{
    /** CSI final-byte (single char) -> navigation Key. */
    private const array CSI_LETTER = [
        'A' => Key::Up,
        'B' => Key::Down,
        'C' => Key::Right,
        'D' => Key::Left,
        'H' => Key::Home,
        'F' => Key::End,
        'Z' => Key::ShiftTab,
    ];

    /** CSI "<n>~" numeric parameter -> Key. */
    private const array CSI_TILDE = [
        1 => Key::Home,
        2 => Key::Insert,
        3 => Key::Delete,
        4 => Key::End,
        5 => Key::PageUp,
        6 => Key::PageDown,
        7 => Key::Home,
        8 => Key::End,
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

    /** SS3 final byte (\eO?) -> function Key. */
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
    ];

    public function decode(string $bytes): DecodeResult
    {
        /** @var list<Event> $events */
        $events = [];
        $i = 0;
        $len = strlen($bytes);

        while ($i < $len) {
            $byte = $bytes[$i];
            $ord = ord($byte);

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
                // UTF-8 multibyte lead: gather continuation bytes (0x80-0xBF).
                $j = $i + 1;
                while ($j < $len && (ord($bytes[$j]) & 0xC0) === 0x80) {
                    $j++;
                }
                $grapheme = substr($bytes, $i, $j - $i);
                $events[] = Event::keyDown(new KeyDownEvent(0, $grapheme));
                $i = $j;

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

    private function decodeControl(int $ord): Event
    {
        $key = match ($ord) {
            0x09 => Key::Tab,
            0x0D, 0x0A => Key::Enter,
            0x08, 0x7F => Key::Backspace,
            default => null,
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

        // \e<letter> -> Alt+letter
        if (preg_match('/[A-Za-z]/', $next) === 1) {
            $altCase = 'Alt' . strtoupper($next);
            $key = self::altKey($altCase);
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
        $j = $i + 2;
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
        if ($params === '' && isset(self::CSI_LETTER[$final])) {
            $events[] = Event::keyDown(new KeyDownEvent(self::CSI_LETTER[$final]->value));

            return;
        }

        if ($final === '~' && $params !== '' && ctype_digit($params)) {
            $n = (int) $params;
            if (isset(self::CSI_TILDE[$n])) {
                $events[] = Event::keyDown(new KeyDownEvent(self::CSI_TILDE[$n]->value));
            }
        }

        // else: unknown CSI -> consumed and dropped (total decoder).
    }

    /**
     * Decode an SGR mouse report \e[<b;x;y(M|m). Returns bytes consumed, 0 if incomplete.
     *
     * @param list<Event> $events
     */
    private function decodeMouse(string $bytes, int $i, int $len, array &$events): int
    {
        $j = $i + 3; // skip "\e[<"
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
        if (count($parts) !== 3) {
            return; // malformed -> drop
        }

        $b = (int) $parts[0];
        $x = (int) $parts[1] - 1; // 1-based -> 0-based
        $y = (int) $parts[2] - 1;
        $press = $final === 'M';

        // Wheel events: b=64 (wheel-up), b=65 (wheel-down)
        if ($b === 64 || $b === 65) {
            $wheelDelta = $b === 64 ? -1 : 1;
            $what = EventType::MouseMove; // wheel events are move-class in TV
            $events[] = Event::mouse($what, new MouseEvent(new Point($x, $y), 0, false, $wheelDelta));

            return;
        }

        // Low 2 bits of $b select the button (0=left, 1=middle, 2=right).
        $buttonBit = $press ? (1 << ($b & 0x03)) : 0;
        $what = $press ? EventType::MouseDown : EventType::MouseUp;

        $events[] = Event::mouse($what, new MouseEvent(new Point($x, $y), $buttonBit));
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
