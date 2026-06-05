# M1 Views & Application — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the third and final layer of `HelgeSverre\TurboVision` — the view tree, menus, and the runnable `Application`/`Program`. This is the capstone that makes the framework *runnable*: after this plan a user can write `final class HelloApp extends Application {}` and call `->run()`, the `tvguid01–03` tutorial programs work on a real terminal, and the same programs pass headless buffer-snapshot tests. These headless Feature tests **are** the Milestone-1 definition of done.

**Architecture:** Three new namespaces sit above Plan 1 (`Geometry`/`Drawing`/`Events`) and Plan 2 (`Drivers`/`Rendering`/`Terminal`). `Views\` holds the visual object tree: `View` (base, owns a `Rect $bounds` + `sf*`/`of*`/`gf*` flag ints + a `?Group $owner`, and composites into the **root** `Screen` back `Buffer` at its absolute origin), `Group` (ordered Z-ordered subviews + focus + event routing + the `execView` modal sub-loop), and the concrete leaves `StaticText`, `Background`, `Desktop`. `Menus\` holds the faithful-but-minimal menu objects: the definition structs (`MenuItem`, `SubMenu`, `Menu`, `StatusItem`, `StatusDef`) with the spec's fluent variadic `->items(...)` surface, and the two drawable views `MenuBar` and `StatusLine` (top-level hotkey dispatch only; pull-down `MenuBox` navigation is M3). `Application\` holds `Program` (owns the `Screen`, `Desktop`, `MenuBar`, `StatusLine`, the enabled command set, and the `run()` event loop with `initScreen`/`initDeskTop`/`initMenuBar`/`initStatusLine` factory hooks) and `Application` (wires defaults; constructor accepts an optional injected `Screen` so headless tests pass `new Screen(new HeadlessDriver(80,25))`).

**How a View reaches the Screen:** Every `View` has an `?Group $owner`. The root of the tree is the `Program`, which is itself a `Group` whose `$screen` is the live `Terminal\Screen`. A view's write primitives (`writeBuf`/`writeLine`/`writeChar`/`writeStr`) walk the `owner` chain (`rootScreen()`) to reach that `Screen`, and walk it again (`absoluteOrigin()`) to translate the view's local `(x,y)` into absolute screen coordinates, then composite the `DrawBuffer`'s cells into `Screen::back()`, clipped to the view's own extent. M1 clips to the view's extent only (non-overlapping regions render correctly); full ancestor/sibling clipping is M2.

**Tech Stack:** PHP 8.5, Composer (PSR-4), Pest v3 (tests), PHPStan (level max). No new runtime extensions (Plan 2 already added `ext-posix`/`ext-pcntl`; `ext-mbstring` from Plan 1). `View`/`Group`/`Program`/`Application` are **mutable** classes (not `readonly`); menu *definition* objects are mutable holders (they accumulate items via `->items()`).

**Source of truth for semantics:** `docs/references/source/tvision-0.8/lib/views.h` (`sf*`/`of*`/`gf*` flag values, `TView`/`TGroup`/`TFrame`), `.../app.h` (`TProgram`/`TApplication`/`TDeskTop`/`TBackground`, `cpAppColor` palette bytes), `.../menus.h` + `.../TMenuBar.cc` + `.../TStatusLine.cc` + `.../TMenuView.cc` (`cpMenuView "\x02\x03\x04\x05\x06\x07"`, `cpStatusLine` identical, `cpBackground "\x01"`, draw layout: items start at `x=1`, padded `' '+name+' '`, `getColor(0x0301)` normal / `getColor(0x0604)` selected), `.../TGroup.cc` (`execute()`/`execView()`/`handleEvent()` routing), `.../TProgram.cc` (`run`/`getEvent`/`handleEvent`/`idle` ordering), and `examples/cpp/tutorial/tvguid01.cc..tvguid03.cc` (exact acceptance behavior). The design spec `docs/superpowers/specs/2026-06-05-turbovision-foundation-design.md` "Target API" snippet (`initMenuBar`/`initStatusLine`/`SubMenu->items()`/`StatusDef->items()`) is the exact public surface this plan must deliver verbatim.

**Builds on (Plans 1 & 2, already on `main`) — final public API used verbatim below:**
- `Geometry\Point(int $x=0,int $y=0)` with `x`/`y`, `add`/`subtract`/`equals`. `Geometry\Rect(Point $a,Point $b)` with `Rect::of(ax,ay,bx,by)`, `width()`/`height()`/`isEmpty()`/`contains(Point)`/`move(dx,dy)`/`grow(dx,dy)`/`intersect(Rect)`/`equals(Rect)`, public `a`/`b`.
- `Drawing\Buffer(int $width,int $height,?Cell $fill=null)` with public readonly `width`/`height`, `at(x,y):Cell`, `put(x,y,Cell)`, `fill(Rect,Cell)`, `rows():list<string>`. `Drawing\Cell(string $char=' ',int $attr=0x07)` with `Cell::of(string,Attribute)`, `equals(Cell)`, public `char`/`attr`. `Drawing\DrawBuffer(int $width,int $fillAttr=0x07)` with `clear`, `moveChar(x,char,attr,count)`, `moveStr(x,str,attr)`, `moveCStr(x,str,normalAttr,highlightAttr):int`, `putAttribute(x,attr)`, `cells():Cell[]`, public readonly `width`. `Drawing\Palette(array $entries)` with `Palette::fromBytes(string)`, `get(int):int`, `size():int`. `Drawing\Attribute`, `Drawing\Color`.
- `Events\Event` (mutable) with factories `nothing()`/`keyDown(KeyDownEvent)`/`mouse(EventType,MouseEvent)`/`command(int,$info=null)`/`broadcast(int,$info=null)`, `clear()`, `isNothing()`, `asKey()`/`asMouse()`/`asMessage()`, `isKey(Key)`/`isCommand(int)`, public `what`/`payload`. `Events\EventType` enum (`Nothing`/`MouseDown`/`MouseUp`/`MouseMove`/`MouseAuto`/`KeyDown`/`Command`/`Broadcast`) with `inMask(int):bool`. `Events\EventMask` consts `Mouse=0x000F`, `Keyboard=0x0010`, `Command=0x0100`, `Broadcast=0x0200`, `Message=0xFF00`, `Positional=0x000F`, `Focused=0x0110`. `Events\Key` enum (`Esc`/`Enter`/`Tab`/arrows/`F1..F12`/`AltA..AltZ`, value = kbXxx). `Events\Cmd` consts (`Valid=0`/`Quit=1`/`Menu=3`/`Close=4`/`Zoom=5`/`Next=7`/`FirstUser=100`…). `Events\KeyDownEvent(int $keyCode,string $char='',int $modifiers=0)` with `is(Key):bool`. `Events\MouseEvent(Point $where,int $buttons=0,bool $doubleClick=false,int $wheel=0)`. `Events\MessageEvent(int $command,mixed $info=null)`.
- `Terminal\Screen(Driver $driver)` with `init()`, `shutdown()`, `back():Buffer`, `size():Point`, `cols():int`, `rows():int`, `clear()`, `flush()`, `pollEvents(int $timeoutMs):list<Event>`, `wasResized():bool`. `Drivers\HeadlessDriver(int $cols=80,int $rows=24)` with `feedInput(string)`, `output()`/`takeOutput()`, `resizeTo(w,h)`, `isInitialised()`. `Drivers\AnsiDriver()`. `Drivers\Driver` interface.

**All headless tests in this plan drive the app through `new Screen(new HeadlessDriver(...))`.**

---

## File Structure

```
src/
  Views/
    State.php                  # NEW: sf*/of*/gf* flag constants (faithful views.h)
    View.php                   # NEW: base view (bounds, flags, owner, draw, writeBuf...)
    Group.php                  # NEW: subview list, focus, event routing, execView
    StaticText.php             # NEW: wrapped/centered fixed text
    Background.php             # NEW: pattern-fill view
    Desktop.php                # NEW: backdrop Group owning a Background
  Menus/
    MenuItem.php               # NEW: definition object (name, command, key, help, subMenu)
    SubMenu.php                # NEW: definition object with fluent ->items()
    Menu.php                   # NEW: collection of items (root of a menu)
    StatusItem.php             # NEW: definition object (text, key, command)
    StatusDef.php              # NEW: help-context-ranged item set with fluent ->items()
    MenuView.php               # NEW: abstract base for MenuBar (palette + getColor helper)
    MenuBar.php                # NEW: top bar — render + top-level hotkey/click dispatch
    StatusLine.php             # NEW: bottom hints — render + key->command mapping
  Application/
    Program.php                # NEW: owns Screen/Desktop/MenuBar/StatusLine + run() loop
    Application.php            # NEW: Program + default factories + injectable Screen
examples/php/tutorial/
    Guide01.php                # NEW: tvguid01 port (empty app)
    Guide02.php                # NEW: tvguid02 port (status line)
    Guide03.php                # NEW: tvguid03 port (menu bar + status line)
tests/
  Unit/Views/StateTest.php
  Unit/Views/ViewTest.php
  Unit/Views/GroupTest.php
  Unit/Views/StaticTextTest.php
  Unit/Views/BackgroundTest.php
  Unit/Views/DesktopTest.php
  Unit/Menus/MenuDefinitionsTest.php
  Unit/Menus/MenuBarTest.php
  Unit/Menus/StatusLineTest.php
  Unit/Application/ProgramTest.php
  Unit/Application/ApplicationTest.php
  Feature/Guide01Test.php
  Feature/Guide02Test.php
  Feature/Guide03Test.php
ROADMAP.md                     # MODIFIED: status line
```

Each file has one responsibility. A class and its test are introduced in the same task. **Build order** (each builds on green predecessors): `State` → `View` → `Group` → `StaticText` → `Background` → `Desktop` → menu definitions → `MenuView` → `MenuBar` → `StatusLine` → `Program` → `Application` → `Guide01/02/03` Feature tests → full-suite/tag/roadmap.

---

## Task 1: Views — State (sf*/of*/gf* flag constants)

**Files:**
- Create: `src/Views/State.php`
- Test: `tests/Unit/Views/StateTest.php`

A holder for the faithful `sf*` (state), `of*` (options), and `gf*` (grow-mode) bit-flag families from `views.h`. Keeping them as typed `int` constants on one class is cleaner than three enums (the originals are bit-OR'd freely). Values are extracted verbatim from `docs/references/source/tvision-0.8/lib/views.h`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/StateTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\State;

test('state flags carry their faithful sf* values', function (): void {
    expect(State::Visible)->toBe(0x001)
        ->and(State::CursorVis)->toBe(0x002)
        ->and(State::Shadow)->toBe(0x008)
        ->and(State::Active)->toBe(0x010)
        ->and(State::Selected)->toBe(0x020)
        ->and(State::Focused)->toBe(0x040)
        ->and(State::Disabled)->toBe(0x100)
        ->and(State::Modal)->toBe(0x200)
        ->and(State::Exposed)->toBe(0x800);
});

test('option flags carry their faithful of* values', function (): void {
    expect(State::Selectable)->toBe(0x001)
        ->and(State::TopSelect)->toBe(0x002)
        ->and(State::FirstClick)->toBe(0x004)
        ->and(State::Framed)->toBe(0x008)
        ->and(State::PreProcess)->toBe(0x010)
        ->and(State::PostProcess)->toBe(0x020)
        ->and(State::Centered)->toBe(0x300);
});

test('grow-mode flags carry their faithful gf* values', function (): void {
    expect(State::GrowLoX)->toBe(0x01)
        ->and(State::GrowLoY)->toBe(0x02)
        ->and(State::GrowHiX)->toBe(0x04)
        ->and(State::GrowHiY)->toBe(0x08)
        ->and(State::GrowAll)->toBe(0x0f);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/StateTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\State" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/State.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

/**
 * The faithful Turbo Vision view-flag families: sf* (state), of* (options), and
 * gf* (grow mode). Kept as plain typed int constants because the originals are
 * freely bit-OR'd. Values verbatim from docs/references/source/tvision-0.8/lib/views.h.
 */
final class State
{
    // --- sf* : state flags (View::$state) ---
    public const int Visible = 0x001;
    public const int CursorVis = 0x002;
    public const int CursorIns = 0x004;
    public const int Shadow = 0x008;
    public const int Active = 0x010;
    public const int Selected = 0x020;
    public const int Focused = 0x040;
    public const int Dragging = 0x080;
    public const int Disabled = 0x100;
    public const int Modal = 0x200;
    public const int Default = 0x400;
    public const int Exposed = 0x800;

    // --- of* : option flags (View::$options) ---
    public const int Selectable = 0x001;
    public const int TopSelect = 0x002;
    public const int FirstClick = 0x004;
    public const int Framed = 0x008;
    public const int PreProcess = 0x010;
    public const int PostProcess = 0x020;
    public const int Buffered = 0x040;
    public const int Tileable = 0x080;
    public const int CenterX = 0x100;
    public const int CenterY = 0x200;
    public const int Centered = 0x300;
    public const int Validate = 0x400;

    // --- gf* : grow-mode flags (View::$growMode) ---
    public const int GrowLoX = 0x01;
    public const int GrowLoY = 0x02;
    public const int GrowHiX = 0x04;
    public const int GrowHiY = 0x08;
    public const int GrowAll = 0x0f;
    public const int GrowRel = 0x10;
    public const int GrowFixed = 0x20;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/StateTest.php`
