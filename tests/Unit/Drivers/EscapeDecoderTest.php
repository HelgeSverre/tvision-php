<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;

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
        ->and(decodeOneKey("\eA")->is(Key::AltA))->toBeTrue();
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

test('SGR mouse release decodes to a MouseUp with no buttons', function (): void {
    $result = (new EscapeDecoder())->decode("\e[<0;3;3m");

    expect($result->events)->toHaveCount(1)
        ->and($result->events[0]->what)->toBe(EventType::MouseUp)
        ->and($result->events[0]->asMouse()?->buttons)->toBe(0);
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

test('an unknown CSI sequence is consumed without throwing', function (): void {
    $result = (new EscapeDecoder())->decode("\e[99Z");

    expect($result->events)->toBe([])
        ->and($result->remainder)->toBe('');
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
