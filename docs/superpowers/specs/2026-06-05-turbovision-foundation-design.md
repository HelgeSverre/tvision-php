# TurboVision for PHP — Foundation (Milestone 1) Design

- **Date:** 2026-06-05
- **Status:** Approved (brainstorm complete) → ready for implementation planning
- **Package:** `helgesverre/turbovision` · namespace `HelgeSverre\TurboVision`
- **Scope of this spec:** Milestone 1 only (the "walking skeleton"). Later milestones
  (M2–M6) get their own spec→plan cycles.

## Context

We are re-implementing Borland's **Turbo Vision** text-mode UI framework in modern
PHP 8.5, distributed via Packagist. The reference-gathering phase is complete: the
full C++ source of Sergio Sigala's UNIX port (TVision 0.8), the doxygen class
reference, the Texinfo handbook, and 34 example programs are captured under
[`docs/references/`](../../references/) and [`examples/cpp/`](../../../examples/cpp/).
Key maps to consult while building: `docs/references/CLASS-INDEX.md` (every class +
proposed PHP name), `docs/references/CAPABILITIES.md` (what the library does + port
priorities), `docs/references/installation-handbook.md` (keyboard/screen/mouse/event
runtime behaviour).

This is the **design phase the original request deferred** ("figure out the
appropriate shape of the php library once we have gathered references").

## Decisions (the four forks settled during brainstorming)

1. **Fidelity = faithful core, modern skin.** Replicate the proven engine — view
   tree, event loop, draw buffer, palette chain, `execView` modality, command/event
   model — so behaviour matches the original and the C++ examples act as behavioral
   oracles. Expose a modern, ergonomic PHP API over it: typed enums (`Key`, `EventType`),
   typed event payloads, fluent/variadic construction, constructor promotion, named
   args. **Drop the `T` prefix** (`TView`→`View`); lean on namespaces.
2. **Terminal backend = pure-PHP ANSI/termios**, sitting behind a `Driver` interface.
   ANSI/VT escape output to STDOUT; raw mode via `stty`; non-blocking STDIN reads we
   decode ourselves; xterm SGR mouse mode; `SIGWINCH` via `ext-pcntl`. No system
   libraries. A `HeadlessDriver` (scripted input, captured output) is a first-class
   sibling for testing.
3. **First milestone = walking skeleton.** Smallest complete proof of the entire
   pipeline. **Acceptance = PHP `tvguid01–03` run on a real terminal AND pass headless
   buffer-snapshot tests** (empty app; status-line commands; menu bar + status line).
4. **Render model = double-buffered diff.** Views draw Cells into a `DrawBuffer`
   (faithful `TDrawBuffer`); the group tree composites into an in-memory back `Buffer`;
   a Presenter diffs back-vs-front each dirtied tick and emits only changed cells as
   minimal ANSI. Flicker-free, faithful to TV's buffer concept, and trivially
   snapshot-testable.

## Target API (the modern skin)

The concrete surface M1 must deliver — `tvguid03` (menu bar + status line + exit) in
the target API. This pins the "modern skin": faithful factory-override bones
(`initMenuBar`/`initStatusLine`, command codes, `run()`) with an idiomatic surface
(typed `Rect`/`Key`/`Cmd`, variadic `->items()`, constructor args, no `T` prefix, no
manual `TProgInit` constructor dance).

```php
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Menus\{MenuBar, MenuItem, SubMenu, StatusLine, StatusItem, StatusDef};
use HelgeSverre\TurboVision\Events\{Key, Cmd};
use HelgeSverre\TurboVision\Geometry\Rect;

final class HelloApp extends Application
{
    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return new MenuBar($bounds, new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit the program'),
        ));
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }
}

exit((new HelloApp())->run());
```

The smallest case, `tvguid01`, is just `final class HelloApp extends Application {}`
then `exit((new HelloApp())->run());` — inherited default factories supply an empty
desktop, menu bar, and status line.

## Goals (Milestone 1)

- A runnable `Application` subclass that boots a full-screen TUI, shows a desktop +
  default menu bar + status line, routes keyboard/mouse/command events, and quits
  cleanly restoring the terminal.
- The complete render pipeline: `View::draw()` → `DrawBuffer` → composite → diff →
  ANSI → STDOUT, with UTF-8 + 256-color output degrading to 16-color/mono.
- The complete input pipeline: STDIN bytes → escape-sequence decoder → typed `Event`
  → routed `handleEvent`.
- A `Driver` abstraction with `AnsiDriver` (real TTY) and `HeadlessDriver` (tests).
- Faithful but minimal `Menus\` (MenuBar + StatusLine) sufficient for `tvguid01–03`.
- Bulletproof terminal teardown on every exit path.

## Non-goals (deferred to later milestones)

- Windows, frames, scrolling, list viewers (M2).
- Pull-down menu navigation depth, dialogs, controls, validators, message boxes (M3).
- Text editor, file dialogs (M4); outline, color dialog (M5); help system, object
  streaming/persistence (M6).
- `NcursesDriver`, Linux VCS fast path, Gpm/moused (not needed; modern terminals only).
- Command-callback sugar (`->onClick()`); M1 uses faithful `handleEvent` routing only.

## Architecture & data flow

Two pipelines meet at the event loop in `Program::run()`. The **`Driver` interface is
the only component that touches the real terminal**; everything above it is pure,
deterministic, headless-testable logic.

```
INPUT:   STDIN (raw, non-blocking) ─▶ Driver::pollInput()
         ─▶ escape-sequence decoder ─▶ Event ─▶ Program dispatch
         ─▶ Group routes to focused subview chain ─▶ View::handleEvent()

OUTPUT:  View::draw() writes Cells ─▶ DrawBuffer ─▶ composited into back Buffer
         ─▶ Presenter.diff(front, back) ─▶ minimal ANSI ─▶ Driver::write() ─▶ STDOUT

SIGNALS: SIGWINCH ─▶ resize flag ─▶ Screen resized ─▶ full redraw
```

## Namespace & module layout

Root `HelgeSverre\TurboVision\`, concern-based sub-namespaces. **Bold** = exists in M1.

| Namespace | M1 contents | Later milestones |
|-----------|------------|------------------|
| **`Geometry\`** | `Point`, `Rect` | — |
| **`Drawing\`** | `Cell`, `Buffer`, `DrawBuffer`, `Palette`, `Color`, `Attribute` | — |
| **`Drivers\`** | `Driver` (interface), `AnsiDriver`, `HeadlessDriver`, `EscapeDecoder`, `AnsiEncoder` | `NcursesDriver?` |
| **`Events\`** | `Event`, `EventType`, `KeyDownEvent`, `MouseEvent`, `MessageEvent`, `Key`, `Cmd` | — |
| **`Views\`** | `View`, `Group`, `StaticText`, `Background`, `Desktop` | `Window`, `Frame`, `Scroller`, `ScrollBar`, `ListViewer`, … |
| **`Menus\`** | `MenuBar`, `MenuView`, `Menu`, `MenuItem`, `SubMenu`, `StatusLine`, `StatusItem`, `StatusDef` | `MenuBox`, `MenuPopup` |
| **`Application\`** | `Program`, `Application` | — |
| `Dialogs\`, `Editors\`, `Validators\`, `Help\`, `Persistence\` | — | P1–P3 |

## Components (M1) — responsibility · key interface · dependencies

### Geometry
- **`Point`** — immutable `(int $x, int $y)` value object. `equals()`, `add()`.
- **`Rect`** — immutable rectangle of two Points `(a, b)`. `Rect::of(ax,ay,bx,by)`,
  `width()`, `height()`, `contains(Point)`, `intersect(Rect)`, `move(dx,dy)`,
  `grow(dx,dy)`, `isEmpty()`. Faithful to `TRect`. Depends on: `Point`.

### Drawing
- **`Color`** — a color value (named 16, indexed 256, or truecolor) + capability-aware
  downgrade. **`Attribute`** — style bits (bold, underline, reverse, …).
- **`Cell`** — one screen cell: immutable `(string $char /* one grapheme */, Color $fg,
  Color $bg, int $attrs)`. A blank cell is the fill primitive.
- **`Buffer`** — `width × height` grid of Cells (the back and front screen buffers).
  `at(x,y): Cell`, `put(x,y,Cell)`, `fill(Rect,Cell)`, `resize(w,h)`, `rows(): string[]`
  (decorative-glyph rows for snapshot tests). Depends on: `Cell`.
- **`DrawBuffer`** — per-view drawing helper (faithful `TDrawBuffer`): `moveStr(x,str,
  attr)`, `moveChar(x,char,attr,count)`, `moveCStr(x,str,attrs)` with `~hotkey~`
  parsing, `putAttribute()`, `putChar()`. A view fills one; `Group` blits it into the
  back `Buffer` clipped to bounds. Depends on: `Cell`, `Palette`.
- **`Palette`** — logical→physical color mapping with the TV palette-chain semantics
  (a view maps its color indexes through its owner up to the root). M1 ships a default
  color palette and a monochrome palette. Depends on: `Color`.

### Drivers
- **`Driver`** (interface) — `init(): void`, `shutdown(): void`, `size(): array{int,int}`,
  `write(string $bytes): void`, `pollInput(int $timeoutMs): ?string`, `setRaw(bool):
  void`, `showCursor(bool): void`, `moveCursor(int $x, int $y): void`. The sole
  terminal boundary.
- **`AnsiDriver`** — real TTY. Raw mode via shelled `stty raw -echo` (restored on
  shutdown); alt-screen enter/leave; STDIN via `stream_select` + non-blocking reads;
  STDOUT writes; queries size via `stty size`/`TIOCGWINSZ`. Throws a typed exception
  if STDIN/STDOUT is not a TTY or `stty` is unavailable, **before** mutating terminal
  state. Depends on: `ext-posix`, `ext-pcntl`.
- **`HeadlessDriver`** — scripted input queue, in-memory output capture, fixed size;
  exposes the resulting `Buffer` for assertions. No real I/O.
- **`EscapeDecoder`** — pure: incremental byte stream → `Event`s. Decodes printable
  UTF-8, arrows/function/nav keys (`\e[A`, `\eOP`, …), Alt/`ESC`-prefixed, and SGR
  mouse (`\e[<b;x;yM/m`). Total function: unknown sequences → an "unknown key" event,
  never an exception.
- **`AnsiEncoder`** — pure: a run `(x, y, string, style)` → minimal ANSI (cursor move +
  SGR + text), capability-aware (truecolor/256/16/mono).

### Events
- **`EventType`** — enum: `Nothing`, `MouseDown`, `MouseUp`, `MouseMove`, `MouseAuto`,
  `KeyDown`, `Command`, `Broadcast` (faithful `evXxx`).
- **`Event`** — **mutable** tagged object: `EventType $what` + one payload
  (`KeyDownEvent|MouseEvent|MessageEvent`). Mutability is intentional and faithful:
  handlers consume an event via `clearEvent()` (sets `what = Nothing`) so it stops
  propagating. Helpers: `isKey()`, `isCommand()`, `clear()`.
- **`KeyDownEvent`** — `(string $char, int $code, Key|null $key, int $modifiers)`.
- **`MouseEvent`** — `(Point $where, int $buttons, bool $double, int $wheel)`.
- **`MessageEvent`** — `(int $command, mixed $info)`.
- **`Key`** — enum of special key codes (faithful `kbXxx`: `Enter`, `Esc`, `Tab`, `F1`…,
  `AltX`, `CtrlC`, arrows, …).
- **`Cmd`** — standard command constants (faithful `cmXxx`: `Quit`, `Ok`, `Cancel`,
  `Close`, `Zoom`, `Next`, …). **Commands are `int`** on the wire, so user-defined
  commands are just ints — fully extensible; `Cmd` is the convenience holder for built-ins.

### Views
- **`View`** — base of all visible objects. `Rect $bounds`, `int $state` / `int $options`
  / `int $growMode` flags (faithful `sf*`/`of*`/`gf*`), `EventType` masks. Core methods:
  `draw(): void` (fill a `DrawBuffer`, paint via owner), `handleEvent(Event): void`
  (the primary extension point), `getPalette(): Palette`, `setState(int $flag, bool)`,
  `drawView()`, `setBounds()`, `sizeLimits()`. Depends on: `Geometry`, `Drawing`, `Events`.
- **`Group`** — a `View` owning an ordered subview list (Z-order). Composites children
  into the back buffer with clipping; routes events (focused-chain for keys, positional
  for mouse, fan-out for broadcasts); manages focus (`selectNext`, `focusView`).
  Depends on: `View`.
- **`StaticText`** — non-interactive wrapped text. **`Background`** — pattern fill view.
  **`Desktop`** — the backdrop `Group` that will host windows in M2.

### Menus
- **`MenuItem` / `SubMenu`** — definition objects: label (with `~hotkey~`), command,
  shortcut `Key`, help hint; `SubMenu` nests items via `->items(...)`.
- **`StatusItem` / `StatusDef`** — status-line definition: hint text, key, command,
  scoped to a help-context range.
- **`MenuView`** (abstract) / **`MenuBar`** — draws the top bar; opens menus and
  dispatches commands on hotkey/click. M3 deepens pull-down navigation; M1 needs enough
  for `tvguid03`.
- **`StatusLine`** — draws bottom hints; maps keys → commands; context-sensitive.

### Application
- **`Program`** — owns `Driver`/`Buffer`s/`Desktop`/`MenuBar`/`StatusLine`; runs the
  event loop (`getEvent`, `handleEvent`, `idle`, `putEvent`); manages the enabled
  command set; ends on `cmQuit`. Factory hooks: `initDeskTop`, `initMenuBar`,
  `initStatusLine`, `initScreen`.
- **`Application`** — `Program` + terminal init/teardown + sensible default
  menu/status/desktop factories. The class users subclass.

## Event loop & lifecycle

```
Application::run(): int
  driver.init(); driver.setRaw(true); enter alt-screen; hide cursor      # guarded by try/finally
  build Desktop + MenuBar + StatusLine; full draw
  loop:
    if resizeFlag:  size = driver.size(); resize buffers; redraw all
    ev = getEvent()                     # decode pending STDIN; else mouse; else idle()
    if ev.what !== Nothing: handleEvent(ev)   # routed down the view tree; clearEvent stops it
    if dirty: render()                  # composite tree -> back buffer -> diff -> ANSI
    if endState (cmQuit): break
  finally: driver.shutdown()            # restore cooked mode, main screen, cursor
  return exitCode
```

Input polling uses `stream_select` on STDIN with a small timeout, so the loop idles
without busy-waiting and still services SIGWINCH and future timers. `getEvent` mirrors
TV's order: pending keystrokes → mouse → `idle()`.

## Rendering pipeline

Views never write to STDOUT. `draw()` fills a `DrawBuffer`; `Group` blits it into the
back `Buffer`, clipped to the view rect and respecting Z-order. Once per dirtied tick
the **Presenter** diffs back-vs-front, coalesces changed cells into horizontal runs of
equal style, emits `cursor-move + SGR + text` per run via `AnsiEncoder`, then sets
`front ← back`. CP437 box-drawing/semigraphic glyphs map to their Unicode equivalents.
Output is UTF-8 + 256-color by default, degrading to 16-color/monochrome (bold+inverse)
by detected terminal capability.

## Error handling & terminal restoration

**Cardinal rule: never leave the user's terminal wedged.**
- Every raw-mode / alt-screen entry is paired with `finally` teardown.
- A top-level handler plus `register_shutdown_function` restores cooked mode, the main
  screen, and the cursor on any exception, fatal error, or signal.
- `AnsiDriver::init()` validates it is attached to a TTY and that `stty` exists, and
  throws a clear typed exception **before** changing any terminal state.
- The `EscapeDecoder` is total: malformed/unknown input degrades to an "unknown key"
  event; it never throws.
- Typed exception hierarchy rooted at a `TurboVisionException`.

## Testing strategy

- **Pure units:** Geometry; `Cell`/`Buffer`; `DrawBuffer` (incl. `~hotkey~` parsing);
  `Palette` resolution; `EscapeDecoder` (bytes→Event table); `AnsiEncoder` (run→bytes
  table).
- **Headless integration:** drive a full `Application` via `HeadlessDriver` with
  scripted keystrokes; assert on `Buffer::rows()` **snapshots** and on emitted ANSI.
- **Example acceptance (definition of done):** PHP translations of `tvguid01–03` run
  headless and snapshot-match expected screens; quit cleanly.
- **Tooling:** Pest (preferred) or PHPUnit; PHPStan at max; `bin/demo` runner for
  eyeballing on a real terminal; GitHub Actions CI (lint + static analysis + tests).

## Packaging

- PSR-4 `HelgeSverre\TurboVision\` → `src/`.
- `composer.json`: `"php": ">=8.5"` (honoring the explicit 8.5 target; code uses 8.5
  idioms), `ext-posix`, `ext-pcntl`, `ext-mbstring`. No system-library requirements.
- **MIT** license for our code, plus a `NOTICE` crediting Borland (original Turbo
  Vision, public domain) and Sergio Sigala (UNIX port, BSD-style). The gathered
  `docs/references/` is retained for provenance.
- README quick-start mirroring the `tvguid01` example.

## Milestone 1 — acceptance criteria

1. `composer install` works on a stock PHP 8.5 with `ext-posix`/`ext-pcntl`/`ext-mbstring`.
2. A `HelloApp extends Application` boots a full-screen TUI on a real terminal: desktop
   backdrop, default menu bar, status line; `Alt-X` / the Exit menu quits and the
   terminal is fully restored (cooked mode, main screen, cursor visible).
3. PHP `tvguid01`, `tvguid02`, `tvguid03` exist under `examples/php/tutorial/` and pass
   headless snapshot tests of the rendered `Buffer`.
4. Unit + headless test suites green; PHPStan max clean; CI green.
5. Resizing the terminal (SIGWINCH) reflows and repaints without corruption.

## Roadmap after M1

- **M2 — Windowing:** `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer`
  (acceptance: tvguid04–10).
- **M3 — Menus-deep + Dialogs:** pull-down navigation, `Dialog`, `Button`, `InputLine`,
  `CheckBoxes`, `RadioButtons`, `Label`, `ListBox`, `MessageBox` (acceptance: tvguid11–16).
- **M4 — Editor & files:** `Validator` family, `Editor`/`FileEditor`/`EditWindow`/`Memo`,
  `FileDialog`/`ChDirDialog`.
- **M5 — Outline & color:** `OutlineViewer`/`Outline`/`Node`, `ColorDialog` + selectors.
- **M6 — Help & persistence:** help system (+ `tvhc` or a PHP-native help format),
  object streaming/resources — or an idiomatic PHP serialization replacement.

Each milestone is its own spec → plan → build cycle.

## Risks & open questions

- **PHP 8.5 floor limits adopters** (8.5 is very new). Honored per explicit request;
  revisit to `>=8.3` if adoption matters more than bleeding-edge idioms.
- **`stty`-based raw mode** assumes a POSIX `stty`; Windows is out of scope for M1
  (document as a known limitation; a future driver could target Windows VT/conpty).
- **Wide/combining graphemes & East-Asian width** in the cell model — handle ASCII/BMP
  cleanly in M1; full wcwidth-style width handling can land with the editor (M4).
- **Object streaming fork (M6)** — port the binary streamer vs PHP-native serialization
  is left open until M6's own spec.
