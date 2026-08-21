<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;

/**
 * Decode $bytes and return the single resulting KeyDownEvent (fails the test if the
 * decode produced anything other than exactly one key event).
 */
function decodeOneKey(string $bytes): \HelgeSverre\TurboVision\Events\KeyDownEvent
{
    $result = (new EscapeDecoder())->decode($bytes);
    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');
    $key = $result->events[0]->asKey();
    expect($key)->not->toBeNull();

    if ($key === null) {
        throw new \RuntimeException('Expected a KeyDownEvent but got null');
    }

    return $key;
}

test('printable ASCII becomes a KeyDown carrying the character', function (): void {
    $k = decodeOneKey('a');

    expect($k->char)->toBe('a')
        ->and($k->keyCode)->toBe(0x61);
});

test('a UTF-8 multibyte grapheme decodes to one KeyDown', function (): void {
    $k = decodeOneKey('é'); // 0xC3 0xA9

    expect($k->char)->toBe('é')
        ->and($k->keyCode)->toBe(0);
});

test('a string of printables decodes to one event each', function (): void {
    $result = (new EscapeDecoder())->decode('Hi!');

    expect($result->events)->toHaveCount(3)
        ->and($result->events[0]->asKey()?->char)->toBe('H')
        ->and($result->events[1]->asKey()?->char)->toBe('i')
        ->and($result->events[2]->asKey()?->char)->toBe('!');
});

test('control codes map to special keys or ctrl chars', function (): void {
    expect(decodeOneKey("\t")->is(Key::Tab))->toBeTrue()
        ->and(decodeOneKey("\r")->is(Key::Enter))->toBeTrue()
        ->and(decodeOneKey("\n")->is(Key::Enter))->toBeTrue()
        ->and(decodeOneKey("\x7F")->is(Key::Backspace))->toBeTrue()
        ->and(decodeOneKey("\x08")->is(Key::Backspace))->toBeTrue();
    // Ctrl-A is 0x01, kept as a raw control keyCode with no char
    expect(decodeOneKey("\x01")->keyCode)->toBe(0x01)
        ->and(decodeOneKey("\x01")->char)->toBe('');
});

test('CSI arrows decode to the navigation keys', function (): void {
    expect(decodeOneKey("\e[A")->is(Key::Up))->toBeTrue()
        ->and(decodeOneKey("\e[B")->is(Key::Down))->toBeTrue()
        ->and(decodeOneKey("\e[C")->is(Key::Right))->toBeTrue()
        ->and(decodeOneKey("\e[D")->is(Key::Left))->toBeTrue();
});

test('Home/End/nav decode from letter and tilde forms', function (): void {
    expect(decodeOneKey("\e[H")->is(Key::Home))->toBeTrue()
        ->and(decodeOneKey("\e[F")->is(Key::End))->toBeTrue()
        ->and(decodeOneKey("\e[1~")->is(Key::Home))->toBeTrue()
        ->and(decodeOneKey("\e[2~")->is(Key::Insert))->toBeTrue()
        ->and(decodeOneKey("\e[3~")->is(Key::Delete))->toBeTrue()
        ->and(decodeOneKey("\e[4~")->is(Key::End))->toBeTrue()
        ->and(decodeOneKey("\e[5~")->is(Key::PageUp))->toBeTrue()
        ->and(decodeOneKey("\e[6~")->is(Key::PageDown))->toBeTrue();
});

test('function keys decode from SS3 and tilde forms', function (): void {
    expect(decodeOneKey("\eOP")->is(Key::F1))->toBeTrue()
        ->and(decodeOneKey("\eOQ")->is(Key::F2))->toBeTrue()
        ->and(decodeOneKey("\eOR")->is(Key::F3))->toBeTrue()
        ->and(decodeOneKey("\eOS")->is(Key::F4))->toBeTrue()
        ->and(decodeOneKey("\e[15~")->is(Key::F5))->toBeTrue()
        ->and(decodeOneKey("\e[17~")->is(Key::F6))->toBeTrue()
        ->and(decodeOneKey("\e[21~")->is(Key::F10))->toBeTrue()
        ->and(decodeOneKey("\e[23~")->is(Key::F11))->toBeTrue()
        ->and(decodeOneKey("\e[24~")->is(Key::F12))->toBeTrue();
});

test('Alt+letter decodes to the matching Alt key', function (): void {
    expect(decodeOneKey("\ex")->is(Key::AltX))->toBeTrue()
        ->and(decodeOneKey("\ef")->is(Key::AltF))->toBeTrue()
        ->and(decodeOneKey("\eA")->is(Key::AltA))->toBeTrue()
        ->and(decodeOneKey("\eA")->modifiers)->toBe(KeyModifier::Alt);
});

test('legacy ESC Alt-digit sequences retain window-selection key identities', function (): void {
    $keys = [
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
    ];

    foreach ($keys as $character => $expected) {
        $key = decodeOneKey("\e{$character}");

        expect($key->is($expected))->toBeTrue("Alt+{$character} should be {$expected->name}")
            ->and($key->modifiers)->toBe(KeyModifier::Alt);
    }
});

test('legacy ESC Alt punctuation maps to its Turbo Vision identity', function (): void {
    expect(decodeOneKey("\e-")->is(Key::AltMinus))->toBeTrue()
        ->and(decodeOneKey("\e=")->is(Key::AltEqual))->toBeTrue()
        ->and(decodeOneKey("\e ")->is(Key::AltSpace))->toBeTrue()
        ->and(decodeOneKey("\e\x7f")->is(Key::AltBackspace))->toBeTrue();
});