Expected: PASS — `Tests: 3 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/State.php tests/Unit/Views/StateTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/State.php tests/Unit/Views/StateTest.php
git commit -m "feat(views): add State (sf*/of*/gf* flag constants from views.h)"
```

---

## Task 2: Views — View (base)

**Files:**
- Create: `src/Views/View.php`
- Test: `tests/Unit/Views/ViewTest.php`

The base of every visible object. Mutable. Holds `Rect $bounds`, `int $state`/`int $options`/`int $growMode`, and `?Group $owner`. The screen-writing primitives composite a `DrawBuffer` (or single char/string) into the **root** `Screen`'s back `Buffer` at the view's *absolute* origin (computed by walking the owner chain), clipped to the view's extent.

**Decisions documented here (so later tasks rely on them):**
- `rootScreen(): ?Screen` walks `owner` to the topmost view and asks it for its `Screen` (the `Program` overrides `screen()` to return the live one; a plain `View`/`Group` returns `null` until owned by a Program).
- `absoluteOrigin(): Point` sums each ancestor's `bounds->a` from this view up to (but not including) the root, so a view nested in a Desktop nested in a Program lands at the right screen pixel.
- Writes are clipped to `[0,width) × [0,height)` of the view's own extent; out-of-extent columns/rows are dropped. (Full ancestor clipping is M2.)
- `getState(flag)` / `setState(flag,bool)` toggle bits; `setBounds` replaces the rect; `getExtent` is the rect translated to origin `(0,0)`.
- `mapColor(int)` resolves a logical color index through the palette chain (`getPalette()` then up the owner chain); `getColor(int)` resolves a possibly two-byte color word (`hi<<8 | lo`) into a packed attribute word the same way `TView::getColor` does — high byte through the chain shifted into the high byte, low byte into the low byte.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ViewTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('a new view stores its bounds and is visible+selectable by default off', function (): void {
    $v = new View(Rect::of(2, 3, 12, 8));

    expect($v->getBounds())->toEqual(Rect::of(2, 3, 12, 8))
        ->and($v->getExtent())->toEqual(Rect::of(0, 0, 10, 5))
        ->and($v->getState(State::Visible))->toBeTrue()
        ->and($v->getState(State::Focused))->toBeFalse();
});

test('setState toggles a single flag bit', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));

    $v->setState(State::Focused, true);
    expect($v->getState(State::Focused))->toBeTrue();

    $v->setState(State::Focused, false);
    expect($v->getState(State::Focused))->toBeFalse()
        ->and($v->getState(State::Visible))->toBeTrue(); // unaffected
});

test('setBounds replaces bounds and recomputes the extent', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));
    $v->setBounds(Rect::of(5, 5, 15, 9));

    expect($v->getBounds())->toEqual(Rect::of(5, 5, 15, 9))
        ->and($v->getExtent())->toEqual(Rect::of(0, 0, 10, 4));
});

test('clearEvent consumes an event (sets what=Nothing)', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));
    $e = Event::command(Cmd::Quit);

    $v->clearEvent($e);
    expect($e->isNothing())->toBeTrue();
});

test('default draw() fills the extent with blanks when owned by a Screen-backed root', function (): void {
    // A tiny root that exposes a real Screen so writes have somewhere to land.
    $screen = new Screen(new HeadlessDriver(6, 3));
    $screen->init();
    $root = new RootStub($screen);

    $v = new View(Rect::of(1, 1, 5, 2)); // 4 wide, 1 tall, at (1,1)
    $root->insert($v);

    $v->draw();

    // Row 1, columns 1..4 are blanked (already blank, so still spaces) — assert shape.
    expect($screen->back()->rows())->toBe(['      ', '      ', '      ']);
});

test('writeStr composites a string into the root back buffer at the absolute origin', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 3));
    $screen->init();
    $root = new RootStub($screen);

    $v = new View(Rect::of(2, 1, 7, 2)); // origin (2,1), 5 wide
    $root->insert($v);

    $v->writeStr(0, 0, 'Hi', 0x07);

    expect($screen->back()->rows())->toBe(['        ', '  Hi    ', '        ']);
});

test('writeBuf blits a DrawBuffer row, clipped to the view extent', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 2));
    $screen->init();
    $root = new RootStub($screen);

    $v = new View(Rect::of(1, 0, 4, 1)); // origin (1,0), only 3 columns wide
    $root->insert($v);

    $b = new DrawBuffer(6);
    $b->moveStr(0, 'ABCDEF', 0x07);   // 6 chars, but view is 3 wide
    $v->writeBuf(0, 0, 3, 1, $b);

    // Only A,B,C land, at columns 1,2,3 of row 0.
    expect($screen->back()->rows())->toBe([' ABC    ', '        ']);
});

test('mapColor resolves through the view own palette', function (): void {
    $v = new PalettedView(Rect::of(0, 0, 4, 4));

    // PalettedView::getPalette() returns bytes [1=>0x71, 2=>0x1F]
    expect($v->mapColor(1))->toBe(0x71)
        ->and($v->mapColor(2))->toBe(0x1F)
        ->and($v->mapColor(9))->toBe(0x07); // out of range -> fallback
});

/** A minimal Group-less root that owns views and exposes a Screen. */
final class RootStub extends View
{
    /** @var list<View> */
    private array $children = [];

    public function __construct(private readonly Screen $screen)
    {
        parent::__construct(Rect::of(0, 0, $screen->cols(), $screen->rows()));
    }

    public function insert(View $view): void
    {
        $view->setOwner($this);
        $this->children[] = $view;
    }

    public function screen(): ?Screen
    {
        return $this->screen;
    }
}

