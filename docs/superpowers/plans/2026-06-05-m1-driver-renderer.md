# M1 Driver & Renderer — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the second layer of `HelgeSverre\TurboVision` — the terminal boundary and the render/decode pipeline that turns the pure drawing/event primitives from Plan 1 into real bytes on a real (or headless) terminal. After this plan, a caller can draw `Cell`s into a `Buffer`, `flush()` minimal ANSI to a `Driver`, and `pollEvents()` typed `Event`s decoded from raw input — fully unit-tested through a `HeadlessDriver`, with a real `AnsiDriver` and a `bin/render-demo` for eyeballing.

**Architecture:** Three namespaces sit above Plan 1's `Drawing\`/`Events\`/`Geometry\`. `Drivers\` holds two pure codecs (`AnsiEncoder` = drawing intent → ANSI bytes; `EscapeDecoder` = raw bytes → `Event[]` + remainder) and the `Driver` interface with its two implementations (`HeadlessDriver` for tests, `AnsiDriver` for a real TTY). `Rendering\` holds the pure `DiffPresenter` (front-vs-back diff → minimal ANSI). `Terminal\` holds the `Screen` integration capstone that owns a `Driver`, two `Buffer`s, an `AnsiEncoder`, an `EscapeDecoder` (+ its remainder), and a `DiffPresenter`, exposing `back()`/`flush()`/`pollEvents()`/`init()`/`shutdown()`. The `Driver` interface is the *only* component that touches the real terminal; everything else is deterministic and headless-testable.

**Tech Stack:** PHP 8.5, Composer (PSR-4), Pest v3 (tests), PHPStan (level max). Runtime ext after this plan: `ext-mbstring` (from Plan 1) plus `ext-posix` and `ext-pcntl` (added in the final task for the real `AnsiDriver`).

**Source of truth for semantics:** `docs/references/source/tvision-0.8/lib/system.h` / `system.cc` (`TScreen`/`TDisplay`/`TEventQueue`), `docs/references/source/tvision-0.8/lib/tkeys.h` (key codes — values used below were extracted from it), and `docs/references/installation-handbook.md` (keyboard/screen/mouse chapters — xterm SGR mouse mode `\e[<b;x;yM/m`, SIGWINCH reflow, escape-sequence decoding).

**Builds on (Plan 1, already on `main`):** `Drawing\{Buffer,Cell,DrawBuffer,Attribute,Color}`, `Events\{Event,EventType,EventMask,Key,Cmd,KeyDownEvent,MouseEvent,MessageEvent}`, `Geometry\{Point,Rect}`, `Exceptions\TurboVisionException`. Real signatures of those classes are used verbatim below.

---

## File Structure

```
src/
  Exceptions/
    DriverException.php          # NEW: thrown by AnsiDriver when no TTY / no stty
  Drivers/
    AnsiEncoder.php              # NEW: pure — drawing intent -> ANSI/VT bytes
    DecodeResult.php             # NEW: value object { Event[] events, string remainder }
    EscapeDecoder.php            # NEW: pure, incremental — raw bytes -> DecodeResult
    Driver.php                   # NEW: interface — the only real-terminal boundary
    HeadlessDriver.php           # NEW: scripted input + captured output, no real I/O
    AnsiDriver.php               # NEW: real TTY via stty / stream_select / pcntl
  Rendering/
    DiffPresenter.php            # NEW: pure — diff(front,back) -> minimal ANSI
  Terminal/
    Screen.php                   # NEW: integration capstone (owns Driver + buffers + codecs)
  Events/
    Key.php                      # MODIFIED: add F11/F12 cases (decoder targets)
bin/
  render-demo                    # NEW: PHP smoke script — bordered box on a real terminal
tests/
  Unit/Drivers/AnsiEncoderTest.php
  Unit/Drivers/DecodeResultTest.php
  Unit/Drivers/EscapeDecoderTest.php
  Unit/Drivers/HeadlessDriverTest.php
  Unit/Drivers/AnsiDriverTest.php
  Unit/Rendering/DiffPresenterTest.php
  Unit/Terminal/ScreenTest.php
composer.json                    # MODIFIED: add ext-posix, ext-pcntl to require
ROADMAP.md                       # MODIFIED: status line
```

Each file has one responsibility. The two codecs (`AnsiEncoder`, `EscapeDecoder`) are pure and fully unit-tested. `Driver` is an interface; `HeadlessDriver` is the keystone test double; `AnsiDriver`'s genuinely un-unit-testable bits are isolated behind a thin shell and covered by `bin/render-demo` rather than fragile mocks. A class and its test are introduced in the same task.

**Build order (each builds on green predecessors):** AnsiEncoder → DecodeResult → EscapeDecoder (+ Key F11/F12) → Driver interface → HeadlessDriver → AnsiDriver → DiffPresenter → Screen → bin/render-demo → composer ext + full-suite/tag.

---

## Task 1: Drivers — AnsiEncoder

**Files:**
- Create: `src/Drivers/AnsiEncoder.php`
- Test: `tests/Unit/Drivers/AnsiEncoderTest.php`

A pure (no-I/O) translator from drawing intent to ANSI/VT byte strings. `moveCursor` emits a 1-based `CUP` sequence (terminal rows/cols are 1-based; our coordinates are 0-based). `style` delegates to `Attribute::fromByte(...)->toSgr()` so colour packing stays in one place. `run` is the renderer's workhorse: move + style + text in one call. The screen-control helpers (`reset`, `clearScreen`, cursor visibility, alt-screen, mouse) are exact constant strings. Tested against exact byte strings.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/AnsiEncoderTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\AnsiEncoder;

test('moveCursor emits a 1-based CUP sequence', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->moveCursor(0, 0))->toBe("\e[1;1H")
        ->and($enc->moveCursor(4, 2))->toBe("\e[3;5H")   // col 4 -> 5, row 2 -> 3
        ->and($enc->moveCursor(79, 23))->toBe("\e[24;80H");
});

test('style mirrors Attribute::fromByte(byte)->toSgr()', function (): void {
    $enc = new AnsiEncoder();

    // 0x07 = light gray on black
    expect($enc->style(0x07))->toBe("\e[0;37;40m")
        // 0x1F = white (15) on blue (1) -> bright fg
        ->and($enc->style(0x1F))->toBe("\e[0;97;41m");
});

test('run combines move + style + text in order', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->run(2, 1, 'Hi', 0x07))
        ->toBe("\e[2;3H" . "\e[0;37;40m" . 'Hi');
});

