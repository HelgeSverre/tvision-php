<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use Closure;

/**
 * Pure, incremental decoder: raw terminal bytes -> DecodeResult (events + remainder).
 * Total by construction — unknown sequences are consumed and dropped, never thrown.
 * Supports classic xterm sequences, SGR mouse input, and the Kitty keyboard
 * protocol while safely consuming terminal replies and unsupported sequences.
 */
final class EscapeDecoder
{
    private const float DOUBLE_CLICK_SECONDS = 0.5;

    /** Base key -> Shift-combined legacy identity, keyed by enum-case name. */
    private const array SHIFT_COMBINED = [
        'Tab' => Key::ShiftTab,
        'Insert' => Key::ShiftInsert,
        'Delete' => Key::ShiftDelete,
        'F1' => Key::ShiftF1,
        'F2' => Key::ShiftF2,
        'F3' => Key::ShiftF3,
        'F4' => Key::ShiftF4,
        'F5' => Key::ShiftF5,
        'F6' => Key::ShiftF6,
        'F7' => Key::ShiftF7,
        'F8' => Key::ShiftF8,
        'F9' => Key::ShiftF9,
        'F10' => Key::ShiftF10,
    ];

    /** Base key -> Ctrl-combined legacy identity, keyed by enum-case name. */
    private const array CTRL_COMBINED = [
        'Enter' => Key::CtrlEnter,
        'Backspace' => Key::CtrlBackspace,
        'Insert' => Key::CtrlInsert,
        'Delete' => Key::CtrlDelete,
        'Left' => Key::CtrlLeft,
        'Right' => Key::CtrlRight,
        'End' => Key::CtrlEnd,
        'PageDown' => Key::CtrlPageDown,
        'Home' => Key::CtrlHome,
        'PageUp' => Key::CtrlPageUp,
        'F1' => Key::CtrlF1,
        'F2' => Key::CtrlF2,
        'F3' => Key::CtrlF3,
        'F4' => Key::CtrlF4,
        'F5' => Key::CtrlF5,
        'F6' => Key::CtrlF6,
        'F7' => Key::CtrlF7,
        'F8' => Key::CtrlF8,
        'F9' => Key::CtrlF9,
        'F10' => Key::CtrlF10,
    ];

    /** Base key -> Alt-combined legacy identity, keyed by enum-case name. */
    private const array ALT_COMBINED = [
        'F1' => Key::AltF1,
        'F2' => Key::AltF2,
        'F3' => Key::AltF3,
        'F4' => Key::AltF4,
        'F5' => Key::AltF5,
        'F6' => Key::AltF6,
        'F7' => Key::AltF7,
        'F8' => Key::AltF8,
        'F9' => Key::AltF9,
        'F10' => Key::AltF10,
    ];

    /** @var Closure():float Monotonic seconds; injectable for deterministic double-click tests. */
    private readonly Closure $clock;

    private float $lastClickAt = 0.0;

    private ?Point $lastClickPoint = null;

    private int $lastClickButton = 0;

    /**
     * @param (Closure():float)|null $clock Monotonic seconds; defaults to hrtime.
     */
    public function __construct(?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000_000;
    }

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

    /**
     * Kitty's all-keys-as-escape-codes enhancement represents F1..F12 using
     * private-use code points. Earlier functional keys retain their legacy
     * CSI/SS3 representations, so this is the first range we can faithfully
     * expose through Turbo Vision's public Key enum.
     *
     * @see https://sw.kovidgoyal.net/kitty/keyboard-protocol/#functional-key-definitions
     */
    private const array KITTY_FUNCTIONAL = [
        57364 => Key::F1,
        57365 => Key::F2,
        57366 => Key::F3,
        57367 => Key::F4,
        57368 => Key::F5,
        57369 => Key::F6,
        57370 => Key::F7,
        57371 => Key::F8,
        57372 => Key::F9,
        57373 => Key::F10,
        57374 => Key::F11,
        57375 => Key::F12,
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
        return $this->flushPendingEvents($remainder)[0] ?? null;
    }