test('SGR mouse press decodes position (1-based -> 0-based) and button', function (): void {
    // button 0 (left) press at col 10 row 5  -> where (9, 4)
    $result = (new EscapeDecoder())->decode("\e[<0;10;5M");

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('');
    $mouse = $result->events[0]->asMouse();
    expect($mouse)->not->toBeNull();

    if ($mouse === null) {
        throw new \RuntimeException('Expected a MouseEvent but got null');
    }

    expect($result->events[0]->what)->toBe(EventType::MouseDown)
        ->and($mouse->where->x)->toBe(9)
        ->and($mouse->where->y)->toBe(4)
        ->and($mouse->buttons)->toBe(1); // left button bit
});

test('SGR mouse release preserves the releasing button bit so callers can match it to the prior MouseDown', function (): void {
    // b=0 -> left button (index 0) -> buttons bit = 1<<0 = 1
    $result = (new EscapeDecoder())->decode("\e[<0;3;3m");

    expect($result->events)->toHaveCount(1)
        ->and($result->events[0]->what)->toBe(EventType::MouseUp)
        ->and($result->events[0]->asMouse()?->buttons)->toBe(1); // bit 0 = left button
});

test('two quick presses at the same position are marked as a double click', function (): void {
    $decoder = new EscapeDecoder();

    $first = $decoder->decode("\e[<0;10;5M")->events[0]->asMouse();
    $decoder->decode("\e[<0;10;5m");
    $second = $decoder->decode("\e[<0;10;5M")->events[0]->asMouse();

    expect($first?->doubleClick)->toBeFalse()
        ->and($second?->doubleClick)->toBeTrue();
});

test('a lone trailing ESC is returned as remainder, not an event', function (): void {
    $result = (new EscapeDecoder())->decode("\e");

    expect($result->events)->toBe([])
        ->and($result->remainder)->toBe("\e");
});

test('an incomplete CSI is held as remainder until completed', function (): void {
    $decoder = new EscapeDecoder();

    $first = $decoder->decode("a\e[");
    expect($first->events)->toHaveCount(1)            // the 'a'
        ->and($first->events[0]->asKey()?->char)->toBe('a')
        ->and($first->remainder)->toBe("\e[");

    // caller re-feeds the remainder prepended to the next chunk
    $second = $decoder->decode($first->remainder . 'A');
    expect($second->events)->toHaveCount(1)
        ->and($second->events[0]->asKey()?->is(Key::Up))->toBeTrue()
        ->and($second->remainder)->toBe('');
});

test('Kitty keyboard sequences preserve the key and modifiers', function (): void {
    $result = (new EscapeDecoder())->decode("\e[13;2u");

    expect($result->events)->toHaveCount(1)
        ->and($result->remainder)->toBe('')
        ->and($result->events[0]->asKey()?->is(Key::Enter))->toBeTrue()
        ->and($result->events[0]->asKey()?->modifiers)->toBe(KeyModifier::Shift);
});

test('terminal control strings and cursor reports never leak into keyboard input', function (): void {
    $decoder = new EscapeDecoder();

    expect($decoder->decode("\e]11;rgb:ffff/ffff/ffff\x07")->events)->toBe([])
        ->and($decoder->decode("\eP1\$r0m\e\\")->events)->toBe([])
        ->and($decoder->decode("\e[12;40R")->events)->toBe([]);
});

test('a fragmented terminal control string is retained until its terminator arrives', function (): void {
    $decoder = new EscapeDecoder();
    $first = $decoder->decode("\e]52;c;payload\e");
    $second = $decoder->decode($first->remainder . "\\x");

    expect($first->events)->toBe([])
        ->and($first->remainder)->toBe("\e]52;c;payload\e")
        ->and($second->events)->toHaveCount(1)
        ->and($second->events[0]->asKey()?->char)->toBe('x');
});

test('flushPending turns a stranded ESC into Key::Esc', function (): void {
    $decoder = new EscapeDecoder();

    $esc = $decoder->flushPending("\e");
    expect($esc)->not->toBeNull();

    if ($esc === null) {
        throw new \RuntimeException('Expected an Event but got null');
    }

    expect($esc->asKey()?->is(Key::Esc))->toBeTrue();

    expect($decoder->flushPending(''))->toBeNull()
        ->and($decoder->flushPending("\e["))->toBeNull();
});

test('flushPendingEvents preserves consecutive bare ESC presses', function (): void {
    $events = (new EscapeDecoder())->flushPendingEvents("\e\e");

    $events[0]->clear();

    expect($events)->toHaveCount(2)
        ->and($events[0]->isNothing())->toBeTrue()
        ->and($events[1]->asKey()?->is(Key::Esc))->toBeTrue();
});

test('a held double-Esc before an incomplete inner sequence flushes both presses', function (): void {
    $decoder = new EscapeDecoder();

    $result = $decoder->decode("\e\e[");
    expect($result->events)->toBe([])
        ->and($result->remainder)->toBe("\e\e[");

    $events = $decoder->flushPendingEvents($result->remainder);

    expect(count($events))->toBe(2)
        ->and($events[0]->asKey()?->is(Key::Esc))->toBeTrue()
        ->and($events[1]->asKey()?->is(Key::Esc))->toBeTrue();
});

test('a lone incomplete CSI still drops at flush time', function (): void {
    $decoder = new EscapeDecoder();

    expect($decoder->flushPendingEvents("\e["))->toBe([]);
});

test('double-click detection uses the injected clock', function (): void {
    $now = 1000.0;
    $decoder = new EscapeDecoder(clock: function () use (&$now): float {
        return $now;
    });

    $down1 = "\e[<0;10;10M";
    $decoder->decode($down1);
    $now += 0.1; // inside the double-click window
    $events = $decoder->decode("\e[<0;10;10M")->events;

    expect($events[0]->asMouse()?->doubleClick)->toBeTrue();
});