test('screen-control helpers are exact constant strings', function (): void {
    $enc = new AnsiEncoder();

    expect($enc->reset())->toBe("\e[0m")
        ->and($enc->clearScreen())->toBe("\e[2J\e[H")
        ->and($enc->hideCursor())->toBe("\e[?25l")
        ->and($enc->showCursor())->toBe("\e[?25h")
        ->and($enc->enterAltScreen())->toBe("\e[?1049h")
        ->and($enc->leaveAltScreen())->toBe("\e[?1049l")
        ->and($enc->enableMouse())->toBe("\e[?1000h\e[?1006h")
        ->and($enc->disableMouse())->toBe("\e[?1006l\e[?1000l");
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Drivers/AnsiEncoderTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Drivers\AnsiEncoder" not found`.

- [ ] **Step 3: Write the implementation**

`src/Drivers/AnsiEncoder.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Drawing\Attribute;

/**
 * Pure (no-I/O) translator from drawing intent to ANSI/VT byte strings.
 * Coordinates are 0-based; emitted CUP sequences are 1-based, as terminals expect.
 */
final class AnsiEncoder
{
    /** Cursor Position (CUP): move to 0-based ($x, $y). */
    public function moveCursor(int $x, int $y): string
    {
        return "\e[" . ($y + 1) . ';' . ($x + 1) . 'H';
    }

    /** SGR sequence for a packed Turbo Vision attribute byte. */
    public function style(int $attrByte): string
    {
        return Attribute::fromByte($attrByte)->toSgr();
    }

    /** Move + style + literal text — the renderer's per-run workhorse. */
    public function run(int $x, int $y, string $text, int $attrByte): string
    {
        return $this->moveCursor($x, $y) . $this->style($attrByte) . $text;
    }

    public function reset(): string
    {
        return "\e[0m";
    }

    public function clearScreen(): string
    {
        return "\e[2J\e[H";
    }

    public function hideCursor(): string
    {
        return "\e[?25l";
    }

    public function showCursor(): string
    {
        return "\e[?25h";
    }

    public function enterAltScreen(): string
    {
        return "\e[?1049h";
    }

    public function leaveAltScreen(): string
    {
        return "\e[?1049l";
    }

    /** Enable xterm normal-tracking (1000) + SGR extended (1006) mouse reporting. */
    public function enableMouse(): string
    {
        return "\e[?1000h\e[?1006h";
    }

    public function disableMouse(): string
    {
        return "\e[?1006l\e[?1000l";
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Drivers/AnsiEncoderTest.php`
Expected: PASS — `Tests: 4 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Drivers/AnsiEncoder.php tests/Unit/Drivers/AnsiEncoderTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AnsiEncoder.php tests/Unit/Drivers/AnsiEncoderTest.php
git commit -m "feat(drivers): add pure AnsiEncoder (drawing intent -> ANSI bytes)"
```

---

## Task 2: Drivers — DecodeResult

**Files:**
- Create: `src/Drivers/DecodeResult.php`
- Test: `tests/Unit/Drivers/DecodeResultTest.php`

The immutable value object returned by `EscapeDecoder::decode()`: the `Event[]` fully decoded this pass plus the `remainder` (trailing bytes that form an *incomplete* sequence the caller must re-feed next time). Introduced before the decoder so the decoder's signature is anchored.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/DecodeResultTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\DecodeResult;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\KeyDownEvent;

test('holds events and a remainder string', function (): void {
    $events = [Event::keyDown(new KeyDownEvent(0x0061, 'a'))];
    $result = new DecodeResult($events, "\e");

    expect($result->events)->toBe($events)
        ->and($result->remainder)->toBe("\e");
});

test('defaults to no events and an empty remainder', function (): void {
    $result = new DecodeResult();

    expect($result->events)->toBe([])
        ->and($result->remainder)->toBe('');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Drivers/DecodeResultTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Drivers\DecodeResult" not found`.

- [ ] **Step 3: Write the implementation**

`src/Drivers/DecodeResult.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Events\Event;

/**
 * The outcome of one EscapeDecoder::decode() pass: every fully decoded Event, plus
 * the trailing bytes that form an incomplete sequence (the caller re-feeds these
 * prepended to the next chunk).
 */
final readonly class DecodeResult
{
    /** @param list<Event> $events */
    public function __construct(
        public array $events = [],
        public string $remainder = '',
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Drivers/DecodeResultTest.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Drivers/DecodeResult.php tests/Unit/Drivers/DecodeResultTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/DecodeResult.php tests/Unit/Drivers/DecodeResultTest.php
git commit -m "feat(drivers): add DecodeResult value object"
```

---

## Task 3: Events — add F11/F12 to the Key enum

**Files:**
- Modify: `src/Events/Key.php`
- Test: `tests/Unit/Events/KeyF11F12Test.php`

The escape decoder (next task) maps `\e[23~`/`\e[24~` to F11/F12, but the Plan 1 `Key` enum stops at F10. Add the two faithful CGA scancodes (`kbF11 = 0x8500`, `kbF12 = 0x8600`) so the decoder has targets. This is the only modification to a Plan 1 file in this plan.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Events/KeyF11F12Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Key;

test('F11 and F12 carry their faithful CGA scancodes', function (): void {
    expect(Key::F11->value)->toBe(0x8500)
        ->and(Key::F12->value)->toBe(0x8600);
});

test('all key codes remain unique after adding F11/F12', function (): void {
    $values = array_map(fn (Key $k): int => $k->value, Key::cases());

    expect($values)->toBe(array_unique($values));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Events/KeyF11F12Test.php`
Expected: FAIL — `Undefined constant HelgeSverre\TurboVision\Events\Key::F11`.

- [ ] **Step 3: Modify the Key enum**

In `src/Events/Key.php`, after the `case F10 = 0x4400;` line (the last function-key line), add:

```php
    case F11 = 0x8500;
    case F12 = 0x8600;
```

So the function-key block reads:

```php
    // Function keys
    case F1 = 0x3B00;
    case F2 = 0x3C00;
    case F3 = 0x3D00;
    case F4 = 0x3E00;
    case F5 = 0x3F00;
    case F6 = 0x4000;
    case F7 = 0x4100;
    case F8 = 0x4200;
    case F9 = 0x4300;
    case F10 = 0x4400;
    case F11 = 0x8500;
    case F12 = 0x8600;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Events/KeyF11F12Test.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Events/Key.php tests/Unit/Events/KeyF11F12Test.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Events/Key.php tests/Unit/Events/KeyF11F12Test.php
git commit -m "feat(events): add F11/F12 keys for the escape decoder"
```

---

## Task 4: Drivers — EscapeDecoder

**Files:**
- Create: `src/Drivers/EscapeDecoder.php`
- Test: `tests/Unit/Drivers/EscapeDecoderTest.php`

A pure, incremental decoder: `decode(string $bytes): DecodeResult` consumes a byte chunk and returns fully decoded `Event`s plus a `remainder` (an *incomplete* trailing sequence to re-feed next pass). The decoder is **total** — unknown sequences never throw; an unrecognised CSI/SS3 sequence is consumed and dropped.

Decoding table (faithful to `tkeys.h`; codes verified against the source):

| Input bytes | Result |
|---|---|
| printable ASCII `0x20`–`0x7E` | `KeyDown(keyCode=ord, char=byte)` |
| UTF-8 multibyte lead + continuations | `KeyDown(keyCode=0, char=grapheme)` |
| `0x01`–`0x1A` (Ctrl-A..Z, **except** Tab/Enter) | `KeyDown(keyCode=ord, char='')` |
| `0x09` Tab | `Key::Tab` (0x0F09) |
| `0x0D` Enter (and `0x0A`) | `Key::Enter` (0x1C0D) |
| `0x7F` / `0x08` Backspace | `Key::Backspace` (0x0E08) |
| `\e[A`/`B`/`C`/`D` | Up / Down / Right / Left |
| `\e[H`, `\e[F` | Home, End |
| `\e[1~`,`\e[2~`,`\e[3~`,`\e[4~`,`\e[5~`,`\e[6~` | Home, Ins, Del, End, PgUp, PgDn |
| `\e[7~`, `\e[8~` | Home, End (rxvt-style) |
| `\eOP`/`Q`/`R`/`S` | F1..F4 |
| `\e[11~`..`\e[15~` | F1..F5 |
| `\e[17~`..`\e[21~` | F6..F10 |
| `\e[23~`, `\e[24~` | F11, F12 |
| `\eX` (letter `a`–`z`/`A`–`Z`) | Alt+letter → matching `Key::Alt*` |
| `\e[<b;x;yM` / `\e[<b;x;ym` | `MouseEvent` (SGR; `M`=press, `m`=release) |
| lone trailing `\e` | empty events, `remainder = "\e"` |
| incomplete CSI/SS3 (e.g. `\e[`, `\e[1`) | empty/partial events, that fragment as `remainder` |

`flushPending(string $remainder): ?Event` turns a stranded lone `\e` (held in remainder when input goes quiet on a timeout) into `Key::Esc`; anything else returns `null`.

> **NOTE (planned follow-up):** this decoder targets the common xterm/VT subset needed for M1. Hardening it against a multi-terminal corpus (xterm, kitty, tmux, screen, Windows Terminal, iTerm — including `modifyOtherKeys`/kitty-protocol variants and `\e[1;5A`-style modified arrows) is a planned follow-up, executed via a **parallel-verification workflow**: capture real byte traces per terminal, run them through `decode()`, and diff against an expected-event corpus. Out of scope for this task; the table above is the M1 contract.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/EscapeDecoderTest.php`:

```php
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
    expect($mouse)->not->toBeNull()
        ->and($result->events[0]->what)->toBe(EventType::MouseDown)
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
    expect($esc)->not->toBeNull()
        ->and($esc->asKey()?->is(Key::Esc))->toBeTrue();

    expect($decoder->flushPending(''))->toBeNull()
        ->and($decoder->flushPending("\e["))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Drivers/EscapeDecoderTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Drivers\EscapeDecoder" not found`.

- [ ] **Step 3: Write the implementation**

`src/Drivers/EscapeDecoder.php`:

```php
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
            $key = Key::tryFrom(0); // placeholder; resolved by name below
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
```

- [ ] **Step 4: Clean up the dead placeholder line**

The `decodeEscape()` body above contains a deliberately redundant line left from drafting; remove it so PHPStan stays clean. Delete this line:

```php
            $key = Key::tryFrom(0); // placeholder; resolved by name below
```

so the Alt branch reads:

```php
        // \e<letter> -> Alt+letter
        if (preg_match('/[A-Za-z]/', $next) === 1) {
            $altCase = 'Alt' . strtoupper($next);
            $key = self::altKey($altCase);
            if ($key !== null) {
                $events[] = Event::keyDown(new KeyDownEvent($key->value));
            }

            return 2;
        }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Drivers/EscapeDecoderTest.php`
Expected: PASS — `Tests: 15 passed`.

- [ ] **Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Drivers/EscapeDecoder.php tests/Unit/Drivers/EscapeDecoderTest.php`
Expected: `[OK] No errors`. (The `@param list<Event> $events` by-reference annotations and the `self::CSI_LETTER`/`CSI_TILDE`/`SS3` typed `const array` keep generics resolved.)

- [ ] **Step 7: Commit**

```bash
git add src/Drivers/EscapeDecoder.php tests/Unit/Drivers/EscapeDecoderTest.php
git commit -m "feat(drivers): add incremental EscapeDecoder (bytes -> Event[] + remainder)"
```

---

## Task 5: Drivers — Driver interface

**Files:**
- Create: `src/Drivers/Driver.php`
- (No standalone test — the contract is exercised through `HeadlessDriver` in Task 6.)

The single real-terminal boundary. Everything above it is pure. `size()` returns `array{0:int,1:int}` (cols, rows). `pollInput()` returns whatever raw bytes are available within the timeout (`''` if none). `resized()` returns *and clears* a latched SIGWINCH flag. `shutdown()` must be idempotent.

- [ ] **Step 1: Write the interface**

`src/Drivers/Driver.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

/**
 * The sole boundary to a real terminal. Implementations: AnsiDriver (real TTY) and
 * HeadlessDriver (scripted, for tests). Everything above this interface is pure.
 */
interface Driver
{
    /** Enter alt screen, raw mode, hide cursor, enable mouse. Idempotent-safe to skip if already initialised. */
    public function init(): void;

    /** Restore every terminal mutation made by init(). MUST be idempotent. */
    public function shutdown(): void;

    /**
     * Current terminal size.
     *
     * @return array{0:int,1:int} [cols, rows]
     */
    public function size(): array;

    /** Write raw bytes to the terminal. */
    public function write(string $bytes): void;

    /** Raw input bytes available within $timeoutMs; '' if none arrived. */
    public function pollInput(int $timeoutMs): string;

    /** True once since the last call if the terminal was resized (clears the flag). */
    public function resized(): bool;
}
```

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Drivers/Driver.php`
Expected: `[OK] No errors`.

- [ ] **Step 3: Commit**

```bash
git add src/Drivers/Driver.php
git commit -m "feat(drivers): add Driver interface (the terminal boundary)"
```

---

## Task 6: Drivers — HeadlessDriver

**Files:**
- Create: `src/Drivers/HeadlessDriver.php`
- Test: `tests/Unit/Drivers/HeadlessDriverTest.php`

The keystone test double. No real I/O: a scripted input queue (`feedInput()`), captured output (`output()` peeks, `takeOutput()` drains), a settable fixed size (`resizeTo()` which also latches the resize flag). `pollInput()` returns *all* queued input (or `''`) and ignores the timeout — tests are deterministic. `init()`/`shutdown()` just flip a flag so behaviour is observable.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/HeadlessDriverTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;

test('size is fixed and settable; resize latches a flag', function (): void {
    $d = new HeadlessDriver(80, 24);

    expect($d->size())->toBe([80, 24])
        ->and($d->resized())->toBeFalse();

    $d->resizeTo(100, 30);
    expect($d->size())->toBe([100, 30])
        ->and($d->resized())->toBeTrue()
        ->and($d->resized())->toBeFalse(); // flag cleared after read
});

test('output accumulates; output() peeks, takeOutput() drains', function (): void {
    $d = new HeadlessDriver();
    $d->write('abc');
    $d->write('def');

    expect($d->output())->toBe('abcdef')
        ->and($d->output())->toBe('abcdef')   // peek does not drain
        ->and($d->takeOutput())->toBe('abcdef')
        ->and($d->output())->toBe('');         // drained
});

test('fed input is returned by pollInput and then consumed', function (): void {
    $d = new HeadlessDriver();
    $d->feedInput("\e[A");

    expect($d->pollInput(0))->toBe("\e[A")
        ->and($d->pollInput(0))->toBe(''); // queue emptied
});

test('init and shutdown are observable and shutdown is idempotent', function (): void {
    $d = new HeadlessDriver();

    expect($d->isInitialised())->toBeFalse();
    $d->init();
    expect($d->isInitialised())->toBeTrue();
    $d->shutdown();
    $d->shutdown(); // idempotent, no error
    expect($d->isInitialised())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Drivers/HeadlessDriverTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Drivers\HeadlessDriver" not found`.

- [ ] **Step 3: Write the implementation**

`src/Drivers/HeadlessDriver.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

/**
 * A no-I/O Driver for tests: scripted input queue, captured output, fixed/settable
 * size. The keystone that makes the entire render/input pipeline deterministic.
 */
final class HeadlessDriver implements Driver
{
    private string $inputQueue = '';

    private string $captured = '';

    private bool $resized = false;

    private bool $initialised = false;

    public function __construct(
        private int $cols = 80,
        private int $rows = 24,
    ) {}

    public function init(): void
    {
        $this->initialised = true;
    }

    public function shutdown(): void
    {
        $this->initialised = false;
    }

    public function isInitialised(): bool
    {
        return $this->initialised;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        return [$this->cols, $this->rows];
    }

    public function write(string $bytes): void
    {
        $this->captured .= $bytes;
    }

    public function pollInput(int $timeoutMs): string
    {
        $out = $this->inputQueue;
        $this->inputQueue = '';

        return $out;
    }

    public function resized(): bool
    {
        $was = $this->resized;
        $this->resized = false;

        return $was;
    }

    /** Queue raw bytes to be returned by the next pollInput(). */
    public function feedInput(string $bytes): void
    {
        $this->inputQueue .= $bytes;
    }

    /** Peek at everything written so far without draining it. */
    public function output(): string
    {
        return $this->captured;
    }

    /** Return everything written so far and clear the capture buffer. */
    public function takeOutput(): string
    {
        $out = $this->captured;
        $this->captured = '';

        return $out;
    }

    /** Change the reported size and latch the resize flag. */
    public function resizeTo(int $cols, int $rows): void
    {
        $this->cols = $cols;
        $this->rows = $rows;
        $this->resized = true;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Drivers/HeadlessDriverTest.php`
Expected: PASS — `Tests: 4 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Drivers/HeadlessDriver.php tests/Unit/Drivers/HeadlessDriverTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/HeadlessDriver.php tests/Unit/Drivers/HeadlessDriverTest.php
git commit -m "feat(drivers): add HeadlessDriver test double"
```

---

## Task 7: Exceptions — DriverException

**Files:**
- Create: `src/Exceptions/DriverException.php`
- Test: `tests/Unit/Drivers/DriverExceptionTest.php`

The typed exception `AnsiDriver::init()` throws when there is no TTY or `stty` is unavailable, *before* mutating terminal state. Extends the Plan 1 root `TurboVisionException`. Named constructors document the two failure modes.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/DriverExceptionTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Exceptions\DriverException;
use HelgeSverre\TurboVision\Exceptions\TurboVisionException;

test('DriverException is a TurboVisionException', function (): void {
    expect(new DriverException('x'))->toBeInstanceOf(TurboVisionException::class);
});

test('named constructors describe the failure mode', function (): void {
    expect(DriverException::notATty()->getMessage())
        ->toContain('TTY')
        ->and(DriverException::sttyUnavailable()->getMessage())
        ->toContain('stty');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Drivers/DriverExceptionTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Exceptions\DriverException" not found`.

- [ ] **Step 3: Write the implementation**

`src/Exceptions/DriverException.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Exceptions;

/** Thrown by a real-terminal Driver when the environment cannot support raw TUI I/O. */
final class DriverException extends TurboVisionException
{
    public static function notATty(): self
    {
        return new self('STDIN/STDOUT is not a TTY; a real terminal is required.');
    }

    public static function sttyUnavailable(): self
    {
        return new self('The "stty" command is unavailable; cannot enter raw mode.');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Drivers/DriverExceptionTest.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Exceptions/DriverException.php tests/Unit/Drivers/DriverExceptionTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Exceptions/DriverException.php tests/Unit/Drivers/DriverExceptionTest.php
git commit -m "feat(exceptions): add DriverException for TTY/stty failures"
```

---

## Task 8: Drivers — AnsiDriver

**Files:**
- Create: `src/Drivers/AnsiDriver.php`
- Test: `tests/Unit/Drivers/AnsiDriverTest.php`

The real-TTY driver. Most of its surface is genuinely un-unit-testable (it mutates the controlling terminal). The strategy, per the spec: **isolate the un-testable bits behind a thin shell, unit-test only the deterministic, side-effect-free logic, and rely on `bin/render-demo` (Task 9) for end-to-end eyeballing.**

What is unit-tested here:
- `init()` *validates the environment first* and throws `DriverException` (no `posix_isatty` / no `stty`) **before** any terminal mutation. We force the failure path in tests by constructing the driver with non-TTY stream resources (a `php://memory` handle), so `posix_isatty` returns false and `init()` throws without touching the terminal.
- `parseSize()` (a pure static helper) turns the `"rows cols\n"` output of `stty size` into `[cols, rows]`, falling back to `[80, 24]` on garbage.
- `shutdown()` is idempotent (calling it twice, or before `init()`, is a no-op and never errors).

What is **not** unit-tested (covered by `bin/render-demo`): actual raw-mode entry/exit, `stream_select` polling on a live STDIN, SIGWINCH delivery, and alt-screen visuals. These are marked in the class docblock.

> **Design note:** STDIN/STDOUT/the `stty` runner are injected through the constructor (defaulting to the real `STDIN`/`STDOUT` and a real `shell_exec` runner). Tests pass in-memory streams and a fake runner. This keeps the boundary thin and the pure logic reachable. `init()` registers `register_shutdown_function([$this, 'shutdown'])` and traps `SIGWINCH` via `pcntl_signal`; `resized()` pumps `pcntl_signal_dispatch()` then returns/clears the latched flag. Requires `ext-posix` + `ext-pcntl` (added to composer in Task 11).

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/AnsiDriverTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Exceptions\DriverException;

/** A non-TTY stream pair: in-memory handles so posix_isatty() returns false. */
function memoryStreams(): array
{
    return [fopen('php://memory', 'r+b'), fopen('php://memory', 'r+b')];
}

test('init throws DriverException when STDIN/STDOUT is not a TTY', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');

    expect(fn () => $driver->init())->toThrow(DriverException::class);
});

test('init does not write anything when it fails the TTY check', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');

    try {
        $driver->init();
    } catch (DriverException) {
        // expected
    }

    rewind($out);
    expect(stream_get_contents($out))->toBe(''); // terminal state untouched
});

test('parseSize converts "rows cols" to [cols, rows]', function (): void {
    expect(AnsiDriver::parseSize("24 80\n"))->toBe([80, 24])
        ->and(AnsiDriver::parseSize('30 100'))->toBe([100, 30]);
});

test('parseSize falls back to 80x24 on garbage', function (): void {
    expect(AnsiDriver::parseSize(''))->toBe([80, 24])
        ->and(AnsiDriver::parseSize('not a size'))->toBe([80, 24])
        ->and(AnsiDriver::parseSize('0 0'))->toBe([80, 24]);
});

test('shutdown before init, and twice, is a harmless no-op', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');

    $driver->shutdown();
    $driver->shutdown();

    expect(true)->toBeTrue(); // reached here without error
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Drivers/AnsiDriverTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Drivers\AnsiDriver" not found`.

- [ ] **Step 3: Write the implementation**

`src/Drivers/AnsiDriver.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use Closure;
use HelgeSverre\TurboVision\Exceptions\DriverException;

/**
 * Real-TTY Driver: raw mode via `stty`, STDOUT writes, non-blocking STDIN polled
 * with stream_select, terminal size via `stty size`, SIGWINCH via pcntl.
 *
 * Testability boundary: only the deterministic, side-effect-free logic
 * (init() environment validation, parseSize(), idempotent shutdown()) is unit-tested.
 * Raw-mode entry/exit, live stream_select polling, SIGWINCH delivery, and alt-screen
 * visuals are inherently terminal-coupled and are exercised by bin/render-demo, not
 * by fragile unit mocks. Requires ext-posix and ext-pcntl.
 *
 * @phpstan-type SttyRunner Closure(string):string
 */
final class AnsiDriver implements Driver
{
    private readonly AnsiEncoder $encoder;

    /** @var resource */
    private $stdin;

    /** @var resource */
    private $stdout;

    /** @var Closure(string):string */
    private Closure $stty;

    private bool $initialised = false;

    /** Original `stty -g` settings, captured at init() to restore on shutdown(). */
    private ?string $savedStty = null;

    private bool $resizeFlag = false;

    /**
     * @param resource|null $stdin   defaults to STDIN
     * @param resource|null $stdout  defaults to STDOUT
     * @param (Closure(string):string)|null $sttyRunner runs an stty command, returns its stdout
     */
    public function __construct(
        $stdin = null,
        $stdout = null,
        ?Closure $sttyRunner = null,
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stty = $sttyRunner ?? static fn (string $cmd): string => (string) shell_exec($cmd);
        $this->encoder = new AnsiEncoder();
    }

    public function init(): void
    {
        if ($this->initialised) {
            return;
        }

        // Validate the environment BEFORE mutating any terminal state.
        if (! \function_exists('posix_isatty')
            || ! @posix_isatty($this->stdin)
            || ! @posix_isatty($this->stdout)) {
            throw DriverException::notATty();
        }

        $probe = trim(($this->stty)('command -v stty'));
        if ($probe === '') {
            throw DriverException::sttyUnavailable();
        }

        // Save current settings, then enter raw mode.
        $this->savedStty = trim(($this->stty)('stty -g'));
        ($this->stty)('stty raw -echo');

        // Non-blocking STDIN so pollInput() never blocks past its timeout.
        stream_set_blocking($this->stdin, false);

        // Enter alt screen, clear, hide cursor, enable mouse.
        $this->write(
            $this->encoder->enterAltScreen()
            . $this->encoder->clearScreen()
            . $this->encoder->hideCursor()
            . $this->encoder->enableMouse()
        );

        // Trap SIGWINCH and guarantee teardown on any exit path.
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGWINCH, function (): void {
                $this->resizeFlag = true;
            });
        }
        register_shutdown_function([$this, 'shutdown']);

        $this->initialised = true;
    }

    public function shutdown(): void
    {
        if (! $this->initialised) {
            return;
        }

        $this->write(
            $this->encoder->disableMouse()
            . $this->encoder->showCursor()
            . $this->encoder->leaveAltScreen()
            . $this->encoder->reset()
        );

        if ($this->savedStty !== null && $this->savedStty !== '') {
            ($this->stty)('stty ' . $this->savedStty);
        } else {
            ($this->stty)('stty sane');
        }

        $this->initialised = false;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        return self::parseSize(($this->stty)('stty size'));
    }

    public function write(string $bytes): void
    {
        fwrite($this->stdout, $bytes);
    }

    public function pollInput(int $timeoutMs): string
    {
        $read = [$this->stdin];
        $write = null;
        $except = null;
        $sec = intdiv($timeoutMs, 1000);
        $usec = ($timeoutMs % 1000) * 1000;

        $ready = @stream_select($read, $write, $except, $sec, $usec);
        if ($ready === false || $ready === 0) {
            return '';
        }

        $bytes = fread($this->stdin, 8192);

        return $bytes === false ? '' : $bytes;
    }

    public function resized(): bool
    {
        if (\function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        $was = $this->resizeFlag;
        $this->resizeFlag = false;

        return $was;
    }

    /**
     * Parse `stty size` output ("rows cols") into [cols, rows]; fall back to [80, 24].
     *
     * @return array{0:int,1:int}
     */
    public static function parseSize(string $raw): array
    {
        if (preg_match('/(\d+)\s+(\d+)/', trim($raw), $m) === 1) {
            $rows = (int) $m[1];
            $cols = (int) $m[2];
            if ($rows > 0 && $cols > 0) {
                return [$cols, $rows];
            }
        }

        return [80, 24];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Drivers/AnsiDriverTest.php`
Expected: PASS — `Tests: 5 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Drivers/AnsiDriver.php tests/Unit/Drivers/AnsiDriverTest.php`
Expected: `[OK] No errors`. (PHPStan may flag the bare `SIGWINCH` constant if `ext-pcntl` is not installed in the dev environment. If so, the constant is gated behind `function_exists('pcntl_signal')` already; should that not satisfy analysis, add `if (\defined('SIGWINCH'))` around the `pcntl_signal` block. Do not silence with a baseline.)

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AnsiDriver.php tests/Unit/Drivers/AnsiDriverTest.php
git commit -m "feat(drivers): add AnsiDriver (real TTY via stty/stream_select/pcntl)"
```

---

## Task 9: Rendering — DiffPresenter

**Files:**
- Create: `src/Rendering/DiffPresenter.php`
- Test: `tests/Unit/Rendering/DiffPresenterTest.php`

Pure. `present(Buffer $front, Buffer $back, AnsiEncoder $enc): string` diffs `front` (what is currently on screen) against `back` (what we want), row by row, coalesces consecutive *changed* cells into runs, and for each run emits one `moveCursor`, then `style`+text, re-emitting `style` only when the attribute byte changes mid-run. Does not mutate either buffer. An unchanged buffer yields `''`.

The Screen owns the copy-back (`front ← back`) after writing — the presenter is a pure function of its inputs.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Rendering/DiffPresenterTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use HelgeSverre\TurboVision\Rendering\DiffPresenter;

test('an identical front and back produce no output', function (): void {
    $front = new Buffer(4, 2);
    $back = new Buffer(4, 2);

    expect((new DiffPresenter())->present($front, $back, new AnsiEncoder()))->toBe('');
});

test('a single changed cell emits move + style + char', function (): void {
    $front = new Buffer(4, 1);
    $back = new Buffer(4, 1);
    $back->put(2, 0, new Cell('X', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    // move to (2,0) -> "\e[1;3H", style 0x07, "X"
    expect($ansi)->toBe("\e[1;3H" . "\e[0;37;40m" . 'X');
});

test('consecutive changed cells of one attr coalesce into a single run', function (): void {
    $front = new Buffer(5, 1);
    $back = new Buffer(5, 1);
    $back->put(1, 0, new Cell('a', 0x07));
    $back->put(2, 0, new Cell('b', 0x07));
    $back->put(3, 0, new Cell('c', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    // one move to col 1, one style, then "abc"
    expect($ansi)->toBe("\e[1;2H" . "\e[0;37;40m" . 'abc');
});

test('an attr change mid-run re-emits style but not a move', function (): void {
    $front = new Buffer(3, 1);
    $back = new Buffer(3, 1);
    $back->put(0, 0, new Cell('a', 0x07));
    $back->put(1, 0, new Cell('b', 0x1F)); // different attr
    $back->put(2, 0, new Cell('c', 0x1F));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    expect($ansi)->toBe(
        "\e[1;1H" . "\e[0;37;40m" . 'a'  // run start: move + style + 'a'
        . "\e[0;97;41m" . 'bc'           // attr changes: re-style, no move, 'bc'
    );
});

test('an unchanged gap splits cells into separate runs', function (): void {
    $front = new Buffer(5, 1);
    $back = new Buffer(5, 1);
    $back->put(0, 0, new Cell('a', 0x07));
    // column 1 unchanged (blank)
    $back->put(2, 0, new Cell('b', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    expect($ansi)->toBe(
        "\e[1;1H" . "\e[0;37;40m" . 'a'   // run 1 at col 0
        . "\e[1;3H" . "\e[0;37;40m" . 'b' // run 2 at col 2 (new move)
    );
});

test('rows are emitted top-to-bottom with independent moves', function (): void {
    $front = new Buffer(2, 2);
    $back = new Buffer(2, 2);
    $back->put(0, 0, new Cell('a', 0x07));
    $back->put(0, 1, new Cell('b', 0x07));

    $ansi = (new DiffPresenter())->present($front, $back, new AnsiEncoder());

    expect($ansi)->toBe(
        "\e[1;1H" . "\e[0;37;40m" . 'a'   // row 0
        . "\e[2;1H" . "\e[0;37;40m" . 'b' // row 1
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Rendering/DiffPresenterTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Rendering\DiffPresenter" not found`.

- [ ] **Step 3: Write the implementation**

`src/Rendering/DiffPresenter.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Rendering;

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;

/**
 * Pure double-buffer presenter: diff $front (on screen) vs $back (desired) and emit
 * the minimal ANSI to make $front look like $back. Coalesces consecutive changed
 * cells into runs; one cursor move per run; re-emits the SGR style only when the
 * attribute byte changes within a run. Mutates nothing.
 */
final class DiffPresenter
{
    public function present(Buffer $front, Buffer $back, AnsiEncoder $enc): string
    {
        $out = '';
        $rows = min($front->height, $back->height);
        $cols = min($front->width, $back->width);

        for ($y = 0; $y < $rows; $y++) {
            $x = 0;
            while ($x < $cols) {
                $cur = $back->at($x, $y);
                $old = $front->at($x, $y);

                if ($cur->equals($old)) {
                    $x++;

                    continue;
                }

                // Start a run at this changed cell.
                $out .= $enc->moveCursor($x, $y);
                $runAttr = $cur->attr;
                $out .= $enc->style($runAttr);

                while ($x < $cols) {
                    $cell = $back->at($x, $y);
                    if ($cell->equals($front->at($x, $y))) {
                        break; // unchanged cell ends the run
                    }
                    if ($cell->attr !== $runAttr) {
                        $runAttr = $cell->attr;
                        $out .= $enc->style($runAttr); // re-style, no move
                    }
                    $out .= $cell->char;
                    $x++;
                }
            }
        }

        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Rendering/DiffPresenterTest.php`
Expected: PASS — `Tests: 6 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Rendering/DiffPresenter.php tests/Unit/Rendering/DiffPresenterTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Rendering/DiffPresenter.php tests/Unit/Rendering/DiffPresenterTest.php
git commit -m "feat(rendering): add pure DiffPresenter (front/back diff -> minimal ANSI)"
```

---

## Task 10: Terminal — Screen

**Files:**
- Create: `src/Terminal/Screen.php`
- Test: `tests/Unit/Terminal/ScreenTest.php`

The integration capstone. Owns a `Driver`, a back `Buffer` (what views draw into), a front `Buffer` (what is on screen), an `AnsiEncoder`, an `EscapeDecoder` (+ its `remainder`), and a `DiffPresenter`. Lifecycle:

- `init()`/`shutdown()` delegate to the driver, sizing the buffers from `driver->size()` at init.
- `back(): Buffer` — the surface views paint into.
- `clear()` — reset the back buffer to blanks.
- `flush(): void` — `present(front, back)`, `driver->write(...)`, then copy back into front (so the next diff is incremental).
- `pollEvents(int $timeoutMs): Event[]` — `driver->pollInput` → `decoder->decode(remainder . bytes)` → events; persist the new remainder; if input was empty and a lone ESC is pending, emit `Key::Esc` (via `flushPending`); if `driver->resized()` latched, resize both buffers and set a `wasResized()` flag the caller can read.
- `size(): Point`, plus `cols()`/`rows()` conveniences.

The end-to-end test uses `HeadlessDriver`: draw a small box into `back()`, `flush()`, assert the captured ANSI is non-empty and contains the expected box glyph runs; then `feedInput("\e[A")`, `pollEvents()` returns a `Key::Up` KeyDown.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Terminal/ScreenTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Terminal/ScreenTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Terminal\Screen" not found`.

- [ ] **Step 3: Write the implementation**

`src/Terminal/Screen.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Terminal;

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use HelgeSverre\TurboVision\Drivers\Driver;
use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Rendering\DiffPresenter;

/**
 * Integration capstone tying a Driver to the render/input pipeline. Owns a back
 * Buffer (what views draw into), a front Buffer (what is on screen), an AnsiEncoder,
 * an EscapeDecoder (+ its remainder), and a DiffPresenter.
 */
final class Screen
{
    private Buffer $back;

    private Buffer $front;

    private readonly AnsiEncoder $encoder;

    private readonly EscapeDecoder $decoder;

    private readonly DiffPresenter $presenter;

    private string $remainder = '';

    private bool $wasResized = false;

    public function __construct(private readonly Driver $driver)
    {
        $this->encoder = new AnsiEncoder();
        $this->decoder = new EscapeDecoder();
        $this->presenter = new DiffPresenter();
        // Provisional buffers until init() reads the real size.
        $this->back = new Buffer(0, 0);
        $this->front = new Buffer(0, 0);
    }

    public function init(): void
    {
        $this->driver->init();
        [$cols, $rows] = $this->driver->size();
        $this->resizeBuffers($cols, $rows);
    }

    public function shutdown(): void
    {
        $this->driver->shutdown();
    }

    public function back(): Buffer
    {
        return $this->back;
    }

    public function size(): Point
    {
        return new Point($this->back->width, $this->back->height);
    }

    public function cols(): int
    {
        return $this->back->width;
    }

    public function rows(): int
    {
        return $this->back->height;
    }

    /** Reset the back buffer to blank cells. */
    public function clear(): void
    {
        $this->back = new Buffer($this->back->width, $this->back->height);
    }

    /** Diff front->back, write the minimal ANSI, then copy back into front. */
    public function flush(): void
    {
        $ansi = $this->presenter->present($this->front, $this->back, $this->encoder);
        if ($ansi !== '') {
            $this->driver->write($ansi);
        }
        $this->front = $this->copyOf($this->back);
    }

    /**
     * Poll the driver for input, decode it (reassembling across calls via the held
     * remainder), surface resize, and emit a pending ESC on a quiet tick.
     *
     * @return list<Event>
     */
    public function pollEvents(int $timeoutMs): array
    {
        if ($this->driver->resized()) {
            [$cols, $rows] = $this->driver->size();
            $this->resizeBuffers($cols, $rows);
            $this->wasResized = true;
        }

        $bytes = $this->driver->pollInput($timeoutMs);

        if ($bytes === '') {
            // Quiet tick: a stranded ESC in the remainder becomes Key::Esc.
            $pending = $this->decoder->flushPending($this->remainder);
            if ($pending !== null) {
                $this->remainder = '';

                return [$pending];
            }

            return [];
        }

        $result = $this->decoder->decode($this->remainder . $bytes);
        $this->remainder = $result->remainder;

        return $result->events;
    }

    /** True once since the last call if the terminal was resized (clears the flag). */
    public function wasResized(): bool
    {
        $was = $this->wasResized;
        $this->wasResized = false;

        return $was;
    }

    private function resizeBuffers(int $cols, int $rows): void
    {
        $this->back = new Buffer($cols, $rows);
        $this->front = new Buffer($cols, $rows);
    }

    private function copyOf(Buffer $source): Buffer
    {
        $copy = new Buffer($source->width, $source->height);
        for ($y = 0; $y < $source->height; $y++) {
            for ($x = 0; $x < $source->width; $x++) {
                $copy->put($x, $y, $source->at($x, $y));
            }
        }

        return $copy;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Terminal/ScreenTest.php`
Expected: PASS — `Tests: 8 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Terminal/Screen.php tests/Unit/Terminal/ScreenTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Terminal/Screen.php tests/Unit/Terminal/ScreenTest.php
git commit -m "feat(terminal): add Screen integration capstone (buffers + codecs + driver)"
```

---

## Task 11: Capstone — bin/render-demo

**Files:**
- Create: `bin/render-demo`

A small runnable script that wires `AnsiDriver` + `Screen` to draw a bordered box with a centred label on a real terminal, polling for input and quitting on `q` or `Esc`. This is the manual smoke check for the genuinely terminal-coupled paths of `AnsiDriver` (raw mode, alt screen, SIGWINCH, real polling) that unit tests cannot reach. It is documented but not asserted by the suite; it must `chmod +x` and run by hand.

- [ ] **Step 1: Write the demo script**

`bin/render-demo`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Terminal\Screen;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Draw a centred bordered box with a label into the screen's back buffer.
 */
function drawBox(Screen $screen, string $label): void
{
    $screen->clear();
    $back = $screen->back();
    $w = $screen->cols();
    $h = $screen->rows();

    $boxW = min(40, max(10, $w - 4));
    $boxH = 5;
    $x0 = intdiv($w - $boxW, 2);
    $y0 = intdiv($h - $boxH, 2);
    $attr = 0x1F; // white on blue

    for ($x = 0; $x < $boxW; $x++) {
        $top = $x === 0 ? '┌' : ($x === $boxW - 1 ? '┐' : '─');
        $bot = $x === 0 ? '└' : ($x === $boxW - 1 ? '┘' : '─');
        $back->put($x0 + $x, $y0, new Cell($top, $attr));
        $back->put($x0 + $x, $y0 + $boxH - 1, new Cell($bot, $attr));
    }
    for ($y = 1; $y < $boxH - 1; $y++) {
        $back->put($x0, $y0 + $y, new Cell('│', $attr));
        $back->put($x0 + $boxW - 1, $y0 + $y, new Cell('│', $attr));
        for ($x = 1; $x < $boxW - 1; $x++) {
            $back->put($x0 + $x, $y0 + $y, new Cell(' ', $attr));
        }
    }

    $labelX = $x0 + intdiv($boxW - mb_strlen($label), 2);
    foreach (mb_str_split($label) as $i => $ch) {
        $back->put($labelX + $i, $y0 + intdiv($boxH, 2), new Cell($ch, $attr));
    }
}

$screen = new Screen(new AnsiDriver());
$screen->init();

try {
    drawBox($screen, 'TurboVision — press q or Esc');
    $screen->flush();

    while (true) {
        if ($screen->wasResized()) {
            drawBox($screen, 'TurboVision — press q or Esc');
        }

        $events = $screen->pollEvents(50);
        foreach ($events as $event) {
            $key = $event->asKey();
            if ($key === null) {
                continue;
            }
            if ($key->is(Key::Esc) || $key->char === 'q') {
                break 2;
            }
        }

        $screen->flush();
    }
} finally {
    $screen->shutdown();
}
```

- [ ] **Step 2: Make it executable and verify it parses**

Run:
```bash
chmod +x bin/render-demo
php -l bin/render-demo
```
Expected: `No syntax errors detected in bin/render-demo`.

- [ ] **Step 3: (Manual, optional) eyeball on a real terminal**

Run (in a real interactive terminal, not CI): `php bin/render-demo`
Expected: an alt-screen view with a centred bordered box and label; resizing the window re-centres the box; pressing `q` or `Esc` exits and the terminal is fully restored (cursor visible, main screen, cooked mode). This step is manual and not part of the automated suite.

- [ ] **Step 4: Run PHPStan on the demo**

Run: `./vendor/bin/phpstan analyse bin/render-demo`
Expected: `[OK] No errors`. (If PHPStan does not scan `bin/` by default, add `- bin` to the `paths` list in `phpstan.neon` in the next task, or run the path explicitly as above.)

- [ ] **Step 5: Commit**

```bash
git add bin/render-demo
git commit -m "feat(bin): add render-demo smoke script for AnsiDriver + Screen"
```

---

## Task 12: Composer extensions, full suite, static analysis, milestone tag

**Files:**
- Modify: `composer.json`
- Modify: `phpstan.neon` (add `bin` to paths)
- Modify: `ROADMAP.md`

- [ ] **Step 1: Add the terminal extensions to `composer.json`**

In `composer.json`, change the `require` block from:

```json
    "require": {
        "php": ">=8.5",
        "ext-mbstring": "*"
    },
```

to:

```json
    "require": {
        "php": ">=8.5",
        "ext-mbstring": "*",
        "ext-posix": "*",
        "ext-pcntl": "*"
    },
```

- [ ] **Step 2: Refresh the autoloader / validate composer**

Run:
```bash
composer validate --no-check-publish
composer dump-autoload
```
Expected: `./composer.json is valid` (a notice about `ext-posix`/`ext-pcntl` not being present in the platform is acceptable on systems lacking them; the real `AnsiDriver` requires them at runtime, and CI runs on a PHP build that has them).

- [ ] **Step 3: Add `bin` to PHPStan paths**

In `phpstan.neon`, change:

```neon
parameters:
    level: max
    paths:
        - src
        - tests
```

to:

```neon
parameters:
    level: max
    paths:
        - src
        - tests
        - bin
```

- [ ] **Step 4: Run the entire test suite**

Run: `./vendor/bin/pest`
Expected: PASS — every Plan 1 test plus the new Plan 2 tests are green. Roughly `Tests: 75+ passed` (Plan 1 ~35 + Plan 2: AnsiEncoder 4, DecodeResult 2, Key F11/F12 2, EscapeDecoder 15, HeadlessDriver 4, DriverException 2, AnsiDriver 5, DiffPresenter 6, Screen 8).

- [ ] **Step 5: Run PHPStan at max over the whole project**

Run: `./vendor/bin/phpstan analyse`
Expected: `[OK] No errors`. If a `pcntl`/`posix` symbol is flagged because the dev box lacks the extension, confirm the call site is gated by `function_exists(...)` (it is) — do **not** add a baseline; gate with `\defined(...)`/`\function_exists(...)` instead.

- [ ] **Step 6: Tag the layer complete**

```bash
git add composer.json phpstan.neon
git commit -m "chore: require ext-posix/ext-pcntl and analyse bin/"
git tag -a m1-driver-renderer -m "M1 driver & renderer complete (encoder, decoder, drivers, presenter, screen)"
```

- [ ] **Step 7: Update the roadmap status line**

Modify `ROADMAP.md`, in the "Where we are" section, change the active bullet to:

```markdown
- ▶️ **Now:** M1 Plan 2 (driver & renderer) built and green; next is M1 Plan 3 (views & application).
```

Then:
```bash
git add ROADMAP.md
git commit -m "docs: mark M1 driver & renderer complete in roadmap"
```

---

## What this plan deliberately leaves out (next plan)

- **Plan 3 — Views & application:** `View`, `Group` (compositing `DrawBuffer` rows into
  the back `Buffer` + event routing via `EventMask`), `StaticText`, `Background`,
  `Desktop`, `Menus\*` (MenuBar + StatusLine), `Application\{Program,Application}`, and
  the `tvguid01–03` headless acceptance tests that define M1 done. Plan 3 consumes
  `Terminal\Screen` (draws into `Screen::back()`, calls `flush()`/`pollEvents()`) and
  the `Driver` interface (swapping `HeadlessDriver`/`AnsiDriver`) — both finalised here.
- **Decoder hardening:** the multi-terminal corpus + parallel-verification workflow noted
  in Task 4 is a dedicated follow-up, not part of M1's walking skeleton.

---

## Self-review (performed by the plan author)

- **Spec coverage (this plan's slice):** `Drivers\AnsiEncoder` ✓ (Task 1); `Drivers\DecodeResult` ✓ (Task 2); `Drivers\EscapeDecoder` incl. `flushPending` + remainder ✓ (Task 4, with Key F11/F12 in Task 3); `Drivers\Driver` interface ✓ (Task 5); `Drivers\HeadlessDriver` ✓ (Task 6); `Exceptions\DriverException` ✓ (Task 7); `Drivers\AnsiDriver` ✓ (Task 8, with the un-testable bits isolated + `bin/` smoke check); `Rendering\DiffPresenter` ✓ (Task 9); `Terminal\Screen` ✓ (Task 10); `bin/render-demo` ✓ (Task 11); composer `ext-posix`/`ext-pcntl` ✓ (Task 12). Views/menus/application are explicitly out of scope and tracked for Plan 3.
- **Builds-on-real-signatures check:** uses `Buffer(width,height,?Cell)` with public readonly `width`/`height`, `at(x,y):Cell`, `put(x,y,Cell)`, `rows():list<string>`; `Cell(char,attr)` with `equals()`; `Attribute::fromByte(int)->toSgr()`; `Event::keyDown(KeyDownEvent)`, `Event::mouse(EventType,MouseEvent)`, `asKey()/asMouse()`, `KeyDownEvent(int,string='',int=0)`, `KeyDownEvent::is(Key)`, `MouseEvent(Point,int=0,...)`, `Key::Up/...->value`, `EventType::MouseDown/MouseUp`. All match the on-`main` sources read while authoring.
- **Placeholder scan:** none — every implementation step contains complete, runnable code; the single drafting artefact (the redundant `Key::tryFrom(0)` line in `decodeEscape`) is explicitly removed in Task 4 Step 4 before the test is run, so no dead code reaches a commit.
- **Type-consistency check:** `Driver::size():array{0:int,1:int}`, `pollInput():string`, `resized():bool` are identical across the interface, `HeadlessDriver`, `AnsiDriver`, and `Screen`'s consumption. `DecodeResult::$events` is `list<Event>`; `EscapeDecoder::decode():DecodeResult` and `flushPending():?Event` match `Screen::pollEvents()`'s usage. `DiffPresenter::present(Buffer,Buffer,AnsiEncoder):string` matches `Screen::flush()`. `AnsiEncoder` method names (`moveCursor`,`style`,`run`,`reset`,`clearScreen`,`hideCursor`,`showCursor`,`enterAltScreen`,`leaveAltScreen`,`enableMouse`,`disableMouse`) are used consistently by `AnsiDriver`, `DiffPresenter`, and the tests. `Screen::pollEvents():list<Event>` and `wasResized():bool` match the test expectations.
- **PHP 8.5 feature note:** typed `const array` for the decoder tables, `final readonly class` for `DecodeResult`, constructor promotion, union/return types, and `@param list<Event>` by-reference annotations are the modern features relied on — all available on the 8.5 floor; nothing fragile. The `AnsiDriver`'s injected `Closure(string):string` runner and resource params keep the real-I/O boundary thin and the pure logic unit-reachable.