    /**
     * Turn an ambiguous remainder into key presses after the caller's
     * inter-fragment timeout. Keeping input pending makes decoding invariant to
     * read boundaries without losing rapid consecutive Esc presses:
     *
     * - a pure ESC run becomes one Esc press per byte;
     * - a double-ESC prefix held for wrap disambiguation (e.g. "\e\e[") resolves
     *   to independent Esc presses, dropping the incomplete inner-sequence tail;
     * - a single ESC followed by a fragment (e.g. "\e[") is the head of a real
     *   sequence that never completed and stays dropped.
     *
     * @return list<Event>
     */
    public function flushPendingEvents(string $remainder): array
    {
        if ($remainder === '') {
            return [];
        }

        $escRun = strspn($remainder, "\e");
        $isPureRun = $escRun === strlen($remainder);
        if (! $isPureRun && $escRun < 2) {
            return [];
        }

        $events = [];
        for ($i = 0; $i < $escRun; $i++) {
            $events[] = Event::key(Key::Esc);
        }

        return $events;
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
                // rxvt encodes Ctrl+arrows as lowercase SS3 final bytes.
                $modifiers = ctype_lower($final) ? KeyModifier::Ctrl : KeyModifier::None;
                $events[] = $this->legacyKeyEvent(self::SS3[$final], $modifiers);
            }