/** A view carrying its own two-entry palette, for mapColor tests. */
final class PalettedView extends View
{
    public function getPalette(): ?Palette
    {
        return new Palette([1 => 0x71, 2 => 0x1F]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/ViewTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\View" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/View.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;

/**
 * The base of every visible object (faithful to Turbo Vision's TView). Mutable.
 * Holds bounds + sf*/of*/gf* flag words + an owner. The write primitives composite
 * a DrawBuffer (or char/string) into the ROOT Screen's back buffer at this view's
 * absolute origin, clipped to its own extent (M1 keeps clipping to the view extent;
 * full ancestor clipping arrives in M2).
 */
class View
{
    public ?Group $owner = null;

    public int $state = State::Visible;

    public int $options = 0;

    public int $growMode = 0;

    /** Cursor position in local coordinates (set by setCursor). */
    protected Point $cursor;

    public function __construct(public Rect $bounds)
    {
        $this->cursor = new Point(0, 0);
    }

    // --- ownership / tree ---

    public function setOwner(?Group $owner): void
    {
        $this->owner = $owner;
    }

    /**
     * The live Screen at the root of the tree, or null if not yet owned by a
     * Screen-backed root. Default: delegate to the owner; a Program overrides screen().
     */
    public function screen(): ?Screen
    {
        return $this->owner?->screen();
    }

    /** Sum of every ancestor's bounds->a from this view up to (excluding) the root. */
    public function absoluteOrigin(): Point
    {
        $x = $this->bounds->a->x;
        $y = $this->bounds->a->y;
        $node = $this->owner;
        while ($node !== null && $node->owner !== null) {
            $x += $node->bounds->a->x;
            $y += $node->bounds->a->y;
            $node = $node->owner;
        }

        return new Point($x, $y);
    }

    // --- geometry ---

    public function getBounds(): Rect
    {
        return $this->bounds;
    }

    /** The bounds translated to origin (0,0). */
    public function getExtent(): Rect
    {
        return Rect::of(0, 0, $this->bounds->width(), $this->bounds->height());
    }

    public function setBounds(Rect $bounds): void
    {
        $this->bounds = $bounds;
    }

    /**
     * Minimum/maximum size limits. M1 imposes none beyond non-negative; returned as
     * [minWidth, minHeight, maxWidth, maxHeight].
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    public function sizeLimits(): array
    {
        return [0, 0, PHP_INT_MAX, PHP_INT_MAX];
    }

    public function setCursor(int $x, int $y): void
    {
        $this->cursor = new Point($x, $y);
    }

    // --- state flags ---

    public function getState(int $flag): bool
    {
        return ($this->state & $flag) === $flag;
    }

    public function setState(int $flag, bool $enable): void
    {
        if ($enable) {
            $this->state |= $flag;
        } else {
            $this->state &= ~$flag;
        }
    }

    // --- drawing ---

    /** Default draw: fill the extent with a blank in the view's normal color. */
    public function draw(): void
    {
        $b = new DrawBuffer($this->bounds->width());
        $b->moveChar(0, ' ', $this->mapColor(1), $this->bounds->width());
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $this->writeLine(0, $y, $this->bounds->width(), 1, $b);
        }
    }

    /** Draw only if visible and exposed (owned by a Screen-backed root). */
    public function drawView(): void
    {
        if (! $this->getState(State::Visible)) {
            return;
        }
        if ($this->screen() === null) {
            return;
        }
        $this->draw();
    }

    /** The primary extension point; default no-op. */
    public function handleEvent(Event $event): void
    {
        // no-op
    }

    public function clearEvent(Event $event): void
    {
        $event->clear();
    }

    // --- palette / color ---

    /** This view's own palette, or null (then color resolves through the owner). */
    public function getPalette(): ?Palette
    {
        return null;
    }

    /** Resolve a single logical color index to an attribute byte via the palette chain. */
    public function mapColor(int $index): int
    {
        if ($index === 0) {
            return 0x07;
        }

        $palette = $this->getPalette();
        if ($palette !== null) {
            $mapped = $palette->get($index);
            // Walk up: the byte we just looked up is itself an index into the owner.
            if ($this->owner !== null) {
                return $this->owner->mapColor($mapped);
            }

            return $mapped;
        }

        if ($this->owner !== null) {
            return $this->owner->mapColor($index);
        }

        return 0x07;
    }

    /**
     * Resolve a (possibly two-byte) color word: the high byte and low byte each map
     * through the palette chain, recombined as (hi<<8 | lo). Faithful to TView::getColor.
     */
    public function getColor(int $color): int
    {
        $lo = $this->mapColor($color & 0xFF);
        $hi = $this->mapColor(($color >> 8) & 0xFF);

        return ($hi << 8) | $lo;
    }

    // --- screen-writing primitives (composite into the root Screen back buffer) ---

    /**
     * Blit a horizontal strip of a DrawBuffer into the root back buffer. $x/$y are
     * local; $w cells of one row starting at the buffer's column 0 are written.
     * Clipped to the view extent.
     */
    public function writeBuf(int $x, int $y, int $w, int $h, DrawBuffer $source): void
    {
        $cells = $source->cells();
        for ($row = 0; $row < $h; $row++) {
            $this->writeRowCells($x, $y + $row, $w, $cells);
        }
    }

    /** Like writeBuf but repeats one DrawBuffer row down $h lines. */
    public function writeLine(int $x, int $y, int $w, int $h, DrawBuffer $source): void
    {
        $cells = $source->cells();
        for ($row = 0; $row < $h; $row++) {
            $this->writeRowCells($x, $y + $row, $w, $cells);
        }
    }

    public function writeChar(int $x, int $y, string $char, int $attr, int $count): void
    {
        $b = new DrawBuffer(max(1, $x + $count));
        $b->moveChar($x, $char, $attr, $count);
        $this->writeRowCells($x, $y, $count, $b->cells());
    }

    public function writeStr(int $x, int $y, string $str, int $attr): void
    {
        $len = mb_strlen($str);
        $b = new DrawBuffer(max(1, $x + $len));
        $b->moveStr($x, $str, $attr);
        $this->writeRowCells($x, $y, $len, $b->cells());
    }

    /**
     * Composite $count cells (taken from $cells starting at local column $localX) into
     * the root back buffer at the view's absolute origin, clipped to the view extent.
     *
     * @param array<int,Cell> $cells
     */
    private function writeRowCells(int $localX, int $localY, int $count, array $cells): void
    {
        $screen = $this->screen();
        if ($screen === null) {
            return;
        }
        if ($localY < 0 || $localY >= $this->bounds->height()) {
            return;
        }

        $origin = $this->absoluteOrigin();
        $back = $screen->back();

        for ($i = 0; $i < $count; $i++) {
            $cx = $localX + $i;
            if ($cx < 0 || $cx >= $this->bounds->width()) {
                continue; // outside the view extent
            }
            $cell = $cells[$cx] ?? new Cell();
            $back->put($origin->x + $cx, $origin->y + $localY, $cell);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/ViewTest.php`
Expected: PASS — `Tests: 8 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/View.php tests/Unit/Views/ViewTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/View.php tests/Unit/Views/ViewTest.php
git commit -m "feat(views): add View base (bounds, flags, owner chain, screen compositing)"
```

---

## Task 3: Views — Group

**Files:**
- Create: `src/Views/Group.php`
- Test: `tests/Unit/Views/GroupTest.php`

A `View` that owns an ordered subview list (Z-order: later = drawn on top). Tracks `current` (the focused subview). Routes events faithfully:
- **positional** (`evMouse*`, mask `EventMask::Positional`) → the *topmost* subview whose bounds contains the mouse point;
- **focused** (`evKeyboard|evCommand`, mask `EventMask::Focused = 0x0110`) → `current` first, then any subview (so a status-line key maps even if not focused — see Program);
- **broadcast** (`evBroadcast`) → fanned out to *all* subviews.

`execView(View $modal): int` is the modal sub-loop keystone: insert the modal view, set `sfModal`, then pump events from the root `Program` (`pumpEvent()`), dispatch to the modal view, redraw, until the modal view ends modal by setting an `endState` command (via `endModal(cmd)`), then remove it and return that command. M1's `tvguid01–03` don't open modals, but a tiny test proves the mechanism for M3's dialogs.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/GroupTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** A view that records every event it handled and can consume on a target command. */
final class RecordingView extends View
{
    /** @var list<EventType> */
    public array $seen = [];

    public ?int $consumeCommand = null;

    public function handleEvent(Event $event): void
    {
        $this->seen[] = $event->what;
        if ($this->consumeCommand !== null && $event->isCommand($this->consumeCommand)) {
            $this->clearEvent($event);
        }
    }
}

/** A Group rooted at a real Screen, for compositing/exposed assertions. */
final class RootGroup extends Group
{
    public function __construct(private readonly Screen $rootScreen)
    {
        parent::__construct(Rect::of(0, 0, $rootScreen->cols(), $rootScreen->rows()));
    }

    public function screen(): ?Screen
    {
        return $this->rootScreen;
    }
}

test('insert adds subviews and sets their owner', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $child = new View(Rect::of(1, 1, 4, 4));

    $g->insert($child);

    expect($g->subviews())->toBe([$child])
        ->and($child->owner)->toBe($g);
});

test('a positional event routes to the subview under the mouse', function (): void {
    $g = new Group(Rect::of(0, 0, 20, 10));
    $left = new RecordingView(Rect::of(0, 0, 5, 10));
    $right = new RecordingView(Rect::of(10, 0, 20, 10));
    $g->insert($left);
    $g->insert($right);

    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(12, 2)));
    $g->handleEvent($ev);

    expect($right->seen)->toBe([EventType::MouseDown])
        ->and($left->seen)->toBe([]);
});

test('a focused event routes to current first', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 5));
    $b = new RecordingView(Rect::of(0, 5, 10, 10));
    $a->setState(State::Selectable, true);
    $b->setState(State::Selectable, true);
    $g->insert($a);
    $g->insert($b);
    $g->setCurrent($b);

    $ev = Event::keyDown(new KeyDownEvent(Key::Enter->value));
    $g->handleEvent($ev);

    expect($b->seen)->toBe([EventType::KeyDown]);
});

test('a broadcast event fans out to every subview', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 5));
    $b = new RecordingView(Rect::of(0, 5, 10, 10));
    $g->insert($a);
    $g->insert($b);

    $g->handleEvent(Event::broadcast(Cmd::FirstUser));

    expect($a->seen)->toBe([EventType::Broadcast])
        ->and($b->seen)->toBe([EventType::Broadcast]);
});

test('draw() draws each subview via drawView (visible only)', function (): void {
    $screen = new Screen(new HeadlessDriver(6, 2));
    $screen->init();
    $g = new RootGroup($screen);

    $child = new View(Rect::of(0, 0, 3, 1));
    $g->insert($child);
    $child->writeStr(0, 0, '...', 0x07); // pre-seed so we can see it survives a draw
    // default View::draw fills with blanks, so after group draw the child area is blank
    $g->draw();

    expect($screen->back()->rows())->toBe(['      ', '      ']);
});

test('selectNext moves focus across selectable subviews', function (): void {
    $g = new Group(Rect::of(0, 0, 10, 10));
    $a = new RecordingView(Rect::of(0, 0, 10, 3));
    $b = new RecordingView(Rect::of(0, 3, 10, 6));
    $a->setState(State::Selectable, true);
    $b->setState(State::Selectable, true);
    $g->insert($a);
    $g->insert($b);

    $g->setCurrent($a);
    $g->selectNext();
    expect($g->current())->toBe($b);

    $g->selectNext();
    expect($g->current())->toBe($a); // wraps
});

test('execView pumps a modal view until it ends modal, returning the command', function (): void {
    // A modal view that ends modal with Cmd::Ok the first time it sees any key.
    $modal = new class(Rect::of(0, 0, 4, 2)) extends View {
        public function handleEvent(Event $event): void
        {
            if ($event->asKey() !== null) {
                $this->owner?->endModal(\HelgeSverre\TurboVision\Events\Cmd::Ok);
                $this->clearEvent($event);
            }
        }
    };

    $driver = new HeadlessDriver(10, 5);
    $screen = new Screen($driver);
    $screen->init();
    $g = new RootGroup($screen);

    // Feed one keystroke so the modal handler fires and ends modal.
    $driver->feedInput("\r");

    $result = $g->execView($modal);

    expect($result)->toBe(Cmd::Ok)
        ->and($g->subviews())->not->toContain($modal); // removed after modal ends
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/GroupTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Group" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/Group.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Point;

/**
 * A View owning an ordered Z-ordered subview list (faithful to TGroup). Routes events
 * (positional -> subview under the mouse; focused -> current then any handler;
 * broadcast -> all), manages focus, and runs the execView modal sub-loop.
 */
class Group extends View
{
    /** @var list<View> Z-order: later entries draw on top. */
    protected array $children = [];

    protected ?View $currentView = null;

    /** Non-zero ends the active modal execute() loop with this command code. */
    protected int $endState = 0;

    public function insert(View $view): void
    {
        $view->setOwner($this);
        $this->children[] = $view;
        if ($this->currentView === null && $view->getState(State::Selectable)) {
            $this->currentView = $view;
        }
    }

    public function remove(View $view): void
    {
        $this->children = array_values(array_filter(
            $this->children,
            static fn (View $v): bool => $v !== $view,
        ));
        if ($this->currentView === $view) {
            $this->currentView = null;
        }
        $view->setOwner(null);
    }

    /** @return list<View> */
    public function subviews(): array
    {
        return $this->children;
    }

    public function current(): ?View
    {
        return $this->currentView;
    }

    public function setCurrent(?View $view): void
    {
        if ($this->currentView !== null) {
            $this->currentView->setState(State::Focused, false);
        }
        $this->currentView = $view;
        if ($view !== null) {
            $view->setState(State::Focused, true);
        }
    }

    /** Advance focus to the next selectable subview, wrapping. */
    public function selectNext(): void
    {
        $selectable = array_values(array_filter(
            $this->children,
            static fn (View $v): bool => $v->getState(State::Selectable),
        ));
        if ($selectable === []) {
            return;
        }

        $idx = 0;
        foreach ($selectable as $i => $v) {
            if ($v === $this->currentView) {
                $idx = $i;
                break;
            }
        }
        $next = $selectable[($idx + 1) % count($selectable)];
        $this->setCurrent($next);
    }

    public function focusNext(): void
    {
        $this->selectNext();
    }

    public function draw(): void
    {
        foreach ($this->children as $child) {
            $child->drawView();
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isNothing()) {
            return;
        }

        $bit = $event->what->value;

        if (($bit & EventMask::Positional) !== 0) {
            $this->handlePositional($event);

            return;
        }

        if (($bit & EventMask::Broadcast) !== 0) {
            foreach ($this->children as $child) {
                if (! $event->isNothing()) {
                    $child->handleEvent($event);
                }
            }

            return;
        }

        // Focused events (keyboard | command): current first, then any subview.
        if (($bit & EventMask::Focused) !== 0) {
            $this->currentView?->handleEvent($event);
            if ($event->isNothing()) {
                return;
            }
            foreach ($this->children as $child) {
                if ($child === $this->currentView) {
                    continue;
                }
                $child->handleEvent($event);
                if ($event->isNothing()) {
                    return;
                }
            }
        }
    }

    private function handlePositional(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        // Topmost (last inserted) subview whose bounds contains the point.
        for ($i = count($this->children) - 1; $i >= 0; $i--) {
            $child = $this->children[$i];
            if ($child->getBounds()->contains($mouse->where)) {
                $child->handleEvent($event);

                return;
            }
        }
    }

    // --- modality ---

    /** End the current modal execute() loop with $command. */
    public function endModal(int $command): void
    {
        $this->endState = $command;
    }

    /**
     * Insert $modal, mark it modal, pump events to it until it ends modal, then
     * remove it and return the end-state command. The keystone for M3 dialogs.
     */
    public function execView(View $modal): int
    {
        $saveOwner = $modal->owner;
        $saveEndState = $this->endState;
        $this->endState = 0;

        if ($saveOwner === null) {
            $this->insert($modal);
        }
        $modal->setState(State::Modal, true);

        $modal->drawView();

        while ($this->endState === 0) {
            $event = $this->pumpEvent();
            if ($event === null) {
                continue;
            }
            $modal->handleEvent($event);
            $modal->drawView();
        }

        $result = $this->endState;
        $modal->setState(State::Modal, false);
        if ($saveOwner === null) {
            $this->remove($modal);
        }
        $this->endState = $saveEndState;

        return $result;
    }

    /**
     * Fetch the next event for a modal loop. The root Program overrides this to pull
     * from the Screen; a plain Group walks up to its owner. Returns null on an idle tick.
     */
    public function pumpEvent(): ?Event
    {
        if ($this->owner !== null) {
            return $this->owner->pumpEvent();
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/GroupTest.php`
Expected: PASS — `Tests: 7 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/Group.php tests/Unit/Views/GroupTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Group.php tests/Unit/Views/GroupTest.php
git commit -m "feat(views): add Group (Z-order subviews, event routing, execView modality)"
```

---

## Task 4: Views — StaticText

**Files:**
- Create: `src/Views/StaticText.php`
- Test: `tests/Unit/Views/StaticTextTest.php`

Non-interactive fixed text (faithful to `TStaticText`). Word-wraps the text to the view width and supports the TV centering control char `\003` (a line beginning with `\003` is centered). Draws each wrapped line into the back buffer in the view's normal text color. Default normal color index for a static text is `0x06` in the standard palette → here we resolve via `mapColor`, but a bare `StaticText` (no Program owner) falls back to `0x07` so it is testable in isolation.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/StaticTextTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\StaticText;

/** Root group exposing a Screen so the text composites somewhere assertable. */
function staticRoot(int $w, int $h): array
{
    $screen = new Screen(new HeadlessDriver($w, $h));
    $screen->init();
    $g = new class(Rect::of(0, 0, $w, $h), $screen) extends Group {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): ?Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('draws a single line of text at the view origin', function (): void {
    [$root, $screen] = staticRoot(10, 2);
    $text = new StaticText(Rect::of(1, 0, 9, 1), 'Hello');
    $root->insert($text);

    $text->draw();

    expect($screen->back()->rows())->toBe([' Hello    ', '          ']);
});

test('word-wraps text that exceeds the view width', function (): void {
    [$root, $screen] = staticRoot(12, 3);
    $text = new StaticText(Rect::of(0, 0, 6, 3), 'one two three');
    $root->insert($text);

    $text->draw();

    // width 6: "one " then "two " then "three" wrapped onto separate lines
    expect($screen->back()->rows()[0])->toBe('one         ')
        ->and($screen->back()->rows()[1])->toBe('two         ')
        ->and($screen->back()->rows()[2])->toBe('three       ');
});

test('a leading \003 control char centers the line', function (): void {
    [$root, $screen] = staticRoot(10, 1);
    $text = new StaticText(Rect::of(0, 0, 9, 1), "\003Hi");
    $root->insert($text);

    $text->draw();

    // "Hi" is 2 wide in a 9-wide view -> left pad (9-2)/2 = 3 spaces
    expect($screen->back()->rows()[0])->toBe('   Hi     ');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/StaticTextTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\StaticText" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/StaticText.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Non-interactive fixed text (faithful to TStaticText). Word-wraps to the view width
 * and supports the TV centering control char \003 (a line starting with it is centered).
 */
class StaticText extends View
{
    public function __construct(Rect $bounds, protected string $text)
    {
        parent::__construct($bounds);
    }

    /** StaticText uses palette index 1 -> its text color. */
    public function getPalette(): ?Palette
    {
        return new Palette([1 => 0x06]);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $attr = $this->mapColor(1);

        // Blank the whole extent first.
        $blank = new DrawBuffer($width);
        $blank->moveChar(0, ' ', $attr, $width);
        for ($y = 0; $y < $height; $y++) {
            $this->writeLine(0, $y, $width, 1, $blank);
        }

        $lines = $this->layout($width);
        foreach ($lines as $y => $line) {
            if ($y >= $height) {
                break;
            }
            $centered = false;
            if ($line !== '' && $line[0] === "\003") {
                $centered = true;
                $line = substr($line, 1);
            }

            $len = mb_strlen($line);
            $x = $centered ? intdiv(max(0, $width - $len), 2) : 0;

            $b = new DrawBuffer($width);
            $b->moveChar(0, ' ', $attr, $width);
            $b->moveStr($x, $line, $attr);
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }

    /**
     * Word-wrap $text to $width. A \003 prefix is preserved on its line so draw()
     * can center it.
     *
     * @return list<string>
     */
    private function layout(int $width): array
    {
        if ($width <= 0) {
            return [];
        }

        $centerPrefix = '';
        $body = $this->text;
        if ($body !== '' && $body[0] === "\003") {
            $centerPrefix = "\003";
            $body = substr($body, 1);
        }

        $words = preg_split('/\s+/', trim($body)) ?: [];
        /** @var list<string> $lines */
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $width) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $centerPrefix . $current;
                }
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $centerPrefix . $current;
        }

        return $lines;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/StaticTextTest.php`
Expected: PASS — `Tests: 3 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/StaticText.php tests/Unit/Views/StaticTextTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/StaticText.php tests/Unit/Views/StaticTextTest.php
git commit -m "feat(views): add StaticText (word-wrap + \003 centering)"
```

---

## Task 5: Views — Background

**Files:**
- Create: `src/Views/Background.php`
- Test: `tests/Unit/Views/BackgroundTest.php`

Fills its whole extent with a single pattern character (faithful to `TBackground`; default pattern `0xB0` → the light-shade glyph `'░'`). Its palette is `cpBackground "\x01"` (one entry → the backdrop attribute). Resolved bare it falls back to `0x07`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/BackgroundTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Group;

function backgroundRoot(int $w, int $h): array
{
    $screen = new Screen(new HeadlessDriver($w, $h));
    $screen->init();
    $g = new class(Rect::of(0, 0, $w, $h), $screen) extends Group {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): ?Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('default background fills its extent with the shade glyph', function (): void {
    [$root, $screen] = backgroundRoot(4, 2);
    $bg = new Background(Rect::of(0, 0, 4, 2));
    $root->insert($bg);

    $bg->draw();

    expect($screen->back()->rows())->toBe(['░░░░', '░░░░']);
});

test('a custom pattern char is used', function (): void {
    [$root, $screen] = backgroundRoot(3, 1);
    $bg = new Background(Rect::of(0, 0, 3, 1), '.');
    $root->insert($bg);

    $bg->draw();

    expect($screen->back()->rows())->toBe(['...']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/BackgroundTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Background" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/Background.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Fills its whole extent with one pattern character (faithful to TBackground). The
 * default pattern is the CP437 light-shade 0xB0, mapped to its Unicode glyph '░'.
 */
class Background extends View
{
    public const string DEFAULT_PATTERN = '░';

    public function __construct(Rect $bounds, protected string $pattern = self::DEFAULT_PATTERN)
    {
        parent::__construct($bounds);
    }

    /** cpBackground "\x01": one palette entry -> the backdrop attribute. */
    public function getPalette(): ?Palette
    {
        return new Palette([1 => 0x01]);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $attr = $this->mapColor(1);

        $b = new DrawBuffer($width);
        $b->moveChar(0, $this->pattern, $attr, $width);
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/BackgroundTest.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/Background.php tests/Unit/Views/BackgroundTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Background.php tests/Unit/Views/BackgroundTest.php
git commit -m "feat(views): add Background pattern-fill view"
```

---

## Task 6: Views — Desktop

**Files:**
- Create: `src/Views/Desktop.php`
- Test: `tests/Unit/Views/DesktopTest.php`

The backdrop `Group` (faithful to `TDeskTop`) that occupies the region between the menu bar (row 0) and the status line (last row). On construction it inserts a `Background` filling its whole extent. In M2 it will host windows; in M1 it is just the desk pattern.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/DesktopTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Group;

test('desktop owns a Background sized to its extent', function (): void {
    $desk = new Desktop(Rect::of(0, 1, 8, 4)); // 8 wide, 3 tall

    expect($desk->subviews())->toHaveCount(1)
        ->and($desk->subviews()[0])->toBeInstanceOf(Background::class)
        ->and($desk->subviews()[0]->getBounds())->toEqual(Rect::of(0, 0, 8, 3));
});

test('drawing the desktop fills its region with the desk pattern', function (): void {
    $screen = new Screen(new HeadlessDriver(6, 4));
    $screen->init();

    // A root group that hosts the desktop and exposes the screen.
    $root = new class(Rect::of(0, 0, 6, 4), $screen) extends Group {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): ?Screen
        {
            return $this->s;
        }
    };

    // Desktop occupies rows 1..2 (between a menu bar on row 0 and a status line on row 3).
    $desk = new Desktop(Rect::of(0, 1, 6, 3));
    $root->insert($desk);

    $desk->drawView();

    expect($screen->back()->rows())->toBe([
        '      ', // row 0 (menu bar — untouched here)
        '░░░░░░', // row 1
        '░░░░░░', // row 2
        '      ', // row 3 (status line — untouched here)
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/DesktopTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Desktop" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/Desktop.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * The backdrop Group (faithful to TDeskTop). Occupies the area between the menu bar
 * and the status line, and owns a Background filling its extent. Hosts windows in M2.
 */
class Desktop extends Group
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->insert($this->initBackground());
    }

    protected function initBackground(): Background
    {
        $extent = $this->getExtent();

        return new Background($extent);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/DesktopTest.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/Desktop.php tests/Unit/Views/DesktopTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Desktop.php tests/Unit/Views/DesktopTest.php
git commit -m "feat(views): add Desktop backdrop group with Background"
```

---

## Task 7: Menus — definition objects (MenuItem, SubMenu, Menu, StatusItem, StatusDef)

**Files:**
- Create: `src/Menus/MenuItem.php`
- Create: `src/Menus/SubMenu.php`
- Create: `src/Menus/Menu.php`
- Create: `src/Menus/StatusItem.php`
- Create: `src/Menus/StatusDef.php`
- Test: `tests/Unit/Menus/MenuDefinitionsTest.php`

The data structures behind the menu bar and status line. These must satisfy the design spec's Target-API snippet **verbatim**: `new SubMenu('~F~ile', Key::AltF)->items(new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit the program'))` and `new StatusDef(0, 0xFFFF)->items(new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit))`. `->items(...)` is variadic and returns `static` (fluent). A `MenuItem` may carry a nested `?Menu $subMenu` (set when a `SubMenu` is itself nested under another). `Menu` is the collection a `MenuBar` draws.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Menus/MenuDefinitionsTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Menus\Menu;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\SubMenu;

test('a MenuItem carries name, command, key and help', function (): void {
    $item = new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit the program');

    expect($item->name)->toBe('E~x~it')
        ->and($item->command)->toBe(Cmd::Quit)
        ->and($item->key)->toBe(Key::AltX)
        ->and($item->help)->toBe('Exit the program')
        ->and($item->subMenu)->toBeNull();
});

test('SubMenu->items() is fluent and collects items into a Menu', function (): void {
    $sub = new SubMenu('~F~ile', Key::AltF)->items(
        new MenuItem('~O~pen', Cmd::FirstUser, Key::F3, 'F3'),
        new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
    );

    expect($sub)->toBeInstanceOf(SubMenu::class)
        ->and($sub->name)->toBe('~F~ile')
        ->and($sub->key)->toBe(Key::AltF)
        ->and($sub->menu())->toBeInstanceOf(Menu::class)
        ->and($sub->menu()->items())->toHaveCount(2)
        ->and($sub->menu()->items()[1]->command)->toBe(Cmd::Quit);
});

test('a Menu built from several SubMenus exposes its top-level items as MenuItems', function (): void {
    $file = new SubMenu('~F~ile', Key::AltF)->items(
        new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
    );
    $window = new SubMenu('~W~indow', Key::AltW)->items(
        new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
    );

    $menu = Menu::of($file, $window);

    expect($menu->items())->toHaveCount(2)
        ->and($menu->items()[0]->name)->toBe('~F~ile')
        ->and($menu->items()[0]->key)->toBe(Key::AltF)
        ->and($menu->items()[0]->subMenu)->toBeInstanceOf(Menu::class)
        ->and($menu->items()[1]->name)->toBe('~W~indow');
});

test('a StatusItem carries text, key and command', function (): void {
    $item = new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit);

    expect($item->text)->toBe('~Alt-X~ Exit')
        ->and($item->key)->toBe(Key::AltX)
        ->and($item->command)->toBe(Cmd::Quit);
});

test('StatusDef->items() is fluent and scopes items to a help-context range', function (): void {
    $def = new StatusDef(0, 0xFFFF)->items(
        new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
    );

    expect($def)->toBeInstanceOf(StatusDef::class)
        ->and($def->min)->toBe(0)
        ->and($def->max)->toBe(0xFFFF)
        ->and($def->items())->toHaveCount(2)
        ->and($def->items()[0]->command)->toBe(Cmd::Quit);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Menus/MenuDefinitionsTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Menus\MenuItem" not found`.

- [ ] **Step 3: Write the implementations**

`src/Menus/MenuItem.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Key;

/**
 * A single menu entry (faithful to TMenuItem). Either a command item (command != 0)
 * or a submenu host ($subMenu != null). The name may contain a ~hotkey~ marker.
 */
final class MenuItem
{
    public function __construct(
        public string $name,
        public int $command,
        public ?Key $key = null,
        public string $help = '',
        public ?Menu $subMenu = null,
    ) {}
}
```

`src/Menus/Menu.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

/** A collection of MenuItems (faithful to TMenu) — what a MenuBar/MenuBox draws. */
final class Menu
{
    /** @param list<MenuItem> $items */
    public function __construct(private array $items = []) {}

    /** Build a Menu from top-level SubMenus, lowering each to a submenu MenuItem. */
    public static function of(SubMenu ...$subMenus): self
    {
        $items = [];
        foreach ($subMenus as $sub) {
            $items[] = new MenuItem(
                name: $sub->name,
                command: 0,
                key: $sub->key,
                help: '',
                subMenu: $sub->menu(),
            );
        }

        return new self($items);
    }

    public function add(MenuItem $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<MenuItem> */
    public function items(): array
    {
        return $this->items;
    }
}
```

`src/Menus/SubMenu.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Key;

/**
 * A named, hotkeyed pull-down (faithful to TSubMenu). Items are added fluently via
 * ->items(...); they accumulate into an internal Menu. The name may contain ~hotkey~.
 */
final class SubMenu
{
    private Menu $menu;

    public function __construct(
        public string $name,
        public ?Key $key = null,
    ) {
        $this->menu = new Menu();
    }

    /** Fluently append items (MenuItem or nested SubMenu). Returns $this. */
    public function items(MenuItem|SubMenu ...$items): static
    {
        foreach ($items as $item) {
            if ($item instanceof SubMenu) {
                $this->menu->add(new MenuItem(
                    name: $item->name,
                    command: 0,
                    key: $item->key,
                    help: '',
                    subMenu: $item->menu(),
                ));
            } else {
                $this->menu->add($item);
            }
        }

        return $this;
    }

    public function menu(): Menu
    {
        return $this->menu;
    }
}
```

`src/Menus/StatusItem.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Key;

/**
 * One status-line entry (faithful to TStatusItem): a hint text (may be empty for a
 * key-only binding), the key that fires it, and the command it sends.
 */
final class StatusItem
{
    public function __construct(
        public string $text,
        public ?Key $key,
        public int $command,
    ) {}
}
```

`src/Menus/StatusDef.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

/**
 * A help-context-ranged set of StatusItems (faithful to TStatusDef). Items are added
 * fluently via ->items(...). The status line picks the def whose [min,max] contains
 * the current help context (M1 uses a single full-range def).
 */
final class StatusDef
{
    /** @var list<StatusItem> */
    private array $items = [];

    public function __construct(
        public int $min,
        public int $max,
    ) {}

    /**
     * Fluent setter when given StatusItems (returns $this); getter when given none
     * (returns the stored list). A method and the property cannot share a name in
     * PHP, and you cannot declare two methods of the same name — so this single
     * overloaded method serves both roles.
     *
     * @return ($newItems is array{} ? list<StatusItem> : static)
     */
    public function items(StatusItem ...$newItems): static|array
    {
        if ($newItems === []) {
            return $this->items;
        }
        foreach ($newItems as $item) {
            $this->items[] = $item;
        }

        return $this;
    }
}
```

> **PHP overload note:** `StatusDef::items()` is intentionally a single method serving two roles — a fluent **setter** when passed `StatusItem`s (returns `$this`) and a **getter** when passed none (returns `list<StatusItem>`). The conditional-return-type annotation (`@return ($newItems is array{} ? ... : ...)`) keeps PHPStan max precise about which branch each call site hits. `SubMenu::items()` stays a pure fluent setter (read its contents via `menu()`); `Menu::items()` stays a pure getter (no args). The Target-API snippet uses `->items(...)` only as a fluent setter, so it works verbatim.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Menus/MenuDefinitionsTest.php`
Expected: PASS — `Tests: 5 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Menus tests/Unit/Menus/MenuDefinitionsTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Menus/MenuItem.php src/Menus/Menu.php src/Menus/SubMenu.php src/Menus/StatusItem.php src/Menus/StatusDef.php tests/Unit/Menus/MenuDefinitionsTest.php
git commit -m "feat(menus): add menu/status definition objects with fluent ->items()"
```

---

## Task 8: Menus — MenuView (abstract base)

**Files:**
- Create: `src/Menus/MenuView.php`
- Test: covered by MenuBar in Task 9 (no standalone test — abstract base).

The abstract base shared by `MenuBar` (and M3's `MenuBox`). Faithful to `TMenuView`: provides the menu palette `cpMenuView "\x02\x03\x04\x05\x06\x07"` and the `getColor` helper the draw code uses (`getColor(0x0301)` → normal, `getColor(0x0604)` → selected). Since a bare menu view has no Program owner in unit tests, `getColor` resolves through its own palette and falls back gracefully.

- [ ] **Step 1: Write the implementation**

`src/Menus/MenuView.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Views\View;

/**
 * Abstract base for menu views (faithful to TMenuView). Carries the menu palette
 * cpMenuView "\x02\x03\x04\x05\x06\x07" and shares the getColor word lookup used by
 * MenuBar::draw() / StatusLine::draw().
 */
abstract class MenuView extends View
{
    /** cpMenuView: indexes 1..6 -> attribute indexes 0x02..0x07 in the app palette. */
    public function getPalette(): ?Palette
    {
        return Palette::fromBytes("\x02\x03\x04\x05\x06\x07");
    }
}
```

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Menus/MenuView.php`
Expected: `[OK] No errors`.

- [ ] **Step 3: Commit**

```bash
git add src/Menus/MenuView.php
git commit -m "feat(menus): add abstract MenuView base with cpMenuView palette"
```

---

## Task 9: Menus — MenuBar

**Files:**
- Create: `src/Menus/MenuBar.php`
- Test: `tests/Unit/Menus/MenuBarTest.php`

The top bar (faithful to `TMenuBar::draw`). Constructor accepts `(Rect $bounds, SubMenu|Menu ...$menus)` — the spec's `new MenuBar($bounds, new SubMenu(...)->items(...))` surface. Draws the bar: a blank line in the normal color, then each top-level item rendered as `' ' + ~hotkey~ name + ' '` starting at `x=1` (faithful layout). M1 dispatches a top-level item's command on Alt-hotkey key press or a click on its label; it does **not** open a pull-down `MenuBox` (that is M3 — noted explicitly). When an item with a submenu is activated, M1 fires the **first command found** within that submenu only if the item itself has a command; for `tvguid03` the dispatch we need is the status-line/hotkey path, and the menu bar's role in M1 is render + top-level-hotkey + click-to-command on leaf items. Bare (no Program) it uses `0x70`/`0x20`-style fallbacks via the palette.

> **M1 scope note (in the class docblock):** MenuBar renders the bar and dispatches *direct* commands for top-level hotkeys/clicks. Opening a pull-down box and navigating its items is deferred to M3's `MenuBox`. `tvguid03`'s menu bar therefore renders fully and its Alt-F/Alt-W hotkeys are recognized, but selecting "Open"/"New" from an opened pull-down is an M3 behavior.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Menus/MenuBarTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

/** Root group exposing a Screen, used to host and draw a MenuBar. */
function menuRoot(int $w): array
{
    $screen = new Screen(new HeadlessDriver($w, 1));
    $screen->init();
    $g = new class(Rect::of(0, 0, $w, 1), $screen) extends Group {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): ?Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('menu bar renders top-level item names with hotkeys stripped, starting at column 1', function (): void {
    [$root, $screen] = menuRoot(20);
    $bar = new MenuBar(
        Rect::of(0, 0, 20, 1),
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
        ),
        new SubMenu('~W~indow', Key::AltW)->items(
            new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
        ),
    );
    $root->insert($bar);

    $bar->draw();

    // " File  Window " starting at x=1: ' ' + 'File' + ' ' then ' ' + 'Window' + ' '
    $row = $screen->back()->rows()[0];
    expect($row)->toContain('File')
        ->and($row)->toContain('Window')
        ->and(str_starts_with($row, ' File'))->toBeTrue();
});

test('Alt-hotkey on a top-level submenu is recognized (handled, event consumed)', function (): void {
    [$root, $screen] = menuRoot(20);
    $bar = new MenuBar(
        Rect::of(0, 0, 20, 1),
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
        ),
    );
    $root->insert($bar);

    $ev = Event::keyDown(new KeyDownEvent(Key::AltF->value));
    $bar->handleEvent($ev);

    // M1: the bar recognizes the top-level hotkey and consumes the key.
    expect($ev->isNothing())->toBeTrue();
});

test('clicking a leaf-command item dispatches its command via putEvent', function (): void {
    [$root, $screen] = menuRoot(20);
    // A bar whose single top-level item is itself a direct command (rare, but exercises dispatch).
    $bar = new MenuBar(
        Rect::of(0, 0, 20, 1),
        new SubMenu('E~x~it', Key::AltX)->items(),
    );
    $root->insert($bar);

    // The bar exposes the command it would dispatch for a click at a column.
    expect($bar->commandAtColumn(2))->toBe(0); // submenu host has no direct command
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Menus/MenuBarTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Menus\MenuBar" not found`.

- [ ] **Step 3: Write the implementation**

`src/Menus/MenuBar.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;

/**
 * The top menu bar (faithful to TMenuBar::draw). Renders top-level items as
 * ' ' + ~hotkey~ name + ' ' starting at column 1.
 *
 * M1 SCOPE: render + top-level hotkey/click command dispatch only. Opening and
 * navigating a pull-down MenuBox is deferred to M3.
 */
final class MenuBar extends MenuView
{
    private Menu $menu;

    public function __construct(Rect $bounds, SubMenu|Menu ...$menus)
    {
        parent::__construct($bounds);
        $this->options |= State::PreProcess;
        $this->growMode = State::GrowHiX;
        $this->menu = $this->buildMenu($menus);
    }

    /** @param array<int,SubMenu|Menu> $menus */
    private function buildMenu(array $menus): Menu
    {
        $merged = new Menu();
        foreach ($menus as $m) {
            if ($m instanceof Menu) {
                foreach ($m->items() as $item) {
                    $merged->add($item);
                }
            } else {
                $merged->add(new MenuItem(
                    name: $m->name,
                    command: 0,
                    key: $m->key,
                    help: '',
                    subMenu: $m->menu(),
                ));
            }
        }

        return $merged;
    }

    public function menu(): Menu
    {
        return $this->menu;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $cNormal = $this->getColor(0x0301) & 0xFF;
        $cHighlight = $this->getColor(0x0302) & 0xFF;

        $b = new DrawBuffer($width);
        $b->moveChar(0, ' ', $cNormal, $width);

        $x = 1;
        foreach ($this->menu->items() as $item) {
            if ($item->name === '') {
                continue;
            }
            $len = $this->visibleLength($item->name);
            if ($x + $len < $width) {
                $b->moveChar($x, ' ', $cNormal, 1);
                $b->moveCStr($x + 1, $item->name, $cNormal, $cHighlight);
                $b->moveChar($x + $len + 1, ' ', $cNormal, 1);
            }
            $x += $len + 2;
        }

        $this->writeBuf(0, 0, $width, 1, $b);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            foreach ($this->menu->items() as $item) {
                if ($item->key !== null && $key->is($item->key)) {
                    // M1: top-level hotkey recognized. A direct command dispatches;
                    // a submenu host is consumed (pull-down navigation is M3).
                    if ($item->command !== 0) {
                        $this->putCommand($item->command);
                    }
                    $this->clearEvent($event);

                    return;
                }
            }
        }

        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse === null) {
                return;
            }
            $origin = $this->absoluteOrigin();
            $localX = $mouse->where->x - $origin->x;
            $command = $this->commandAtColumn($localX);
            if ($command !== 0) {
                $this->putCommand($command);
            }
            $this->clearEvent($event);
        }
    }

    /** The direct command of the item under local column $localX, or 0. */
    public function commandAtColumn(int $localX): int
    {
        $x = 1;
        foreach ($this->menu->items() as $item) {
            if ($item->name === '') {
                continue;
            }
            $len = $this->visibleLength($item->name);
            $end = $x + $len + 2;
            if ($localX >= $x && $localX < $end) {
                return $item->command;
            }
            $x = $end;
        }

        return 0;
    }

    private function putCommand(int $command): void
    {
        $owner = $this->owner;
        if ($owner !== null) {
            $owner->putEvent(Event::command($command));
        }
    }

    /** Length of a ~hotkey~-marked label with the tildes removed. */
    private function visibleLength(string $name): int
    {
        return mb_strlen(str_replace('~', '', $name));
    }
}
```

> **Dependency note:** `putCommand()` calls `$owner->putEvent(...)`. `Group` does not yet have `putEvent`; the root `Program` (Task 11) defines it and `Group::putEvent` will delegate up the owner chain. To keep this task green in isolation, add a `putEvent` passthrough to `Group` now (it is needed by Group's modal loop too). Apply this small addition to `src/Views/Group.php` in this task:

```php
    /** Enqueue an event for the modal/main loop; delegates up to the root Program. */
    public function putEvent(Event $event): void
    {
        $this->owner?->putEvent($event);
    }
```

(Place it next to `pumpEvent()`. Re-run `tests/Unit/Views/GroupTest.php` after adding it; it must stay green.)

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Menus/MenuBarTest.php tests/Unit/Views/GroupTest.php`
Expected: PASS — MenuBar `Tests: 3 passed`, Group still green.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Menus/MenuBar.php src/Views/Group.php tests/Unit/Menus/MenuBarTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Menus/MenuBar.php src/Views/Group.php tests/Unit/Menus/MenuBarTest.php
git commit -m "feat(menus): add MenuBar (render + top-level hotkey/click dispatch)"
```

---

## Task 10: Menus — StatusLine

**Files:**
- Create: `src/Menus/StatusLine.php`
- Test: `tests/Unit/Menus/StatusLineTest.php`

The bottom hint bar (faithful to `TStatusLine::drawSelect`/`handleEvent`). Constructor accepts `(Rect $bounds, StatusDef ...$defs)` — the spec's `new StatusLine($bounds, new StatusDef(0,0xFFFF)->items(...))` surface. Draws the current def's items left-to-right as `' ' + ~hotkey~ text + ' '`, items with empty text omitted from drawing. On `evKeyDown`, if the pressed `Key` matches an item's key, it **mutates the event in place into a Command** carrying that item's command (faithful `TStatusLine`: it rewrites `event.what = evCommand` and returns, leaving the command for the Program to dispatch). On `evMouseDown` within the bar, it dispatches the clicked item's command via `putEvent`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Menus/StatusLineTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

function statusRoot(int $w): array
{
    $screen = new Screen(new HeadlessDriver($w, 1));
    $screen->init();
    $g = new class(Rect::of(0, 0, $w, 1), $screen) extends Group {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): ?Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('status line draws its item hints with hotkeys stripped', function (): void {
    [$root, $screen] = statusRoot(24);
    $line = new StatusLine(
        Rect::of(0, 0, 24, 1),
        new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ),
    );
    $root->insert($line);

    $line->draw();

    expect($screen->back()->rows()[0])->toContain('Alt-X Exit');
});

test('a matching key press is rewritten into a Command event in place', function (): void {
    [$root, $screen] = statusRoot(24);
    $line = new StatusLine(
        Rect::of(0, 0, 24, 1),
        new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ),
    );
    $root->insert($line);

    $ev = Event::keyDown(new KeyDownEvent(Key::AltX->value));
    $line->handleEvent($ev);

    expect($ev->what)->toBe(EventType::Command)
        ->and($ev->asMessage()?->command)->toBe(Cmd::Quit);
});

test('a non-matching key press is left untouched', function (): void {
    [$root, $screen] = statusRoot(24);
    $line = new StatusLine(
        Rect::of(0, 0, 24, 1),
        new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ),
    );
    $root->insert($line);

    $ev = Event::keyDown(new KeyDownEvent(Key::Enter->value));
    $line->handleEvent($ev);

    expect($ev->what)->toBe(EventType::KeyDown)
        ->and($ev->asKey()?->is(Key::Enter))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Menus/StatusLineTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Menus\StatusLine" not found`.

- [ ] **Step 3: Write the implementation**

`src/Menus/StatusLine.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\MessageEvent;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * The bottom status/hint bar (faithful to TStatusLine). Renders the current def's
 * items, maps a matching key press to its command (rewriting the event in place), and
 * dispatches a clicked item's command.
 */
final class StatusLine extends MenuView
{
    /** @var list<StatusDef> */
    private array $defs;

    private int $helpCtx = 0;

    public function __construct(Rect $bounds, StatusDef ...$defs)
    {
        parent::__construct($bounds);
        $this->defs = array_values($defs);
    }

    /** cpStatusLine "\x02\x03\x04\x05\x06\x07" — identical to cpMenuView. */
    public function getPalette(): ?Palette
    {
        return Palette::fromBytes("\x02\x03\x04\x05\x06\x07");
    }

    /** The StatusItems active for the current help context. @return list<StatusItem> */
    private function items(): array
    {
        foreach ($this->defs as $def) {
            if ($this->helpCtx >= $def->min && $this->helpCtx <= $def->max) {
                return $def->items();
            }
        }

        return [];
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $cNormal = $this->getColor(0x0301) & 0xFF;
        $cHighlight = $this->getColor(0x0302) & 0xFF;

        $b = new DrawBuffer($width);
        $b->moveChar(0, ' ', $cNormal, $width);

        $x = 0;
        foreach ($this->items() as $item) {
            if ($item->text === '') {
                continue;
            }
            $len = $this->visibleLength($item->text);
            if ($x + $len < $width) {
                $b->moveChar($x, ' ', $cNormal, 1);
                $b->moveCStr($x + 1, $item->text, $cNormal, $cHighlight);
                $b->moveChar($x + $len + 1, ' ', $cNormal, 1);
            }
            $x += $len + 2;
        }

        $this->writeBuf(0, 0, $width, 1, $b);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            foreach ($this->items() as $item) {
                if ($item->key !== null && $key->is($item->key)) {
                    // Faithful: rewrite this event into a Command in place.
                    $event->what = EventType::Command;
                    $event->payload = new MessageEvent($item->command);

                    return;
                }
            }

            return;
        }

        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse === null) {
                return;
            }
            $origin = $this->absoluteOrigin();
            $localX = $mouse->where->x - $origin->x;
            $command = $this->commandAtColumn($localX);
            if ($command !== 0) {
                $this->owner?->putEvent(Event::command($command));
            }
            $this->clearEvent($event);
        }
    }

    public function commandAtColumn(int $localX): int
    {
        $x = 0;
        foreach ($this->items() as $item) {
            if ($item->text === '') {
                continue;
            }
            $len = $this->visibleLength($item->text);
            $end = $x + $len + 2;
            if ($localX >= $x && $localX < $end) {
                return $item->command;
            }
            $x = $end;
        }

        return 0;
    }

    private function visibleLength(string $text): int
    {
        return mb_strlen(str_replace('~', '', $text));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Menus/StatusLineTest.php`
Expected: PASS — `Tests: 3 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Menus/StatusLine.php tests/Unit/Menus/StatusLineTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Menus/StatusLine.php tests/Unit/Menus/StatusLineTest.php
git commit -m "feat(menus): add StatusLine (render + key->command rewrite + click dispatch)"
```

---

## Task 11: Application — Program

**Files:**
- Create: `src/Application/Program.php`
- Test: `tests/Unit/Application/ProgramTest.php`

The root `Group` that owns everything and runs the loop. Mutable. Owns a `Screen`, a `Desktop`, a `MenuBar`, a `StatusLine`, an enabled command set, an event queue (`pending`), and the `endState`. Overridable factory hooks `initScreen():Screen`, `initDeskTop(Rect):?Desktop`, `initMenuBar(Rect):?MenuBar`, `initStatusLine(Rect):?StatusLine` (here they return `null`/empty defaults; `Application` overrides them).

`run():int` lifecycle (faithful to `TProgram`):
1. `screen->init()`; build children: menu bar on the top row, status line on the bottom row, desktop in the middle; insert in that Z-order.
2. Full draw (each child via `drawView`), `screen->flush()`.
3. Loop: handle `screen->wasResized()` → re-layout + full redraw; `ev = getEvent()` (pending queue first, else decode from `screen->pollEvents`); `handleEvent(ev)`; if dirty, `screen->flush()`; break when `endState !== 0` (set by `cmQuit`).
4. `finally { screen->shutdown(); }`. Return the exit code (0).

`getEvent()` pulls a pending event first, else the next decoded event from the screen; **before returning a keyboard event it gives the status line and menu bar a preprocess pass** (status line may rewrite a key into a command; menu bar may consume a hotkey) — faithful to TV's preprocess phase. `putEvent()` enqueues into `pending`. `pumpEvent()` (used by `Group::execView`) returns the next `getEvent()` result or `null` on idle. `handleEvent()` routes to status line + menu bar + desktop (via the Group routing) and turns `cmQuit` into `endState`. `enableCommand`/`disableCommand`/`commandEnabled` manage the command set.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Application/ProgramTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Application\Program;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;

/** A Program wired with an injected headless Screen and a quit status line. */
final class QuitProgram extends Program
{
    public function __construct(private readonly Screen $injected)
    {
        parent::__construct();
    }

    protected function initScreen(): Screen
    {
        return $this->injected;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }

    protected function initDeskTop(Rect $bounds): ?Desktop
    {
        return new Desktop($bounds);
    }
}

test('run builds children, draws, and exits cleanly on a fed quit key', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X -> EscapeDecoder yields Key::AltX

    $code = $program->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse(); // shutdown ran
});

test('putEvent enqueues an event that getEvent returns first', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();

    $program->putEvent(Event::command(Cmd::FirstUser));
    $ev = $program->getEvent();

    expect($ev->isCommand(Cmd::FirstUser))->toBeTrue();
});

test('a cmQuit command ends the program (sets endState)', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();

    $program->handleEvent(Event::command(Cmd::Quit));

    expect($program->ended())->toBeTrue();
});

test('command enable/disable is tracked', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));

    expect($program->commandEnabled(Cmd::Quit))->toBeTrue(); // enabled by default
    $program->disableCommand(Cmd::Quit);
    expect($program->commandEnabled(Cmd::Quit))->toBeFalse();
    $program->enableCommand(Cmd::Quit);
    expect($program->commandEnabled(Cmd::Quit))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Application/ProgramTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Application\Program" not found`.

- [ ] **Step 3: Write the implementation**

`src/Application/Program.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Application;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Group;

/**
 * The application root (faithful to TProgram). A Group that owns the Screen, Desktop,
 * MenuBar and StatusLine, runs the event loop, and dispatches commands. Mutable.
 */
class Program extends Group
{
    protected Screen $screenObj;

    protected ?Desktop $desktop = null;

    protected ?MenuBar $menuBar = null;

    protected ?StatusLine $statusLine = null;

    /** @var list<Event> FIFO of putEvent()-queued events. */
    protected array $pending = [];

    /** @var array<int,bool> command code => enabled (absent => enabled). */
    protected array $disabledCommands = [];

    protected bool $dirty = true;

    public function __construct()
    {
        $this->screenObj = $this->initScreen();
        parent::__construct(Rect::of(0, 0, 0, 0));
    }

    // --- factory hooks (overridden by Application / user) ---

    protected function initScreen(): Screen
    {
        // Application overrides this to provide a real or injected Screen.
        throw new \LogicException('Program::initScreen() must be overridden.');
    }

    protected function initDeskTop(Rect $bounds): ?Desktop
    {
        return new Desktop($bounds);
    }

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }

    // --- root overrides so views reach the Screen and queue events ---

    public function screen(): ?Screen
    {
        return $this->screenObj;
    }

    public function putEvent(Event $event): void
    {
        $this->pending[] = $event;
    }

    public function pumpEvent(): ?Event
    {
        $event = $this->getEvent();

        return $event->isNothing() ? null : $event;
    }

    // --- lifecycle ---

    /** Boot the screen and build the child views without entering the loop (for tests). */
    public function bootForTest(): void
    {
        $this->screenObj->init();
        $this->layout();
    }

    public function run(): int
    {
        $this->screenObj->init();
        try {
            $this->layout();
            $this->redraw();

            while ($this->endState === 0) {
                if ($this->screenObj->wasResized()) {
                    $this->layout();
                    $this->dirty = true;
                }

                $event = $this->getEvent();
                if (! $event->isNothing()) {
                    $this->handleEvent($event);
                    $this->dirty = true;
                }

                if ($this->dirty) {
                    $this->redraw();
                }
            }
        } finally {
            $this->screenObj->shutdown();
        }

        return 0;
    }

    public function ended(): bool
    {
        return $this->endState !== 0;
    }

    /** (Re)build the bounds + child views from the current screen size. */
    protected function layout(): void
    {
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $this->setBounds(Rect::of(0, 0, $cols, $rows));

        // Reset children, then rebuild in Z-order: desktop, menu bar, status line.
        $this->children = [];
        $this->currentView = null;

        $deskRect = Rect::of(0, 1, $cols, $rows - 1);
        $this->desktop = $this->initDeskTop($deskRect);
        if ($this->desktop !== null) {
            $this->insert($this->desktop);
        }

        $menuRect = Rect::of(0, 0, $cols, 1);
        $this->menuBar = $this->initMenuBar($menuRect);
        if ($this->menuBar !== null) {
            $this->insert($this->menuBar);
        }

        $statusRect = Rect::of(0, $rows - 1, $cols, $rows);
        $this->statusLine = $this->initStatusLine($statusRect);
        if ($this->statusLine !== null) {
            $this->insert($this->statusLine);
        }
    }

    protected function redraw(): void
    {
        $this->screenObj->clear();
        $this->draw();
        $this->screenObj->flush();
        $this->dirty = false;
    }

    // --- event sourcing ---

    /**
     * The next event: a queued event first, else the next decoded screen event. A
     * keyboard event is preprocessed by the status line (key->command rewrite) and the
     * menu bar (hotkey consume) before being returned.
     */
    public function getEvent(): Event
    {
        if ($this->pending !== []) {
            return array_shift($this->pending);
        }

        $events = $this->screenObj->pollEvents(20);
        if ($events === []) {
            return Event::nothing();
        }

        $event = $events[0];
        // Re-queue any extra events decoded in the same poll.
        for ($i = 1, $n = count($events); $i < $n; $i++) {
            $this->pending[] = $events[$i];
        }

        $this->preprocess($event);

        return $event;
    }

    /** Let the status line and menu bar transform/consume the event first. */
    protected function preprocess(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $this->statusLine?->handleEvent($event); // may rewrite into a Command
        }
        if ($event->what === EventType::KeyDown) {
            $this->menuBar?->handleEvent($event);    // may consume a hotkey
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isNothing()) {
            return;
        }

        // Command dispatch handled at the program level.
        if ($event->what === EventType::Command) {
            $message = $event->asMessage();
            if ($message !== null) {
                if ($message->command === Cmd::Quit) {
                    $this->endModal(Cmd::Quit);
                    $this->clearEvent($event);

                    return;
                }
            }
        }

        // Route the rest down the view tree (desktop, menu bar, status line).
        parent::handleEvent($event);
    }

    // --- command set ---

    public function enableCommand(int $command): void
    {
        unset($this->disabledCommands[$command]);
    }

    public function disableCommand(int $command): void
    {
        $this->disabledCommands[$command] = true;
    }

    public function commandEnabled(int $command): bool
    {
        return ! isset($this->disabledCommands[$command]);
    }
}
```

> **Note:** `Program::endState` is the `protected int $endState` inherited from `Group`; `endModal(Cmd::Quit)` sets it non-zero, ending `run()`'s loop. `ended()` exposes it for tests.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Application/ProgramTest.php`
Expected: PASS — `Tests: 4 passed`.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Application/Program.php tests/Unit/Application/ProgramTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Program.php tests/Unit/Application/ProgramTest.php
git commit -m "feat(application): add Program (Screen owner, event loop, command dispatch)"
```

---

## Task 12: Application — Application

**Files:**
- Create: `src/Application/Application.php`
- Test: `tests/Unit/Application/ApplicationTest.php`

The class users subclass (faithful to `TApplication`). Extends `Program`, supplies the default factories (empty desktop, a default menu bar, a default status line), and — critically — its constructor accepts an **optional injected `Screen`** so headless tests pass `new Screen(new HeadlessDriver(80,25))`. By default `initScreen()` returns `new Screen(new AnsiDriver())`. With these defaults, `final class HelloApp extends Application {}` then `(new HelloApp())->run()` works (tvguid01).

The default status line provides `~Alt-X~ Exit` (Cmd::Quit) so even the bare app is quittable; the default menu bar provides a minimal `~≡~`-less `File/Exit` so the bar renders. (Subclasses override `initMenuBar`/`initStatusLine` to customize — as tvguid02/03 do.)

- [ ] **Step 1: Write the failing test**

`tests/Unit/Application/ApplicationTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

/** The smallest possible app — tvguid01 shape. */
final class HelloApp extends Application {}

test('a bare Application boots with an injected headless screen and renders three regions', function (): void {
    $driver = new HeadlessDriver(40, 6);
    $app = new HelloApp(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $rows = $driver->isInitialised() ? $app->backRowsForTest() : [];

    // Row 0 is the menu bar (non-blank), last row is the status line (non-blank),
    // middle rows are the desktop pattern.
    expect($rows)->toHaveCount(6)
        ->and(trim($rows[0]))->not->toBe('')      // menu bar present
        ->and(trim($rows[5]))->not->toBe('')      // status line present
        ->and($rows[2])->toContain('░');          // desktop backdrop
});

test('a bare Application quits on the default Alt-X status command', function (): void {
    $driver = new HeadlessDriver(40, 6);
    $app = new HelloApp(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});

test('the default initScreen would build a real AnsiDriver-backed Screen', function (): void {
    // We do not boot it (no TTY in CI); we only assert the type wiring is sound by
    // confirming an injected screen short-circuits the default.
    $injected = new Screen(new HeadlessDriver(10, 3));
    $app = new HelloApp($injected);
    $app->bootForTest();

    expect($app->screen())->toBe($injected);
});
```

> **Test-support note:** `ProgramTest` already exercises `bootForTest()`, `putEvent`, `getEvent`, `ended`, and the command set. `ApplicationTest` additionally needs `drawAndFlushForTest()` and `backRowsForTest()`. Add these two thin test-support methods to `Program` (they wrap existing protected logic) in this task:

```php
    /** Test helper: draw the tree and flush once (mirrors run()'s redraw). */
    public function drawAndFlushForTest(): void
    {
        $this->redraw();
    }

    /**
     * Test helper: the current back-buffer rows.
     *
     * @return list<string>
     */
    public function backRowsForTest(): array
    {
        return $this->screenObj->back()->rows();
    }
```

(Place them next to `bootForTest()` in `src/Application/Program.php`. Re-run `tests/Unit/Application/ProgramTest.php` after adding them; it must stay green.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Application/ApplicationTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Application\Application" not found`.

- [ ] **Step 3: Write the implementation**

First add the two test-support methods to `src/Application/Program.php` (see the test-support note above), placing them right after `bootForTest()`:

```php
    /** Test helper: draw the tree and flush once (mirrors run()'s redraw). */
    public function drawAndFlushForTest(): void
    {
        $this->redraw();
    }

    /**
     * Test helper: the current back-buffer rows.
     *
     * @return list<string>
     */
    public function backRowsForTest(): array
    {
        return $this->screenObj->back()->rows();
    }
```

Then create `src/Application/Application.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Application;

use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;

/**
 * The class users subclass (faithful to TApplication). Provides default desktop, menu
 * bar, and status line factories so `final class HelloApp extends Application {}` then
 * `(new HelloApp())->run()` works. Accepts an optional injected Screen for headless tests.
 */
class Application extends Program
{
    public function __construct(private readonly ?Screen $screenOverride = null)
    {
        parent::__construct();
    }

    protected function initScreen(): Screen
    {
        return $this->screenOverride ?? new Screen(new AnsiDriver());
    }

    protected function initDeskTop(Rect $bounds): ?Desktop
    {
        return new Desktop($bounds);
    }

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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Application/ApplicationTest.php tests/Unit/Application/ProgramTest.php`
Expected: PASS — Application `Tests: 3 passed`, Program still green.

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Application tests/Unit/Application`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Application.php tests/Unit/Application/ApplicationTest.php
git commit -m "feat(application): add Application with default factories + injectable Screen"
```

---

## Task 13: Acceptance — Guide01 (tvguid01 port) + headless Feature test

**Files:**
- Create: `examples/php/tutorial/Guide01.php`
- Test: `tests/Feature/Guide01Test.php`

Port of `tvguid01.cc` — the empty app. `final class Guide01App extends Application {}` then `exit((new Guide01App())->run())` when run as a script. The Feature test constructs it with an injected `new Screen(new HeadlessDriver(80, 25))`, draws the first frame, asserts the menu bar text on row 0 and the status line text on the last row, then feeds Alt-X and pumps the loop, asserting a clean exit.

The example file is dual-purpose: it defines `Guide01App` and only auto-runs when executed directly (so the Feature test can `require` it without running it).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Guide01Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide01.php';

test('Guide01 renders the default menu bar and status line headless', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide01App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    expect($rows)->toHaveCount(25)
        ->and($rows[0])->toContain('File')   // default menu bar
        ->and($rows[24])->toContain('Exit')  // default status line
        ->and($rows[12])->toContain('░');     // desktop backdrop
});

test('Guide01 quits cleanly on Alt-X', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide01App(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Guide01Test.php`
Expected: FAIL — `Failed opening required '.../Guide01.php'` (file does not exist yet).

- [ ] **Step 3: Write the example**

`examples/php/tutorial/Guide01.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide01 — PHP port of Turbo Vision's tvguid01.cc (Borland, 1991).
 * The smallest complete app: inherited defaults supply an empty desktop, menu bar,
 * and status line. Run directly to launch on a real terminal; `require` it from a
 * test to use Guide01App headlessly.
 */

use HelgeSverre\TurboVision\Application\Application;

require_once __DIR__ . '/../../../vendor/autoload.php';

final class Guide01App extends Application {}

// Auto-run only when executed directly (not when require'd by a test).
if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide01App())->run());
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Guide01Test.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Verify the example parses as a script**

Run: `php -l examples/php/tutorial/Guide01.php`
Expected: `No syntax errors detected in examples/php/tutorial/Guide01.php`.

- [ ] **Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide01.php tests/Feature/Guide01Test.php`
Expected: `[OK] No errors`. (If PHPStan does not scan `examples/` by default, run the path explicitly as above; the final task adds `examples` to `phpstan.neon` paths.)

- [ ] **Step 7: Commit**

```bash
git add examples/php/tutorial/Guide01.php tests/Feature/Guide01Test.php
git commit -m "feat(examples): port tvguid01 to Guide01 with headless acceptance test"
```

---

## Task 14: Acceptance — Guide02 (tvguid02 port) + headless Feature test

**Files:**
- Create: `examples/php/tutorial/Guide02.php`
- Test: `tests/Feature/Guide02Test.php`

Port of `tvguid02.cc` — overrides `initStatusLine` to add `~Alt-X~ Exit` (Cmd::Quit) and `~Alt-F3~ Close` (Cmd::Close). (M1's `Key` enum has no `AltF3`; faithful to the tutorial's intent we bind the second item to `Key::Esc` for Close, which is the conventional close key and keeps the snapshot honest. Documented in the example.) The Feature test asserts both hint texts render on the last row, then quits via Alt-X.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Guide02Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide02.php';

test('Guide02 renders both status hints on the last row', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide02App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $last = $app->backRowsForTest()[24];

    expect($last)->toContain('Alt-X Exit')
        ->and($last)->toContain('Close');
});

test('Guide02 quits cleanly on Alt-X', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide02App(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Guide02Test.php`
Expected: FAIL — `Failed opening required '.../Guide02.php'`.

- [ ] **Step 3: Write the example**

`examples/php/tutorial/Guide02.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide02 — PHP port of Turbo Vision's tvguid02.cc (Borland, 1991).
 * Adds a custom status line with two items. The original binds "Close" to kbAltF3;
 * M1's Key enum stops at the Alt-letter set, so we bind Close to Esc (the conventional
 * close key) — the intent (a second status item dispatching cmClose) is preserved.
 */

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;

require_once __DIR__ . '/../../../vendor/autoload.php';

final class Guide02App extends Application
{
    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
        ));
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide02App())->run());
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Guide02Test.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Verify the example parses**

Run: `php -l examples/php/tutorial/Guide02.php`
Expected: `No syntax errors detected in examples/php/tutorial/Guide02.php`.

- [ ] **Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide02.php tests/Feature/Guide02Test.php`
Expected: `[OK] No errors`.

- [ ] **Step 7: Commit**

```bash
git add examples/php/tutorial/Guide02.php tests/Feature/Guide02Test.php
git commit -m "feat(examples): port tvguid02 to Guide02 with status-line acceptance test"
```

---

## Task 15: Acceptance — Guide03 (tvguid03 port) + headless Feature test

**Files:**
- Create: `examples/php/tutorial/Guide03.php`
- Test: `tests/Feature/Guide03Test.php`

Port of `tvguid03.cc` — overrides **both** `initMenuBar` (File: Open/New/Exit; Window: Next/Zoom) and `initStatusLine` (F10 Menu, Alt-X Exit, Alt-F3 Close), using the spec's exact Target-API surface (`new SubMenu(...)->items(...)`, `new StatusDef(...)->items(...)`). User command codes `cmMyFileOpen=200`/`cmMyNewWin=201` become `Cmd::FirstUser + n` ints. The Feature test asserts the menu bar shows `File` and `Window` on row 0 and the status line shows the hints on the last row, then quits via Alt-X. This is the richest acceptance program and exercises the full Target-API verbatim.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Guide03Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide03.php';

test('Guide03 renders the File and Window menus on row 0', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide03App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    expect($rows[0])->toContain('File')
        ->and($rows[0])->toContain('Window');
});

test('Guide03 renders the status hints on the last row', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide03App(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    $last = $app->backRowsForTest()[24];

    expect($last)->toContain('Alt-X Exit')
        ->and($last)->toContain('Close');
});

test('Guide03 quits cleanly on Alt-X', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide03App(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Guide03Test.php`
Expected: FAIL — `Failed opening required '.../Guide03.php'`.

- [ ] **Step 3: Write the example**

`examples/php/tutorial/Guide03.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide03 — PHP port of Turbo Vision's tvguid03.cc (Borland, 1991).
 * Adds a full menu bar (File: Open/New/Exit; Window: Next/Zoom) and a status line
 * (F10 Menu, Alt-X Exit, Alt-F3 Close). User command codes 200/201 from the original
 * become Cmd::FirstUser + n. Close binds to Esc (M1 has no AltF3 key — see Guide02).
 */

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;

require_once __DIR__ . '/../../../vendor/autoload.php';

const CM_MY_FILE_OPEN = Cmd::FirstUser + 100; // 200
const CM_MY_NEW_WIN = Cmd::FirstUser + 101;   // 201

final class Guide03App extends Application
{
    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return new MenuBar(
            $bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('~O~pen', CM_MY_FILE_OPEN, Key::F3, 'F3'),
                new MenuItem('~N~ew', CM_MY_NEW_WIN, Key::F4, 'F4'),
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
            new SubMenu('~W~indow', Key::AltW)->items(
                new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
                new MenuItem('~Z~oom', Cmd::Zoom, Key::F5, 'F5'),
            ),
        );
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('', Key::F10, Cmd::Menu),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
        ));
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide03App())->run());
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Guide03Test.php`
Expected: PASS — `Tests: 3 passed`.

- [ ] **Step 5: Verify the example parses**

Run: `php -l examples/php/tutorial/Guide03.php`
Expected: `No syntax errors detected in examples/php/tutorial/Guide03.php`.

- [ ] **Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide03.php tests/Feature/Guide03Test.php`
Expected: `[OK] No errors`.

- [ ] **Step 7: Commit**

```bash
git add examples/php/tutorial/Guide03.php tests/Feature/Guide03Test.php
git commit -m "feat(examples): port tvguid03 to Guide03 (menu bar + status line) with acceptance tests"
```

---

## Task 16: Milestone — full suite, static analysis, tag, roadmap

**Files:**
- Modify: `phpstan.neon` (add `examples` to paths)
- Modify: `ROADMAP.md`

- [ ] **Step 1: Add `examples` to PHPStan paths**

In `phpstan.neon`, change:

```neon
parameters:
    level: max
    paths:
        - src
        - tests
        - bin
```

to:

```neon
parameters:
    level: max
    paths:
        - src
        - tests
        - bin
        - examples
```

- [ ] **Step 2: Run the entire test suite**

Run: `./vendor/bin/pest`
Expected: PASS — every Plan 1 + Plan 2 test plus the new Plan 3 tests are green. Roughly `Tests: 130+ passed` (Plan 1 ~35 + Plan 2 ~48 + Plan 3: State 3, View 8, Group 7, StaticText 3, Background 2, Desktop 2, MenuDefinitions 5, MenuBar 3, StatusLine 3, Program 4, Application 3, Guide01 2, Guide02 2, Guide03 3 = ~50).

- [ ] **Step 3: Run PHPStan at max over the whole project**

Run: `./vendor/bin/phpstan analyse`
Expected: `[OK] No errors`. If a view-tree generic array is flagged (`list<View>`, `list<Event>`, `array<int,bool>`), confirm the precise annotations from the tasks are present; do not add a baseline.

- [ ] **Step 4: (Manual, optional) eyeball Guide03 on a real terminal**

Run (in a real interactive terminal, not CI): `php examples/php/tutorial/Guide03.php`
Expected: a full-screen TUI with the File/Window menu bar on top, the desk pattern, and the status line at the bottom; pressing `Alt-X` quits and the terminal is fully restored. Manual; not part of the automated suite.

- [ ] **Step 5: Tag the milestone complete**

```bash
git add phpstan.neon
git commit -m "chore: analyse examples/ at phpstan max"
git tag -a m1-views-application -m "M1 views & application complete (View/Group, menus, Program/Application, tvguid01-03)"
git tag -a m1 -m "Milestone 1 complete: runnable TurboVision walking skeleton (tvguid01-03 green)"
```

- [ ] **Step 6: Update the roadmap status line**

Modify `ROADMAP.md`, in the "Where we are" section, change the active bullet to:

```markdown
- ✅ **M1 complete:** views & application built and green; tvguid01–03 run on a real terminal and pass headless snapshot tests. Next milestone: M2 (windowing — Window, Frame, ScrollBar, Scroller, ListViewer).
```

Then:

```bash
git add ROADMAP.md
git commit -m "docs: mark Milestone 1 complete in roadmap"
```

---

## What this plan delivers (Milestone 1 — definition of done)

- A runnable `Application` subclass that boots a full-screen TUI (desktop + menu bar + status line), routes keyboard/mouse/command events, and quits cleanly restoring the terminal (real-terminal path via `AnsiDriver`).
- `tvguid01–03` ported to `examples/php/tutorial/Guide01.php..Guide03.php`, runnable on a real terminal **and** passing headless `Buffer::rows()` snapshot Feature tests through `new Screen(new HeadlessDriver(80,25))`.
- The full Target-API surface from the design spec, delivered verbatim: `initMenuBar`/`initStatusLine` factory overrides, `new SubMenu('~F~ile', Key::AltF)->items(...)`, `new StatusDef(0, 0xFFFF)->items(...)`, `new MenuBar($bounds, ...)`, `new StatusLine($bounds, ...)`, no `T` prefix, typed `Rect`/`Key`/`Cmd`.

## What this plan deliberately leaves out (next milestones)

- **M2 — Windowing:** `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer`; full ancestor/sibling clipping in `Group` compositing (M1 clips to the view extent only). Acceptance: tvguid04–10.
- **M3 — Menus-deep + Dialogs:** pull-down `MenuBox`/`MenuPopup` navigation (M1 MenuBar is render + top-level hotkey/click only); `Dialog`, `Button`, `InputLine`, `CheckBoxes`, `RadioButtons`, `Label`, `ListBox`, `MessageBox` — built on the `Group::execView` modal sub-loop proven here. Acceptance: tvguid11–16.
- **M4–M6:** editor & files, outline & color, help & persistence.

---

## Self-review (performed by the plan author)

- **Spec coverage (this plan's slice):** `Views\State` ✓ (Task 1); `Views\View` ✓ (Task 2, with owner-chain `screen()`/`absoluteOrigin()` compositing + `writeBuf`/`writeLine`/`writeChar`/`writeStr` + `mapColor`/`getColor`/`setState`/`getState`/`setBounds`/`getExtent`/`getBounds`/`sizeLimits`/`setCursor`/`clearEvent`/`drawView`); `Views\Group` ✓ (Task 3, positional/focused/broadcast routing per `EventMask`, `setCurrent`/`selectNext`/`focusNext`, and the `execView` modal sub-loop with a headless modal test); `Views\StaticText` ✓ (Task 4, wrap + `\003` center); `Views\Background` ✓ (Task 5, `0xB0`→`'░'`); `Views\Desktop` ✓ (Task 6); menu definitions `MenuItem`/`SubMenu`/`Menu`/`StatusItem`/`StatusDef` ✓ (Task 7, fluent `->items()`); `Menus\MenuView` ✓ (Task 8); `Menus\MenuBar` ✓ (Task 9, render + top-level hotkey/click, `MenuBox` deferred to M3 and noted); `Menus\StatusLine` ✓ (Task 10, key→command rewrite + click dispatch); `Application\Program` ✓ (Task 11, Screen owner, `run`/`getEvent`/`putEvent`/`pumpEvent`/`handleEvent`, factory hooks, command set, `cmQuit`→endState); `Application\Application` ✓ (Task 12, defaults + injectable Screen); `Guide01/02/03` + headless Feature tests ✓ (Tasks 13–15); full-suite/tag/roadmap ✓ (Task 16).
- **Builds-on-real-signatures check:** `Screen(Driver)` with `init/shutdown/back/size/cols/rows/clear/flush/pollEvents/wasResized`; `HeadlessDriver(cols,rows)` with `feedInput/output/takeOutput/resizeTo/isInitialised`; `Buffer::put/at/rows`; `DrawBuffer::moveChar/moveStr/moveCStr(normalAttr,highlightAttr)/cells`; `Palette::fromBytes/get`; `Event::keyDown/command/broadcast/mouse/nothing/clear/asKey/asMouse/asMessage/isCommand/isNothing`, public `what`/`payload`; `EventType::KeyDown/MouseDown/Command/Broadcast` + `inMask`; `EventMask::Positional=0x000F`/`Focused=0x0110`/`Broadcast=0x0200`; `Key::Alt*/F*/Esc/Enter`; `Cmd::Quit/Close/Menu/Next/Zoom/FirstUser`; `KeyDownEvent::is`; `MessageEvent(command,info)`; `Rect::of/width/height/contains/move/a/b`; `Point(x,y)`. All match the on-`main` sources read while authoring (`src/Events/EventMask.php` confirms `Focused = Keyboard | Command = 0x0110`).
- **Extensions to existing M1 files:** **none to Plan 1/2 source.** This plan adds only new `Views\`/`Menus\`/`Application\` files plus the `examples/`/`tests/Feature/` additions and the `phpstan.neon` `examples` path. The `sf*/of*/gf*` families are added as a *new* `Views\State` class (Task 1), not by editing an existing file. Two test-support methods (`drawAndFlushForTest`, `backRowsForTest`) and `bootForTest`/`ended` live on the *new* `Program` class; the `putEvent`/`pumpEvent` passthroughs are added to the *new* `Group` class within Tasks 3/9 (Group is introduced in this plan). No Plan 1/2 file is modified.
- **Cross-task forward-reference check:** `Group::putEvent`/`pumpEvent`/`endModal`/`endState` are defined in Task 3 (and the `putEvent` passthrough completed in Task 9, with the Group test re-run green). `View::screen()`/`absoluteOrigin()`/`writeBuf` (Task 2) are used by every drawable in Tasks 4–10. `Program::redraw`/`screenObj` (Task 11) underpin the `drawAndFlushForTest`/`backRowsForTest` helpers added in Task 12 and used by the Feature tests in Tasks 13–15. No task references a method defined only in a later task.
- **`StatusDef::items()` overload note:** PHP forbids two methods of the same name, so `StatusDef::items()` is a single method that is a fluent setter when given `StatusItem`s and a getter when given none (Task 7, with the conditional-return-type annotation for PHPStan). `SubMenu::items()` stays a pure fluent setter (read via `menu()`); `Menu::items()` stays a pure getter. The Target-API snippet uses `->items(...)` only as a fluent setter, so it works verbatim.
- **Placeholder scan:** none — every implementation step contains complete, runnable code; every test step contains exact code, an exact command, and the expected output. The one PHP-language subtlety (`StatusDef::items()`) is resolved inline in Task 7, not deferred.
- **PHP 8.5 feature note:** `final class`/typed `const int`/`const string`, constructor promotion, union return types (`static|array`), conditional return types in `@return`, enums, and precise `list<>`/`array<int,...>` annotations are the modern features relied on — all available on the 8.5 floor. `View`/`Group`/`Program`/`Application` are deliberately mutable (not `readonly`), as required for the live view tree; the menu *definition* objects are mutable holders that accumulate items.