            return 3;
        }

        // ECMA-48 control strings. Terminal replies (OSC, DCS, SOS, PM, APC)
        // are protocol traffic, not keyboard input; consume them through BEL or ST
        // so their payload cannot leak into the application as typed characters.
        if (str_contains(']PX^_', $next)) {
            return $this->decodeControlString($bytes, $i, $len, $next === ']');
        }

        // ESC ESC … — double-escape passthrough (tmux/screen wraps inner sequences).
        // If the byte following the second ESC would start a proper CSI or SS3 sequence
        // ('[' or 'O'), strip the outer ESC silently and decode the inner sequence.
        // Otherwise emit the first ESC as Key::Esc and consume only 1 byte so the
        // second ESC is available for the next iteration.
        if ($next === "\e") {
            // The third byte decides whether this is a wrapped sequence or two
            // independent Esc presses. Preserve both until it arrives (or timeout).
            if ($i + 2 >= $len) {
                return 0;
            }

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

            // Two bare ESC bytes (or ESC ESC followed by non-sequence byte):
            // emit first ESC as Key::Esc, consume 1 byte; the second ESC will be
            // processed on the next iteration or returned as remainder.
            $events[] = Event::key(Key::Esc);

            return 1;
        }

        // Legacy Alt keypresses are sent as ESC followed by their base byte.
        // Preserve Turbo Vision's combined identities, including Alt+digits
        // used for desktop window selection.
        if (($key = self::altCharacterKey($next)) !== null) {
            $events[] = $this->legacyKeyEvent($key, KeyModifier::Alt);

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
            // rxvt encodes Shift+arrows as lowercase CSI final bytes.
            $modifiers = ctype_lower($final) ? KeyModifier::Shift : KeyModifier::None;
            $events[] = $this->legacyKeyEvent(self::CSI_LETTER[$final], $modifiers);

            return;
        }

        // CSI <n>~ : function / navigation tilde sequence.
        if ($final === '~') {
            [$n, $modifiers] = $this->legacyKeyParameters($params);
            if ($n !== null && isset(self::CSI_TILDE[$n])) {
                $events[] = $this->legacyKeyEvent(self::CSI_TILDE[$n], $modifiers);
            }

            return;
        }

        if ($final === 'u') {
            $this->emitKittyKey($params, $events);

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
            [$prefix, $modifiers] = $this->legacyKeyParameters($params);
            if ($prefix === 1) {
                $events[] = $this->legacyKeyEvent(self::CSI_LETTER[$final], $modifiers);
            }

            return;
        }

        // xterm/Kitty F1, F2 and F4 use CSI 1[;modifier] P/Q/S. CSI R is
        // deliberately excluded: it is a cursor-position report, not F3.
        if ($params !== '' && $final !== 'R' && isset(self::SS3[$final])) {
            [$prefix, $modifiers] = $this->legacyKeyParameters($params);
            if ($prefix === 1) {
                $events[] = $this->legacyKeyEvent(self::SS3[$final], $modifiers);
            }

            return;
        }

        // Device replies and unsupported CSI extensions are intentionally ignored.
        // Converting protocol traffic into a synthetic key can activate whichever
        // control happens to treat keyCode=0 as meaningful.
    }

    /** @return array{?int, int} [key parameter, decoded modifier bitmask] */
    private function legacyKeyParameters(string $params): array
    {
        if (preg_match('/^(\d+)(?:;(\d+))?$/D', $params, $matches) !== 1) {
            return [null, 0];
        }

        $encodedModifiers = isset($matches[2]) ? (int) $matches[2] : 1;
        if ($encodedModifiers < 1 || $encodedModifiers > 256) {
            return [null, 0];
        }

        return [(int) $matches[1], $encodedModifiers - 1];
    }

    /** @param list<Event> $events */
    private function emitKittyKey(string $params, array &$events): void
    {
        $fields = explode(';', $params);
        if (count($fields) > 3 || $fields[0] === '') {
            return;
        }

        $keyParts = explode(':', $fields[0]);
        if (count($keyParts) > 3
            || ! $this->decimalFields($keyParts, allowEmpty: true)
            || $keyParts[0] === ''
        ) {
            return;
        }
        $codepoint = (int) $keyParts[0];

        $modifierParts = isset($fields[1]) && $fields[1] !== '' ? explode(':', $fields[1]) : ['1'];
        if (! $this->decimalFields($modifierParts, allowEmpty: false) || count($modifierParts) > 2) {
            return;
        }
        $encodedModifiers = (int) $modifierParts[0];
        $eventType = isset($modifierParts[1]) ? (int) $modifierParts[1] : 1;
        if ($encodedModifiers < 1 || $encodedModifiers > 256 || ! in_array($eventType, [1, 2, 3], true)) {
            return;
        }
        if ($eventType === 3) {
            return; // KeyDownEvent cannot represent releases; safe fallback is to ignore them.
        }
        $modifiers = $encodedModifiers - 1;

        $selectedCodepoint = $codepoint;
        if (($modifiers & KeyModifier::Shift) !== 0 && isset($keyParts[1]) && $keyParts[1] !== '') {
            $selectedCodepoint = (int) $keyParts[1];
        }

        $text = '';
        if (isset($fields[2]) && $fields[2] !== '') {
            $textParts = explode(':', $fields[2]);
            if (! $this->decimalFields($textParts, allowEmpty: false)) {
                return;
            }
            foreach ($textParts as $textCodepoint) {
                $character = $this->unicodeCharacter((int) $textCodepoint);
                if ($character === null || preg_match('/[\p{Cc}\p{Cs}]/u', $character) === 1) {
                    return;
                }
                $text .= $character;
            }
        }

        $special = self::KITTY_FUNCTIONAL[$codepoint] ?? match ($codepoint) {
            9 => Key::Tab,
            13 => Key::Enter,
            27 => Key::Esc,
            127 => Key::Backspace,
            default => null,
        };
        if ($special !== null) {
            $events[] = $this->legacyKeyEvent($special, $modifiers);

            return;
        }

        // Unknown private-use key codes describe functional keys the current public
        // Key enum cannot express. Ignore them instead of inventing a printable key.
        if ($codepoint >= 0xE000 && $codepoint <= 0xF8FF) {
            return;
        }

        // CSI-u disambiguation replaces the legacy byte sequences used by the
        // framework's public shortcut API. Preserve that API at the decoder
        // boundary while retaining modifier metadata for modern consumers.
        $shortcutCodepoint = isset($keyParts[2]) && $keyParts[2] !== ''
            ? (int) $keyParts[2]
            : $codepoint;
        if (($modifiers & KeyModifier::Ctrl) !== 0
            && (($shortcutCodepoint >= ord('A') && $shortcutCodepoint <= ord('Z'))
                || ($shortcutCodepoint >= ord('a') && $shortcutCodepoint <= ord('z')))
        ) {
            $controlCode = ord(strtoupper(chr($shortcutCodepoint))) - ord('@');
            $events[] = Event::keyDown(new KeyDownEvent($controlCode, '', $modifiers));

            return;
        }

        if (($modifiers & KeyModifier::Alt) !== 0
            && $shortcutCodepoint >= 0
            && $shortcutCodepoint <= 0x7F
        ) {
            $altKey = self::altCharacterKey(chr($shortcutCodepoint));
            if ($altKey !== null) {
                $events[] = $this->legacyKeyEvent($altKey, $modifiers);

                return;
            }
        }

        if ($text === '') {
            $text = $this->unicodeCharacter($selectedCodepoint) ?? '';
            if ($text !== '' && preg_match('/[\p{Cc}\p{Cs}]/u', $text) === 1) {
                $text = '';
            }
        }

        if ($codepoint === 0 && $text === '') {
            return;
        }

        $events[] = Event::keyDown(new KeyDownEvent(
            $selectedCodepoint >= 0x20 && $selectedCodepoint <= 0x7E ? $selectedCodepoint : 0,
            $text,
            $modifiers,
        ));
    }

    /** @param list<string> $fields */
    private function decimalFields(array $fields, bool $allowEmpty): bool
    {
        foreach ($fields as $field) {
            if ($field === '' && $allowEmpty) {
                continue;
            }
            if ($field === '' || strlen($field) > 7 || ! ctype_digit($field)) {
                return false;
            }
        }

        return true;
    }

    private function unicodeCharacter(int $codepoint): ?string
    {
        if ($codepoint < 0 || $codepoint > 0x10FFFF || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            return null;
        }

        return mb_chr($codepoint, 'UTF-8');
    }

    /**
     * Preserve Turbo Vision's historical combined key identities when the
     * modern terminal protocol describes the same unambiguous keypress as a
     * base key plus modifiers. Modifier metadata is kept intact for callers
     * that need the full modern input state.
     */
    private function legacyKeyEvent(Key $key, int $modifiers): Event
    {
        return Event::keyDown(new KeyDownEvent(
            $this->legacyKeyCode($key, $modifiers),
            modifiers: $modifiers,
        ));
    }

    private function legacyKeyCode(Key $key, int $modifiers): int
    {
        // Lock and platform-modifier bits do not alter Turbo Vision's kbXxx
        // identity. Only normalize an exact Shift, Alt, or Ctrl counterpart;
        // combinations such as Ctrl+Shift+F5 deliberately retain the base key
        // plus full metadata because no historical combined code exists.
        $primary = $modifiers & (KeyModifier::Shift | KeyModifier::Alt | KeyModifier::Ctrl);

        $table = match ($primary) {
            KeyModifier::Shift => self::SHIFT_COMBINED,
            KeyModifier::Ctrl => self::CTRL_COMBINED,
            KeyModifier::Alt => self::ALT_COMBINED,
            default => [],
        };

        return ($table[$key->name] ?? $key)->value;
    }

    /** Consume OSC/DCS/SOS/PM/APC through BEL (OSC only) or the ESC \\ ST terminator. */
    private function decodeControlString(string $bytes, int $i, int $len, bool $allowBel): int
    {
        for ($j = $i + 2; $j < $len; $j++) {
            if ($allowBel && $bytes[$j] === "\x07") {
                return $j - $i + 1;
            }
            if ($bytes[$j] === "\e") {
                if ($j + 1 >= $len) {
                    return 0;
                }
                if ($bytes[$j + 1] === '\\') {
                    return $j - $i + 2;
                }
            }
        }

        return 0;
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
            $now = ($this->clock)();
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

    /** Resolve a byte participating in a legacy Alt sequence to a Key, or null. */
    private static function altCharacterKey(string $character): ?Key
    {
        return match ($character) {
            '0' => Key::Alt0,
            '1' => Key::Alt1,
            '2' => Key::Alt2,
            '3' => Key::Alt3,
            '4' => Key::Alt4,
            '5' => Key::Alt5,
            '6' => Key::Alt6,
            '7' => Key::Alt7,
            '8' => Key::Alt8,
            '9' => Key::Alt9,
            '-' => Key::AltMinus,
            '=' => Key::AltEqual,
            ' ' => Key::AltSpace,
            "\x08", "\x7f" => Key::AltBackspace,
            default => preg_match('/^[A-Za-z]$/D', $character) === 1
                ? self::altKey('Alt' . strtoupper($character))
                : null,
        };
    }

    /** @var array<string,Key>|null enum-case name -> Key, built once */
    private static ?array $keysByName = null;

    /** Resolve an "Alt<LETTER>" case name to the Key enum, or null. */
    private static function altKey(string $caseName): ?Key
    {
        self::$keysByName ??= array_combine(
            array_map(static fn (Key $case): string => $case->name, Key::cases()),
            Key::cases(),
        );

        return self::$keysByName[$caseName] ?? null;
    }
}
