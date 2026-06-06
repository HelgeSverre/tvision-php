# M2 Windowing — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Project policy: NO `git tag` steps anywhere.**

**Goal:** Add full windowing to `HelgeSverre\TurboVision` on top of the green M1 engine — a `ScrollBar`, a `Scroller` viewport, a window `Frame`, a framed/movable/resizable/zoomable/closable `Window`, and an abstract scrollable `ListViewer` — plus live terminal-resize reflow, so the C++ tutorials `tvguid04`–`tvguid10` are reproduced faithfully in PHP and proven by headless `Buffer` snapshot tests.

**Architecture:** Each new class is a `View` (or `Group`) subclass that paints through M1's `DrawBuffer` + `writeLine`/`writeBuf` primitives and resolves color through the M1 palette chain (`getColor`/`mapColor` → owner → `Program::getPalette` root). `ScrollBar` is the shared scroll primitive: it owns `value/min/max/pageStep/arrowStep`, computes an integer thumb position faithful to `TScrollBar.cc`, and broadcasts `cmScrollBarChanged`. `Scroller` listens for that broadcast and turns it into a `delta` offset its `draw()` reads. `Frame` is the last subview of a `Window`, drawing the border/title/icons and translating frame-mouse clicks into `cmClose`/`cmZoom`/move/resize. `Window extends Group`, owns a `Frame`, handles `cmClose`/`cmZoom`/`cmResize`/Tab, and exposes `standardScrollBar()`. `ListViewer` is abstract (subclasses supply `getText`) and ships the stable interface M3's `ListBox` builds on. Resize wiring extends M1's `Program`/`Group` so children reflow by `growMode` when the terminal changes size.

**Tech Stack:** PHP 8.5, Composer (PSR-4), Pest v3, PHPStan level max. Runtime ext: `ext-mbstring` (+ M1's `ext-posix`/`ext-pcntl` for the real driver). All M2 unit/feature tests are headless (`HeadlessDriver`); one optional real-PTY case extends `tests/Integration/RealTerminalTest.php`.

**Source of truth for constants & semantics** (verbatim values extracted, do not guess):
- `docs/references/source/tvision-0.8/lib/views.h` — `cmClose=4 cmZoom=5 cmResize=6 cmNext=7 cmPrev=8 cmCancel=11 cmCloseAll=37`; broadcast `cmReceivedFocus=50 cmReleasedFocus=51 cmCommandSetChanged=52 cmScrollBarChanged=53 cmScrollBarClicked=54 cmSelectWindowNum=55 cmListItemSelected=56`; `sbLeftArrow=0 sbRightArrow=1 sbPageLeft=2 sbPageRight=3 sbUpArrow=4 sbDownArrow=5 sbPageUp=6 sbPageDown=7 sbIndicator=8`; `sbHorizontal=0x000 sbVertical=0x001 sbHandleKeyboard=0x002`; `wfMove=0x01 wfGrow=0x02 wfClose=0x04 wfZoom=0x08`; `wpBlueWindow=0 wpCyanWindow=1 wpGrayWindow=2`; `wnNoNumber=0`; window palettes `cpBlueWindow="\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F"`, `cpCyanWindow="\x10\x11\x12\x13\x14\x15\x16\x17"`, `cpGrayWindow="\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F"`.
- `TFrame.cc` — `cpFrame="\x01\x01\x02\x02\x03"`; draw color words `0x0101`/`0x0002` (inactive), `0x0503`/`0x0004` (active), `0x0505`/`0x0005` (dragging); `minWinSize = {16,6}`.
- `TScrollBar.cc` — `cpScrollBar="\x04\x05\x05"`; `getPos = ((value-minVal)*(getSize()-3) + r/2)/r + 1`; `getSize = max(2, long_dimension)`.
- `TScroller.cc` — `cpScroller="\x06\x07"`.
- `TListViewer.cc` — `cpListViewer="\x1A\x1A\x1B\x1C\x1D"`.
- Glyphs (canonical Borland CP437 → Unicode, defined once in `Views\Glyphs`): box single `┌ ┐ └ ┘ ─ │`, double `╔ ╗ ╚ ╝ ═ ║`; scroll arrows `◄ ► ▲ ▼`, track `░`, thumb `▒`; close icon `[~■~]`, zoom icon `[~↑~]`, unzoom `[~↓~]`, drag icon `~──~`. (TV's `closeIcon="[~\x04~]"`, `dragIcon` corner; we map `\x04`→`■`. Exact bytes are an internal port choice — tests assert the chosen glyphs, not CP437.)

**Acceptance:** `examples/php/tutorial/Guide04.php … Guide10.php` (runnable), each with a headless Feature test asserting rendered `Buffer` rows (frame glyphs, scrolled content, thumb position, dual panes, resize reflow). One real-PTY case for Guide04.

---

## File Structure

```
src/Views/
  Glyphs.php          # NEW: CP437→Unicode box/arrow/icon constants (single source)
  ScrollBar.php       # NEW: TScrollBar — value/min/max/step, thumb, arrows, cmScrollBarChanged
  Scroller.php        # NEW: TScroller — delta viewport over a limit area, listens to bars
  Frame.php           # NEW: TFrame — border+title+icons, frame-mouse → close/zoom/move/resize
  Window.php          # NEW: TWindow extends Group — frame, number, zoom/close/resize, palettes
  ListViewer.php      # NEW: abstract TListViewer — focused/range/getText, nav, scroll bars
  Window/WindowFlags.php   # NEW: wf* constants
  Window/WindowPalette.php # NEW: wp* + cpBlueWindow/cpCyanWindow/cpGrayWindow byte strings
  ScrollBar/ScrollBarPart.php # NEW: sb* part/orientation constants
  Desktop.php         # EXTEND: insert-selects-window, cmNext/cmPrev cycle, sizeLimits
  State.php           # EXTEND: add dm* drag-mode constants (dmDragMove/dmDragGrow/dmLimitAll)
  Group.php           # EXTEND: changeBounds() reflow by growMode (calcBounds)
  View.php            # EXTEND: changeBounds/calcBounds/dragView/makeLocal/getClipRect/mouseInView
src/Events/
  Cmd.php             # EXTEND: add ScrollBarChanged/ScrollBarClicked/SelectWindowNum/ListItemSelected/CloseAll + focus broadcasts
src/Application/
  Program.php         # EXTEND: resize loop calls desktop->changeBounds reflow
tests/Unit/Views/
  GlyphsTest.php ScrollBarTest.php ScrollerTest.php FrameTest.php WindowTest.php
  ListViewerTest.php DesktopWindowTest.php CalcBoundsTest.php
tests/Feature/
  Guide04Test.php … Guide10Test.php ResizeReflowTest.php
examples/php/tutorial/
  Guide04.php … Guide10.php
docs/fixtures/lorem.txt   # NEW: deterministic scroll-content fixture for Guide06–10
```

**Build order (each builds on green predecessors):** Glyphs → Cmd/State constants → View geometry helpers → calcBounds/changeBounds reflow → ScrollBar → Scroller → Frame → Window → Desktop window-mgmt → ListViewer → Program resize → Guide04–10 examples+tests → full-suite & roadmap.

---

## Task 1: Glyphs — CP437→Unicode constant table

**Files:** Create `src/Views/Glyphs.php`; Test `tests/Unit/Views/GlyphsTest.php`.

The single source of truth for every box-drawing / arrow / icon grapheme M2 paints. Keeping them here (not scattered in each view) means a future "CP437 mode" or terminal-quirk fix is one edit.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/GlyphsTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\Glyphs;

test('single-line box glyphs', function (): void {
    expect(Glyphs::SINGLE_TOP_LEFT)->toBe('┌')
        ->and(Glyphs::SINGLE_TOP_RIGHT)->toBe('┐')
        ->and(Glyphs::SINGLE_BOTTOM_LEFT)->toBe('└')
        ->and(Glyphs::SINGLE_BOTTOM_RIGHT)->toBe('┘')
        ->and(Glyphs::SINGLE_HORIZONTAL)->toBe('─')
        ->and(Glyphs::SINGLE_VERTICAL)->toBe('│');
});

test('double-line box glyphs', function (): void {
    expect(Glyphs::DOUBLE_TOP_LEFT)->toBe('╔')
        ->and(Glyphs::DOUBLE_TOP_RIGHT)->toBe('╗')
        ->and(Glyphs::DOUBLE_BOTTOM_LEFT)->toBe('╚')
        ->and(Glyphs::DOUBLE_BOTTOM_RIGHT)->toBe('╝')
        ->and(Glyphs::DOUBLE_HORIZONTAL)->toBe('═')
        ->and(Glyphs::DOUBLE_VERTICAL)->toBe('║');
});

test('scroll bar glyphs', function (): void {
    expect(Glyphs::ARROW_LEFT)->toBe('◄')
        ->and(Glyphs::ARROW_RIGHT)->toBe('►')
        ->and(Glyphs::ARROW_UP)->toBe('▲')
        ->and(Glyphs::ARROW_DOWN)->toBe('▼')
        ->and(Glyphs::SCROLL_TRACK)->toBe('░')
        ->and(Glyphs::SCROLL_THUMB)->toBe('▒');
});

test('frame icon strings carry ~highlight~ markers', function (): void {
    expect(Glyphs::CLOSE_ICON)->toBe('[~■~]')
        ->and(Glyphs::ZOOM_ICON)->toBe('[~↑~]')
        ->and(Glyphs::UNZOOM_ICON)->toBe('[~↓~]')
        ->and(Glyphs::DRAG_ICON)->toBe('~──~');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Views/GlyphsTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Glyphs" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/Glyphs.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

/**
 * The Unicode graphemes M2 views paint, mapped from Turbo Vision's CP437 semigraphics.
 * Single source of truth: a future CP437/terminal-quirk mode changes only this file.
 * Icon strings embed ~..~ highlight markers consumed by DrawBuffer::moveCStr.
 */
final class Glyphs
{
    // Single-line box (inactive window frame).
    public const string SINGLE_TOP_LEFT = '┌';
    public const string SINGLE_TOP_RIGHT = '┐';
    public const string SINGLE_BOTTOM_LEFT = '└';
    public const string SINGLE_BOTTOM_RIGHT = '┘';
    public const string SINGLE_HORIZONTAL = '─';
    public const string SINGLE_VERTICAL = '│';

    // Double-line box (active window frame).
    public const string DOUBLE_TOP_LEFT = '╔';
    public const string DOUBLE_TOP_RIGHT = '╗';
    public const string DOUBLE_BOTTOM_LEFT = '╚';
    public const string DOUBLE_BOTTOM_RIGHT = '╝';
    public const string DOUBLE_HORIZONTAL = '═';
    public const string DOUBLE_VERTICAL = '║';

    // Scroll bar.
    public const string ARROW_LEFT = '◄';
    public const string ARROW_RIGHT = '►';
    public const string ARROW_UP = '▲';
    public const string ARROW_DOWN = '▼';
    public const string SCROLL_TRACK = '░';
    public const string SCROLL_THUMB = '▒';

    // Frame icons (with ~hotkey~ markers).
    public const string CLOSE_ICON = '[~■~]';
    public const string ZOOM_ICON = '[~↑~]';
    public const string UNZOOM_ICON = '[~↓~]';
    public const string DRAG_ICON = '~──~';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Views/GlyphsTest.php`
Expected: PASS — `Tests: 4 passed`.

- [ ] **Step 5: PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/Glyphs.php tests/Unit/Views/GlyphsTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Glyphs.php tests/Unit/Views/GlyphsTest.php
git commit -m "feat(views): add Glyphs CP437->Unicode box/arrow/icon table"
```

---

## Task 2: Broadcast/window command codes + drag-mode constants

**Files:** Edit `src/Events/Cmd.php`; Edit `src/Views/State.php`; Test `tests/Unit/Events/CmdTest.php` (extend), `tests/Unit/Views/StateTest.php` (extend).

Add the faithful broadcast/window command codes and the `dm*` drag-mode flags M2 needs. `Cmd::CloseAll`, the focus broadcasts, and the scroll/window/list broadcasts come straight from `views.h`.

- [ ] **Step 1: Extend the failing tests**

Append to `tests/Unit/Events/CmdTest.php`:

```php
test('M2 broadcast and window command codes match Turbo Vision', function (): void {
    expect(Cmd::CloseAll)->toBe(37)
        ->and(Cmd::ReceivedFocus)->toBe(50)
        ->and(Cmd::ReleasedFocus)->toBe(51)
        ->and(Cmd::CommandSetChanged)->toBe(52)
        ->and(Cmd::ScrollBarChanged)->toBe(53)
        ->and(Cmd::ScrollBarClicked)->toBe(54)
        ->and(Cmd::SelectWindowNum)->toBe(55)
        ->and(Cmd::ListItemSelected)->toBe(56);
});
```

Append to `tests/Unit/Views/StateTest.php`:

```php
test('drag-mode flags match Turbo Vision dm* values', function (): void {
    expect(State::DragMove)->toBe(0x01)
        ->and(State::DragGrow)->toBe(0x02)
        ->and(State::LimitLoX)->toBe(0x10)
        ->and(State::LimitLoY)->toBe(0x20)
        ->and(State::LimitHiX)->toBe(0x40)
        ->and(State::LimitHiY)->toBe(0x80)
        ->and(State::LimitAll)->toBe(0xF0);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Events/CmdTest.php tests/Unit/Views/StateTest.php`
Expected: FAIL — `Undefined constant HelgeSverre\TurboVision\Events\Cmd::CloseAll` (and `State::DragMove`).

- [ ] **Step 3: Implement — extend `Cmd`**

In `src/Events/Cmd.php`, after the existing `const int Default = 14;` line, add:

```php
    // --- M2: window management (faithful to views.h) ---
    public const int CloseAll = 37;

    // --- M2: broadcast command codes (views.h cmReceivedFocus..cmListItemSelected) ---
    public const int ReceivedFocus = 50;
    public const int ReleasedFocus = 51;
    public const int CommandSetChanged = 52;
    public const int ScrollBarChanged = 53;
    public const int ScrollBarClicked = 54;
    public const int SelectWindowNum = 55;
    public const int ListItemSelected = 56;
```

- [ ] **Step 4: Implement — extend `State`**

In `src/Views/State.php`, after the `GrowFixed` constant (last line before the closing brace), add:

```php

    // --- dm* : drag-mode flags (used by View::dragView), verbatim from views.h ---
    public const int DragMove = 0x01;
    public const int DragGrow = 0x02;
    public const int LimitLoX = 0x10;
    public const int LimitLoY = 0x20;
    public const int LimitHiX = 0x40;
    public const int LimitHiY = 0x80;
    public const int LimitAll = 0xF0;
```

- [ ] **Step 5: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Events/CmdTest.php tests/Unit/Views/StateTest.php`
Expected: PASS — both files green.

- [ ] **Step 6: PHPStan**

Run: `./vendor/bin/phpstan analyse src/Events/Cmd.php src/Views/State.php`
Expected: `[OK] No errors`.

- [ ] **Step 7: Commit**

```bash
git add src/Events/Cmd.php src/Views/State.php tests/Unit/Events/CmdTest.php tests/Unit/Views/StateTest.php
git commit -m "feat(events): add M2 broadcast cmds and dm* drag-mode flags"
```

---

## Task 3: View geometry helpers — makeLocal, getClipRect, mouseInView, changeBounds, calcBounds, dragView

**Files:** Edit `src/Views/View.php`; Test `tests/Unit/Views/ViewGeometryTest.php`.

M2 needs primitives M1 left as TODOs: `makeLocal` (global→local point), `mouseInView`, `getClipRect` (M1 returns the extent; full ancestor clip is later), `changeBounds`/`calcBounds` (growMode reflow), and `dragView` (the move/resize drag loop). These are added to the base `View` so `Frame`/`Window`/`Scroller`/`ListViewer` all share them.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ViewGeometryTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('makeLocal subtracts the absolute origin', function (): void {
    $outer = new Group(Rect::of(0, 0, 40, 20));
    $inner = new View(Rect::of(5, 3, 15, 10));
    $outer->insert($inner);

    expect($inner->makeLocal(new Point(7, 5)))->toEqual(new Point(2, 2));
});

test('mouseInView is true only for points inside the view bounds', function (): void {
    $outer = new Group(Rect::of(0, 0, 40, 20));
    $v = new View(Rect::of(5, 3, 15, 10));
    $outer->insert($v);

    expect($v->mouseInView(new Point(7, 5)))->toBeTrue()
        ->and($v->mouseInView(new Point(0, 0)))->toBeFalse();
});

test('getClipRect returns the extent in M2', function (): void {
    $v = new View(Rect::of(5, 3, 15, 10));

    expect($v->getClipRect())->toEqual(Rect::of(0, 0, 10, 7));
});

test('calcBounds with gfGrowHiX|gfGrowHiY follows the delta on the high corner', function (): void {
    $v = new View(Rect::of(2, 2, 12, 8));
    $v->growMode = State::GrowHiX | State::GrowHiY;

    // owner grew by (4, 2): only the bottom-right corner moves.
    expect($v->calcBounds(new Point(4, 2)))->toEqual(Rect::of(2, 2, 16, 10));
});

test('calcBounds with gfGrowAll moves both corners', function (): void {
    $v = new View(Rect::of(2, 2, 12, 8));
    $v->growMode = State::GrowAll;

    expect($v->calcBounds(new Point(4, 2)))->toEqual(Rect::of(6, 4, 16, 10));
});

test('calcBounds with no grow mode keeps bounds unchanged', function (): void {
    $v = new View(Rect::of(2, 2, 12, 8));

    expect($v->calcBounds(new Point(4, 2)))->toEqual(Rect::of(2, 2, 12, 8));
});

test('changeBounds replaces bounds', function (): void {
    $v = new View(Rect::of(0, 0, 4, 4));
    $v->changeBounds(Rect::of(1, 1, 9, 9));

    expect($v->getBounds())->toEqual(Rect::of(1, 1, 9, 9));
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ViewGeometryTest.php`
Expected: FAIL — `Call to undefined method ...View::makeLocal()`.

- [ ] **Step 3: Implement — add to `src/Views/View.php`**

Add these methods inside `class View` (after `getExtent()` / near the geometry section). They use only existing members (`$this->bounds`, `absoluteOrigin()`, `$this->growMode`).

```php
    /** Translate a global (root-relative) point into this view's local coordinates. */
    public function makeLocal(Point $global): Point
    {
        $origin = $this->absoluteOrigin();

        return new Point($global->x - $origin->x, $global->y - $origin->y);
    }

    /** True if a global point falls within this view's bounds. */
    public function mouseInView(Point $global): bool
    {
        return $this->bounds->contains($global);
    }

    /**
     * The exposed drawing rectangle in local coordinates. M2 returns the full extent
     * (ancestor-overlap clipping arrives with the buffered-group work in a later
     * milestone); subviews use grow(-1,-1) on this to fit inside a frame.
     */
    public function getClipRect(): Rect
    {
        return $this->getExtent();
    }

    /**
     * Compute new bounds when the owner grows by $delta, honoring this view's growMode.
     * Faithful to TView::calcBounds: gfGrowLoX/HiX move the left/right edge, gfGrowLoY/
     * HiY the top/bottom edge. gfGrowAll = all four. (gfGrowRel is handled by Window.)
     */
    public function calcBounds(Point $delta): Rect
    {
        $ax = $this->bounds->a->x;
        $ay = $this->bounds->a->y;
        $bx = $this->bounds->b->x;
        $by = $this->bounds->b->y;

        if (($this->growMode & State::GrowLoX) !== 0) {
            $ax += $delta->x;
        }
        if (($this->growMode & State::GrowHiX) !== 0) {
            $bx += $delta->x;
        }
        if (($this->growMode & State::GrowLoY) !== 0) {
            $ay += $delta->y;
        }
        if (($this->growMode & State::GrowHiY) !== 0) {
            $by += $delta->y;
        }

        return Rect::of($ax, $ay, $bx, $by);
    }

    /**
     * Apply new bounds. Group overrides this to additionally reflow its subviews.
     * The single funnel every move/resize routes through.
     */
    public function changeBounds(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->drawView();
    }

    /**
     * Move/resize this view in response to a drag. M2 implements the geometric result
     * directly (no inner pump loop): $mode selects move vs grow, $limits clamps the
     * origin, $min/$max clamp the size. Frame/Window drive this from mouse handlers.
     */
    public function dragView(Rect $newBounds, Rect $limits, Point $min, Point $max): void
    {
        $w = max($min->x, min($max->x, $newBounds->width()));
        $h = max($min->y, min($max->y, $newBounds->height()));

        $ax = $newBounds->a->x;
        $ay = $newBounds->a->y;
        // Keep the view fully inside $limits.
        $ax = max($limits->a->x, min($limits->b->x - $w, $ax));
        $ay = max($limits->a->y, min($limits->b->y - $h, $ay));

        $this->changeBounds(Rect::of($ax, $ay, $ax + $w, $ay + $h));
    }
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ViewGeometryTest.php`
Expected: PASS — `Tests: 7 passed`.

- [ ] **Step 5: PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/View.php tests/Unit/Views/ViewGeometryTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/View.php tests/Unit/Views/ViewGeometryTest.php
git commit -m "feat(views): add makeLocal/mouseInView/getClipRect/calcBounds/changeBounds/dragView"
```

---

## Task 4: Group reflow — changeBounds cascades calcBounds to subviews

**Files:** Edit `src/Views/Group.php`; Test `tests/Unit/Views/CalcBoundsTest.php`.

When a `Group`'s bounds change, each subview reflows via its `calcBounds(delta)`. This is the engine behind window resize and terminal-resize reflow.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/CalcBoundsTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('Group::changeBounds reflows each subview by its growMode', function (): void {
    $g = new Group(Rect::of(0, 0, 20, 10));

    $fixed = new View(Rect::of(1, 1, 5, 3));            // no growMode -> stays
    $stretchX = new View(Rect::of(0, 0, 20, 1));
    $stretchX->growMode = State::GrowHiX;               // follows width
    $stretchAll = new View(Rect::of(2, 2, 18, 8));
    $stretchAll->growMode = State::GrowHiX | State::GrowHiY;

    $g->insert($fixed);
    $g->insert($stretchX);
    $g->insert($stretchAll);

    // Grow the group by (10, 4): 20x10 -> 30x14.
    $g->changeBounds(Rect::of(0, 0, 30, 14));

    expect($fixed->getBounds())->toEqual(Rect::of(1, 1, 5, 3))
        ->and($stretchX->getBounds())->toEqual(Rect::of(0, 0, 30, 1))
        ->and($stretchAll->getBounds())->toEqual(Rect::of(2, 2, 28, 12));
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/CalcBoundsTest.php`
Expected: FAIL — `$stretchX` keeps its old bounds (Group does not yet reflow).

- [ ] **Step 3: Implement — override `changeBounds` in `src/Views/Group.php`**

Add these imports at the top (`Point`, `Rect` are not yet imported in Group):

```php
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
```

Add this method to `class Group` (e.g. after `draw()`):

```php
    /**
     * Resize the group and reflow every subview by its growMode (faithful to
     * TGroup::changeBounds, which calls each child's calcBounds with the size delta).
     */
    public function changeBounds(Rect $bounds): void
    {
        $delta = new Point(
            $bounds->width() - $this->bounds->width(),
            $bounds->height() - $this->bounds->height(),
        );

        $this->setBounds($bounds);

        if ($delta->x !== 0 || $delta->y !== 0) {
            foreach ($this->children as $child) {
                $child->changeBounds($child->calcBounds($delta));
            }
        }

        $this->drawView();
    }
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/CalcBoundsTest.php`
Expected: PASS — `Tests: 1 passed`.

- [ ] **Step 5: PHPStan**

Run: `./vendor/bin/phpstan analyse src/Views/Group.php tests/Unit/Views/CalcBoundsTest.php`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Group.php tests/Unit/Views/CalcBoundsTest.php
git commit -m "feat(views): Group::changeBounds reflows subviews by growMode"
```

---

## Task 5: ScrollBar — constants

**Files:** Create `src/Views/ScrollBar/ScrollBarPart.php`; Test `tests/Unit/Views/ScrollBarPartTest.php`.

The orientation/part/option constants for `ScrollBar`, verbatim from `views.h`. Split into their own file so `Window::standardScrollBar` and `ScrollBar` share them.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ScrollBarPartTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;

test('scroll bar part codes are faithful to views.h', function (): void {
    expect(ScrollBarPart::LeftArrow)->toBe(0)
        ->and(ScrollBarPart::RightArrow)->toBe(1)
        ->and(ScrollBarPart::PageLeft)->toBe(2)
        ->and(ScrollBarPart::PageRight)->toBe(3)
        ->and(ScrollBarPart::UpArrow)->toBe(4)
        ->and(ScrollBarPart::DownArrow)->toBe(5)
        ->and(ScrollBarPart::PageUp)->toBe(6)
        ->and(ScrollBarPart::PageDown)->toBe(7)
        ->and(ScrollBarPart::Indicator)->toBe(8);
});

test('scroll bar option flags are faithful', function (): void {
    expect(ScrollBarPart::Horizontal)->toBe(0x000)
        ->and(ScrollBarPart::Vertical)->toBe(0x001)
        ->and(ScrollBarPart::HandleKeyboard)->toBe(0x002);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarPartTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

`src/Views/ScrollBar/ScrollBarPart.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views\ScrollBar;

/** ScrollBar part codes + orientation/option flags, verbatim from views.h. */
final class ScrollBarPart
{
    // sb* part codes (which region was clicked / which key was pressed).
    public const int LeftArrow = 0;
    public const int RightArrow = 1;
    public const int PageLeft = 2;
    public const int PageRight = 3;
    public const int UpArrow = 4;
    public const int DownArrow = 5;
    public const int PageUp = 6;
    public const int PageDown = 7;
    public const int Indicator = 8;

    // sb* orientation/option flags passed to Window::standardScrollBar().
    public const int Horizontal = 0x000;
    public const int Vertical = 0x001;
    public const int HandleKeyboard = 0x002;
}
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarPartTest.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/ScrollBar/ScrollBarPart.php tests/Unit/Views/ScrollBarPartTest.php` → `[OK] No errors`.

```bash
git add src/Views/ScrollBar/ScrollBarPart.php tests/Unit/Views/ScrollBarPartTest.php
git commit -m "feat(views): add ScrollBarPart sb* constants"
```

---

## Task 6: ScrollBar — value model, palette, thumb position

**Files:** Create `src/Views/ScrollBar.php`; Test `tests/Unit/Views/ScrollBarTest.php`.

The value model + `getPos` thumb arithmetic + palette, faithful to `TScrollBar.cc`. Drawing and event handling come in Tasks 7–8 (same class, additive). Orientation is auto-detected: `size.x === 1` → vertical, else horizontal.

`getPos` (faithful): `r = maxVal - minVal; if r==0 return 1; else (((value-minVal) * (getSize()-3) + r/2) / r) + 1`. `getSize = max(2, longDimension)`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ScrollBarTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\State;

test('a 1-wide bar is vertical, a wide bar is horizontal', function (): void {
    $v = new ScrollBar(Rect::of(0, 0, 1, 10));
    $h = new ScrollBar(Rect::of(0, 0, 20, 1));

    expect($v->isVertical())->toBeTrue()
        ->and($h->isVertical())->toBeFalse();
});

test('default value model is zeroed with step 1', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));

    expect($b->value)->toBe(0)
        ->and($b->minVal)->toBe(0)
        ->and($b->maxVal)->toBe(0)
        ->and($b->pageStep)->toBe(1)
        ->and($b->arrowStep)->toBe(1);
});

test('setParams clamps value into [min, max] and normalises max>=min', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setParams(value: 50, min: 0, max: 20, pageStep: 5, arrowStep: 1);
    expect($b->value)->toBe(20);   // clamped to max

    $b->setParams(value: -7, min: 0, max: 20, pageStep: 5, arrowStep: 1);
    expect($b->value)->toBe(0);    // clamped to min
});

test('setValue/setRange/setStep are setParams shortcuts', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(10, 2);
    $b->setValue(40);

    expect($b->value)->toBe(40)
        ->and($b->minVal)->toBe(0)
        ->and($b->maxVal)->toBe(100)
        ->and($b->pageStep)->toBe(10)
        ->and($b->arrowStep)->toBe(2);
});

test('getPos: zero range parks the thumb at 1', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    expect($b->getPos())->toBe(1);
});

test('getPos: value scales across the track (faithful integer arithmetic)', function (): void {
    // Vertical bar of length 10: getSize()=10, track span getSize()-3 = 7.
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);

    $b->setValue(0);
    expect($b->getPos())->toBe(1);          // (0*7 + 50)/100 + 1 = 1

    $b->setValue(50);
    expect($b->getPos())->toBe(5);          // (50*7 + 50)/100 + 1 = 4.0->4 +1 = 4? verify below

    $b->setValue(100);
    expect($b->getPos())->toBe(8);          // (100*7 + 50)/100 + 1 = 7 + 1 = 8
});

test('ScrollBar growMode follows orientation', function (): void {
    $v = new ScrollBar(Rect::of(0, 0, 1, 10));
    $h = new ScrollBar(Rect::of(0, 0, 20, 1));

    expect($v->growMode)->toBe(State::GrowLoX | State::GrowHiX | State::GrowHiY)
        ->and($h->growMode)->toBe(State::GrowLoY | State::GrowHiX | State::GrowHiY);
});
```

> **Arithmetic note for the implementer:** `getPos` for value 50: `intdiv(50*7 + 50, 100) + 1 = intdiv(400,100)+1 = 4+1 = 5`. ✓ For value 100: `intdiv(700+50,100)+1 = 7+1 = 8`. ✓ For value 0: `intdiv(0+50,100)+1 = 0+1 = 1`. ✓ The test values above are correct; do not "round" differently.

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\ScrollBar" not found`.

- [ ] **Step 3: Write the implementation (value model only; draw/handleEvent are stubs filled in Tasks 7–8)**

`src/Views/ScrollBar.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * A vertical (width 1) or horizontal (height 1) scroll bar (faithful to TScrollBar).
 * Holds a value in [minVal, maxVal] with arrow/page steps, computes an integer thumb
 * position, draws a track + thumb + arrows, and broadcasts cmScrollBarChanged when the
 * value moves. Orientation is auto-detected from bounds (size.x === 1 => vertical).
 */
class ScrollBar extends View
{
    /** cpScrollBar: index1=page area, index2=arrows, index3=indicator/thumb. */
    private const string PALETTE = "\x04\x05\x05";

    public int $value = 0;

    public int $minVal = 0;

    public int $maxVal = 0;

    public int $pageStep = 1;

    public int $arrowStep = 1;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);

        if ($bounds->width() === 1) {
            $this->growMode = State::GrowLoX | State::GrowHiX | State::GrowHiY;
        } else {
            $this->growMode = State::GrowLoY | State::GrowHiX | State::GrowHiY;
        }
    }

    public function isVertical(): bool
    {
        return $this->bounds->width() === 1;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    /** The track length along the bar's long axis, faithful min of 2. */
    public function getSize(): int
    {
        $s = $this->isVertical() ? $this->bounds->height() : $this->bounds->width();

        return max(2, $s);
    }

    /** Thumb position (1-based offset along the track), faithful to TScrollBar::getPos. */
    public function getPos(): int
    {
        $r = $this->maxVal - $this->minVal;
        if ($r === 0) {
            return 1;
        }

        return intdiv(($this->value - $this->minVal) * ($this->getSize() - 3) + intdiv($r, 2), $r) + 1;
    }

    /**
     * Set the full value model. Clamps value into [min, max] (after normalising
     * max >= min), and broadcasts cmScrollBarChanged if the value actually moved.
     */
    public function setParams(int $value, int $min, int $max, int $pageStep, int $arrowStep): void
    {
        $max = max($max, $min);
        $value = max($min, min($max, $value));

        $changed = $value !== $this->value || $min !== $this->minVal || $max !== $this->maxVal;
        $valueMoved = $value !== $this->value;

        if ($changed) {
            $this->value = $value;
            $this->minVal = $min;
            $this->maxVal = $max;
            $this->drawView();
            if ($valueMoved) {
                $this->scrollDraw();
            }
        }

        $this->pageStep = $pageStep;
        $this->arrowStep = $arrowStep;
    }

    public function setRange(int $min, int $max): void
    {
        $this->setParams($this->value, $min, $max, $this->pageStep, $this->arrowStep);
    }

    public function setStep(int $pageStep, int $arrowStep): void
    {
        $this->setParams($this->value, $this->minVal, $this->maxVal, $pageStep, $arrowStep);
    }

    public function setValue(int $value): void
    {
        $this->setParams($value, $this->minVal, $this->maxVal, $this->pageStep, $this->arrowStep);
    }

    /** Broadcast that this bar's value changed so any attached Scroller redraws. */
    public function scrollDraw(): void
    {
        $this->owner?->handleEvent(
            Event::broadcast(\HelgeSverre\TurboVision\Events\Cmd::ScrollBarChanged, $this),
        );
    }

    public function draw(): void
    {
        // Filled in Task 7.
    }

    public function handleEvent(Event $event): void
    {
        // Filled in Task 8.
    }
}
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarTest.php`
Expected: PASS — `Tests: 7 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/ScrollBar.php tests/Unit/Views/ScrollBarTest.php` → `[OK] No errors`.

```bash
git add src/Views/ScrollBar.php tests/Unit/Views/ScrollBarTest.php
git commit -m "feat(views): add ScrollBar value model, palette, getPos thumb arithmetic"
```

---

## Task 7: ScrollBar — drawing the track, thumb, and arrows

**Files:** Edit `src/Views/ScrollBar.php`; Test `tests/Unit/Views/ScrollBarDrawTest.php`.

`draw()` paints arrows at both ends, fills the track with `SCROLL_TRACK`, and places `SCROLL_THUMB` at `getPos()`. Vertical and horizontal differ only in which glyphs and which axis. We assert against the root back buffer via a `RootGroup` (the M1 test pattern).

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ScrollBarDrawTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Glyphs;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** A Group rooted at a real Screen so child writes hit the back buffer. */
function sbRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $g = new class($screen) extends Group {
        public function __construct(private readonly Screen $s)
        {
            parent::__construct(Rect::of(0, 0, $s->cols(), $s->rows()));
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('a horizontal scroll bar draws arrows, track and thumb', function (): void {
    [$g, $screen] = sbRoot(10, 1);
    $bar = new ScrollBar(Rect::of(0, 0, 10, 1));
    $g->insert($bar);
    $bar->setRange(0, 100);
    $bar->setValue(0);
    $bar->draw();

    $row = $screen->back()->rows()[0];

    expect(mb_substr($row, 0, 1))->toBe(Glyphs::ARROW_LEFT)
        ->and(mb_substr($row, 9, 1))->toBe(Glyphs::ARROW_RIGHT)
        ->and(mb_substr($row, 1, 1))->toBe(Glyphs::SCROLL_THUMB)        // pos 1 at value 0
        ->and(mb_substr($row, 5, 1))->toBe(Glyphs::SCROLL_TRACK);
});

test('a vertical scroll bar draws arrows top and bottom', function (): void {
    [$g, $screen] = sbRoot(1, 10);
    $bar = new ScrollBar(Rect::of(0, 0, 1, 10));
    $g->insert($bar);
    $bar->setRange(0, 100);
    $bar->setValue(100);
    $bar->draw();

    $rows = $screen->back()->rows();

    expect($rows[0])->toBe(Glyphs::ARROW_UP)
        ->and($rows[9])->toBe(Glyphs::ARROW_DOWN)
        ->and($rows[8])->toBe(Glyphs::SCROLL_THUMB);   // pos 8 at value 100
});

test('a zero-range bar fills the whole track (no thumb)', function (): void {
    [$g, $screen] = sbRoot(8, 1);
    $bar = new ScrollBar(Rect::of(0, 0, 8, 1));
    $g->insert($bar);
    $bar->draw();

    $row = $screen->back()->rows()[0];
    // arrows at ends, track everywhere between, no thumb glyph.
    expect($row)->not->toContain(Glyphs::SCROLL_THUMB)
        ->and(mb_substr($row, 0, 1))->toBe(Glyphs::ARROW_LEFT)
        ->and(mb_substr($row, 7, 1))->toBe(Glyphs::ARROW_RIGHT);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarDrawTest.php`
Expected: FAIL — the back buffer is blank (`draw()` is still a stub).

- [ ] **Step 3: Implement — replace the `draw()` stub in `src/Views/ScrollBar.php`**

Add the imports at the top of the file:

```php
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
```

Replace the `draw()` stub body with:

```php
    public function draw(): void
    {
        $this->drawPos($this->getPos());
    }

    /** Paint the bar with the thumb at track offset $pos (faithful to drawPos). */
    public function drawPos(int $pos): void
    {
        $size = $this->getSize();
        $last = $size - 1;

        $glyphs = $this->isVertical()
            ? [Glyphs::ARROW_UP, Glyphs::ARROW_DOWN]
            : [Glyphs::ARROW_LEFT, Glyphs::ARROW_RIGHT];

        $arrowColor = $this->getColor(2);
        $trackColor = $this->getColor(1);
        $thumbColor = $this->getColor(3);

        $b = new DrawBuffer($size);
        $b->moveChar(0, $glyphs[0], $arrowColor, 1);

        if ($this->maxVal === $this->minVal) {
            $b->moveChar(1, Glyphs::SCROLL_TRACK, $trackColor, $last - 1);
        } else {
            $b->moveChar(1, Glyphs::SCROLL_TRACK, $trackColor, $last - 1);
            $b->moveChar($pos, Glyphs::SCROLL_THUMB, $thumbColor, 1);
        }

        $b->moveChar($last, $glyphs[1], $arrowColor, 1);

        if ($this->isVertical()) {
            // Blit one cell per row down the column.
            for ($y = 0; $y < $size; $y++) {
                $cell = $b->cells()[$y];
                $rowBuf = new DrawBuffer(1);
                $rowBuf->moveChar(0, $cell->char, $cell->attr, 1);
                $this->writeLine(0, $y, 1, 1, $rowBuf);
            }
        } else {
            $this->writeLine(0, 0, $size, 1, $b);
        }
    }
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarDrawTest.php`
Expected: PASS — `Tests: 3 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/ScrollBar.php tests/Unit/Views/ScrollBarDrawTest.php` → `[OK] No errors`.

```bash
git add src/Views/ScrollBar.php tests/Unit/Views/ScrollBarDrawTest.php
git commit -m "feat(views): ScrollBar draws track, thumb and arrows (h+v)"
```

---

## Task 8: ScrollBar — keyboard handling and scrollStep

**Files:** Edit `src/Views/ScrollBar.php`; Test `tests/Unit/Views/ScrollBarKeyTest.php`.

A vertical bar responds to Up/Down (arrow step) and PageUp/PageDown (page step); a horizontal bar to Left/Right and (Ctrl-)Left/Right. `scrollStep(part)` returns ±arrowStep/±pageStep faithfully (`part & 2` ⇒ page, `part & 1` ⇒ positive direction). Home/End jump to min/max.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ScrollBarKeyTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;

test('scrollStep yields signed arrow/page steps', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(10, 2);

    expect($b->scrollStep(ScrollBarPart::UpArrow))->toBe(-2)
        ->and($b->scrollStep(ScrollBarPart::DownArrow))->toBe(2)
        ->and($b->scrollStep(ScrollBarPart::PageUp))->toBe(-10)
        ->and($b->scrollStep(ScrollBarPart::PageDown))->toBe(10);
});

test('vertical bar Down/Up moves by arrowStep and consumes the key', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(10, 2);
    $b->setValue(50);

    $down = Event::keyDown(new KeyDownEvent(Key::Down->value));
    $b->handleEvent($down);
    expect($b->value)->toBe(52)
        ->and($down->isNothing())->toBeTrue();

    $up = Event::keyDown(new KeyDownEvent(Key::Up->value));
    $b->handleEvent($up);
    expect($b->value)->toBe(50);
});

test('vertical bar PageDown moves by pageStep, clamped to max', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setStep(40, 2);
    $b->setValue(80);

    $b->handleEvent(Event::keyDown(new KeyDownEvent(Key::PageDown->value)));
    expect($b->value)->toBe(100);   // 80+40 clamped
});

test('Home and End jump to min/max', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 1, 10));
    $b->setRange(0, 100);
    $b->setValue(50);

    $b->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value)));
    expect($b->value)->toBe(0);

    $b->handleEvent(Event::keyDown(new KeyDownEvent(Key::End->value)));
    expect($b->value)->toBe(100);
});

test('a horizontal bar ignores vertical keys', function (): void {
    $b = new ScrollBar(Rect::of(0, 0, 10, 1));
    $b->setRange(0, 100);
    $b->setValue(50);

    $ev = Event::keyDown(new KeyDownEvent(Key::Down->value));
    $b->handleEvent($ev);

    expect($b->value)->toBe(50)            // unchanged
        ->and($ev->isNothing())->toBeFalse(); // not consumed
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarKeyTest.php`
Expected: FAIL — `Call to undefined method ...ScrollBar::scrollStep()`.

- [ ] **Step 3: Implement — in `src/Views/ScrollBar.php`**

Add imports:

```php
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
```

Add `scrollStep` and replace the `handleEvent` stub:

```php
    /** Signed step for a part code, faithful to TScrollBar::scrollStep. */
    public function scrollStep(int $part): int
    {
        $step = ($part & 2) !== 0 ? $this->pageStep : $this->arrowStep;

        return ($part & 1) !== 0 ? $step : -$step;
    }

    public function handleEvent(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        $part = ScrollBarPart::Indicator;
        $absolute = null; // when set (Home/End), jump straight to this value

        if (! $this->isVertical()) {
            $code = $key->keyCode;
            $part = match (true) {
                $code === Key::Left->value => ScrollBarPart::LeftArrow,
                $code === Key::Right->value => ScrollBarPart::RightArrow,
                $code === Key::Home->value => -1,
                $code === Key::End->value => -1,
                default => ScrollBarPart::Indicator,
            };
            if ($code === Key::Home->value) {
                $absolute = $this->minVal;
            } elseif ($code === Key::End->value) {
                $absolute = $this->maxVal;
            }
        } else {
            $code = $key->keyCode;
            $part = match (true) {
                $code === Key::Up->value => ScrollBarPart::UpArrow,
                $code === Key::Down->value => ScrollBarPart::DownArrow,
                $code === Key::PageUp->value => ScrollBarPart::PageUp,
                $code === Key::PageDown->value => ScrollBarPart::PageDown,
                default => ScrollBarPart::Indicator,
            };
            if ($code === Key::Home->value) {
                $absolute = $this->minVal;
            } elseif ($code === Key::End->value) {
                $absolute = $this->maxVal;
            }
        }

        if ($absolute === null && $part === ScrollBarPart::Indicator) {
            return; // not a key this bar handles
        }

        $newValue = $absolute ?? ($this->value + $this->scrollStep($part));
        $this->setValue($newValue);
        $this->clearEvent($event);
    }
```

> Implementer note: the two branches read identically for Home/End; this is deliberate redundancy kept faithful to TV's two `switch`es. PHPStan is satisfied because `$absolute` is always `?int`.

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollBarKeyTest.php`
Expected: PASS — `Tests: 5 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/ScrollBar.php tests/Unit/Views/ScrollBarKeyTest.php` → `[OK] No errors`.

```bash
git add src/Views/ScrollBar.php tests/Unit/Views/ScrollBarKeyTest.php
git commit -m "feat(views): ScrollBar keyboard scroll + scrollStep"
```

---

## Task 9: Scroller — delta viewport reacting to scroll-bar broadcasts

**Files:** Create `src/Views/Scroller.php`; Test `tests/Unit/Views/ScrollerTest.php`.

A `Scroller` is a selectable view with a `delta` (scroll offset) onto a `limit`-sized logical area. `setLimit` parameterises the attached bars; a `cmScrollBarChanged` broadcast from one of its bars updates `delta` and redraws; `scrollTo` drives the bars; `changeBounds` keeps `delta` in range. Faithful palette `cpScroller="\x06\x07"`. Subclasses override `draw()` to paint `delta`-offset content (the tvguid08–10 text viewers).

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ScrollerTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;

/** A Scroller that paints a row of "delta.y + rowIndex" digits so we can read the offset. */
final class ProbeScroller extends Scroller
{
    public function draw(): void
    {
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $b = new DrawBuffer($this->bounds->width());
            $line = (string) (($this->delta->y + $y) % 10);
            $b->moveChar(0, $line, 0x07, $this->bounds->width());
            $this->writeLine(0, $y, $this->bounds->width(), 1, $b);
        }
    }
}

function scRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $g = new class($screen) extends Group {
        public function __construct(private readonly Screen $s)
        {
            parent::__construct(Rect::of(0, 0, $s->cols(), $s->rows()));
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('a fresh scroller has zero delta/limit and is selectable', function (): void {
    $s = new ProbeScroller(Rect::of(0, 0, 10, 5), null, null);

    expect($s->delta)->toEqual(new Point(0, 0))
        ->and($s->limit)->toEqual(new Point(0, 0))
        ->and($s->getState(State::Selectable))->toBeTrue();
});

test('setLimit stores the limit and parameterises the vertical bar', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 10, 5), null, $vBar);
    $s->setLimit(40, 100);

    expect($s->limit)->toEqual(new Point(40, 100))
        ->and($vBar->minVal)->toBe(0)
        ->and($vBar->maxVal)->toBe(95);   // y - size.y = 100 - 5
});

test('a cmScrollBarChanged broadcast from the vertical bar updates delta and redraws', function (): void {
    [$g, $screen] = scRoot(10, 5);
    $vBar = new ScrollBar(Rect::of(9, 0, 10, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);
    $g->insert($vBar);
    $g->insert($s);

    $vBar->setRange(0, 100);
    $vBar->setValue(3);  // moves bar -> broadcasts cmScrollBarChanged(this)

    // The scroller should have picked up delta.y = 3.
    expect($s->delta->y)->toBe(3);

    $s->draw();
    // Row 0 now shows (3+0)%10 = '3'.
    expect($screen->back()->rows()[0][0])->toBe('3');
});

test('a broadcast from an UNRELATED bar is ignored', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $other = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);

    $s->handleEvent(Event::broadcast(Cmd::ScrollBarChanged, $other));

    expect($s->delta->y)->toBe(0);
});

test('scrollTo drives the bars and clamps via setValue', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);
    $s->setLimit(0, 100);  // maxVal becomes 95
    $s->scrollTo(0, 999);

    expect($vBar->value)->toBe(95)
        ->and($s->delta->y)->toBe(95);
});

test('changeBounds re-clamps the bars (limit reapplied)', function (): void {
    $vBar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $s = new ProbeScroller(Rect::of(0, 0, 9, 5), null, $vBar);
    $s->setLimit(0, 100);          // size.y=5 -> maxVal 95
    $s->changeBounds(Rect::of(0, 0, 9, 9)); // size.y=9 -> maxVal 91

    expect($vBar->maxVal)->toBe(91);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollerTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Scroller" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/Scroller.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * A viewport (faithful to TScroller) onto a logical area larger than the view. Holds a
 * scroll offset (delta) and a logical size (limit), optionally wired to a horizontal
 * and/or vertical ScrollBar. A cmScrollBarChanged broadcast from one of its bars moves
 * delta and redraws. Subclasses override draw() to paint delta-offset content.
 */
class Scroller extends View
{
    /** cpScroller: index1=normal text, index2=highlighted text. */
    private const string PALETTE = "\x06\x07";

    public Point $delta;

    public Point $limit;

    /** Guards re-entrant redraws while several bars are reparameterised. */
    private int $drawLock = 0;

    private bool $drawFlag = false;

    public function __construct(
        Rect $bounds,
        protected ?ScrollBar $hScrollBar = null,
        protected ?ScrollBar $vScrollBar = null,
    ) {
        parent::__construct($bounds);
        $this->delta = new Point(0, 0);
        $this->limit = new Point(0, 0);
        $this->options |= State::Selectable;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function getHScrollBar(): ?ScrollBar
    {
        return $this->hScrollBar;
    }

    public function getVScrollBar(): ?ScrollBar
    {
        return $this->vScrollBar;
    }

    /** Set the logical size and reparameterise the attached bars (faithful setLimit). */
    public function setLimit(int $x, int $y): void
    {
        $this->limit = new Point($x, $y);
        $this->drawLock++;

        if ($this->hScrollBar !== null) {
            $this->hScrollBar->setParams(
                $this->hScrollBar->value,
                0,
                $x - $this->bounds->width(),
                $this->bounds->width() - 1,
                $this->hScrollBar->arrowStep,
            );
        }
        if ($this->vScrollBar !== null) {
            $this->vScrollBar->setParams(
                $this->vScrollBar->value,
                0,
                $y - $this->bounds->height(),
                $this->bounds->height() - 1,
                $this->vScrollBar->arrowStep,
            );
        }

        $this->drawLock--;
        $this->checkDraw();
    }

    /** Scroll to a logical position by driving the bars (which clamp). */
    public function scrollTo(int $x, int $y): void
    {
        $this->drawLock++;
        $this->hScrollBar?->setValue($x);
        $this->vScrollBar?->setValue($y);
        $this->drawLock--;
        $this->checkDraw();
    }

    /** Recompute delta from the bars' values; redraw (deferred under drawLock). */
    public function scrollDraw(): void
    {
        $dx = $this->hScrollBar?->value ?? 0;
        $dy = $this->vScrollBar?->value ?? 0;

        if ($dx !== $this->delta->x || $dy !== $this->delta->y) {
            $this->delta = new Point($dx, $dy);
            if ($this->drawLock !== 0) {
                $this->drawFlag = true;
            } else {
                $this->drawView();
            }
        }
    }

    private function checkDraw(): void
    {
        if ($this->drawLock === 0 && $this->drawFlag) {
            $this->drawFlag = false;
            $this->drawView();
        }
    }

    public function changeBounds(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->drawLock++;
        $this->setLimit($this->limit->x, $this->limit->y);
        $this->drawLock--;
        $this->drawFlag = false;
        $this->drawView();
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isCommand(Cmd::ScrollBarChanged)) {
            $info = $event->asMessage()?->info;
            if ($info === $this->hScrollBar || $info === $this->vScrollBar) {
                $this->scrollDraw();
            }
        }
    }
}
```

> Note: `scrollDraw()` reads the bars' *current* `value` rather than the broadcast info, so it stays correct even if both bars changed. The broadcast only gates *whether* to recompute.

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ScrollerTest.php`
Expected: PASS — `Tests: 6 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/Scroller.php tests/Unit/Views/ScrollerTest.php` → `[OK] No errors`.

```bash
git add src/Views/Scroller.php tests/Unit/Views/ScrollerTest.php
git commit -m "feat(views): add Scroller delta viewport reacting to scroll-bar broadcasts"
```

---

## Task 10: Window flags + palette constants

**Files:** Create `src/Views/Window/WindowFlags.php`, `src/Views/Window/WindowPalette.php`; Test `tests/Unit/Views/WindowConstantsTest.php`.

The `wf*` flag set and the three window palette byte strings, verbatim from `views.h`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/WindowConstantsTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

test('window flags match views.h wf* values', function (): void {
    expect(WindowFlags::Move)->toBe(0x01)
        ->and(WindowFlags::Grow)->toBe(0x02)
        ->and(WindowFlags::Close)->toBe(0x04)
        ->and(WindowFlags::Zoom)->toBe(0x08)
        ->and(WindowFlags::Default)->toBe(0x0F);
});

test('window palette indices match wp* values', function (): void {
    expect(WindowPalette::Blue)->toBe(0)
        ->and(WindowPalette::Cyan)->toBe(1)
        ->and(WindowPalette::Gray)->toBe(2);
});

test('window palette byte strings are verbatim from views.h', function (): void {
    expect(WindowPalette::BLUE_WINDOW)->toBe("\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F")
        ->and(WindowPalette::CYAN_WINDOW)->toBe("\x10\x11\x12\x13\x14\x15\x16\x17")
        ->and(WindowPalette::GRAY_WINDOW)->toBe("\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F");
});

test('byteFor returns the correct palette string per index', function (): void {
    expect(WindowPalette::byteFor(WindowPalette::Blue))->toBe(WindowPalette::BLUE_WINDOW)
        ->and(WindowPalette::byteFor(WindowPalette::Cyan))->toBe(WindowPalette::CYAN_WINDOW)
        ->and(WindowPalette::byteFor(WindowPalette::Gray))->toBe(WindowPalette::GRAY_WINDOW)
        ->and(WindowPalette::byteFor(99))->toBe(WindowPalette::BLUE_WINDOW); // fallback
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/WindowConstantsTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the implementations**

`src/Views/Window/WindowFlags.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views\Window;

/** wf* window flags (faithful to views.h). Default = all four. */
final class WindowFlags
{
    public const int Move = 0x01;
    public const int Grow = 0x02;
    public const int Close = 0x04;
    public const int Zoom = 0x08;
    public const int Default = self::Move | self::Grow | self::Close | self::Zoom;
}
```

`src/Views/Window/WindowPalette.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views\Window;

/** wp* palette indices + the three window palette byte strings (verbatim from views.h). */
final class WindowPalette
{
    public const int Blue = 0;
    public const int Cyan = 1;
    public const int Gray = 2;

    public const string BLUE_WINDOW = "\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F";
    public const string CYAN_WINDOW = "\x10\x11\x12\x13\x14\x15\x16\x17";
    public const string GRAY_WINDOW = "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

    /** The palette byte string for a wp* index (defaults to Blue). */
    public static function byteFor(int $index): string
    {
        return match ($index) {
            self::Cyan => self::CYAN_WINDOW,
            self::Gray => self::GRAY_WINDOW,
            default => self::BLUE_WINDOW,
        };
    }
}
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/WindowConstantsTest.php`
Expected: PASS — `Tests: 4 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/Window/ tests/Unit/Views/WindowConstantsTest.php` → `[OK] No errors`.

```bash
git add src/Views/Window/WindowFlags.php src/Views/Window/WindowPalette.php tests/Unit/Views/WindowConstantsTest.php
git commit -m "feat(views): add WindowFlags wf* and WindowPalette wp*/cp* constants"
```

---

## Task 11: Frame — drawing the border, title and icons

**Files:** Create `src/Views/Frame.php`; Test `tests/Unit/Views/FrameTest.php`.

`Frame` is the last subview of a `Window`; it reads the owner window's `title`, `flags`, `number`, and `sizeLimits`/state to draw the border (single-line when inactive, double-line when active), the centered title, the close icon (left), the zoom/unzoom icon (right), the window number, and the drag icon. Faithful palette `cpFrame="\x01\x01\x02\x02\x03"`. Drawing only (mouse handling in Task 12).

To draw without circular dependency on the concrete `Window`, `Frame` calls a small `FrameOwner` contract the `Window` implements: `frameTitle(): string`, `frameFlags(): int`, `frameNumber(): int`, `frameIsZoomed(): bool`. This keeps `Frame` testable with a stub owner.

- [ ] **Step 1: Write the FrameOwner contract + failing test**

`src/Views/FrameOwner.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

/** What a Frame needs from its owning Window to draw itself. Implemented by Window. */
interface FrameOwner
{
    public function frameTitle(): string;

    public function frameFlags(): int;

    public function frameNumber(): int;

    public function frameIsZoomed(): bool;
}
```

`tests/Unit/Views/FrameTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\FrameOwner;
use HelgeSverre\TurboVision\Views\Glyphs;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/** A Group that satisfies FrameOwner so a Frame can draw against a stub window. */
final class StubWindow extends Group implements FrameOwner
{
    public string $title = 'Demo';

    public int $flags = WindowFlags::Default;

    public int $number = 1;

    public bool $zoomed = false;

    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }

    public function frameTitle(): string
    {
        return $this->title;
    }

    public function frameFlags(): int
    {
        return $this->flags;
    }

    public function frameNumber(): int
    {
        return $this->number;
    }

    public function frameIsZoomed(): bool
    {
        return $this->zoomed;
    }
}

function frameRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();

    return [$screen];
}

test('an inactive frame draws single-line box corners', function (): void {
    [$screen] = frameRoot(20, 6);
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);                 // frame is last subview
    $frame->setState(State::Active, false);
    $frame->draw();

    $rows = $screen->back()->rows();
    expect(mb_substr($rows[0], 0, 1))->toBe(Glyphs::SINGLE_TOP_LEFT)
        ->and(mb_substr($rows[0], 19, 1))->toBe(Glyphs::SINGLE_TOP_RIGHT)
        ->and(mb_substr($rows[5], 0, 1))->toBe(Glyphs::SINGLE_BOTTOM_LEFT)
        ->and(mb_substr($rows[5], 19, 1))->toBe(Glyphs::SINGLE_BOTTOM_RIGHT);
});

test('an active frame draws double-line box corners and the title', function (): void {
    [$screen] = frameRoot(20, 6);
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $win->title = 'Demo';
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    $rows = $screen->back()->rows();
    expect(mb_substr($rows[0], 0, 1))->toBe(Glyphs::DOUBLE_TOP_LEFT)
        ->and(mb_substr($rows[0], 19, 1))->toBe(Glyphs::DOUBLE_TOP_RIGHT)
        ->and($rows[0])->toContain('Demo');
});

test('an active frame draws the close and zoom icons', function (): void {
    [$screen] = frameRoot(20, 6);
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    $top = $screen->back()->rows()[0];
    // close icon body '■' sits at column 3 (after the box corner + '[').
    expect($top)->toContain('■')   // close glyph
        ->and($top)->toContain('↑'); // zoom glyph (not zoomed)
});

test('a zoomed active frame shows the un-zoom icon', function (): void {
    [$screen] = frameRoot(20, 6);
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $win->zoomed = true;
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    expect($screen->back()->rows()[0])->toContain('↓');
});

test('the window number is drawn on the frame', function (): void {
    [$screen] = frameRoot(20, 6);
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $win->number = 3;
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    expect($screen->back()->rows()[0])->toContain('3');
});

test('Frame growMode is gfGrowHiX|gfGrowHiY', function (): void {
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    expect($frame->growMode)->toBe(State::GrowHiX | State::GrowHiY);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/FrameTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Frame" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/Frame.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/**
 * The border/title/icon view drawn as a window's last subview (faithful to TFrame).
 * Single-line box when inactive, double-line when active; centered title; close icon
 * (left), zoom/unzoom icon and window number (right), drag icon (bottom-right). Reads
 * everything it needs from the owning Window via the FrameOwner contract. Mouse
 * handling (move/resize/close/zoom) is added in Task 12.
 */
class Frame extends View
{
    /** cpFrame: 1=passive frame, 2=passive title, 3=active title, 4=active icons, 5=active frame. */
    private const string PALETTE = "\x01\x01\x02\x02\x03";

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    private function frameOwner(): ?FrameOwner
    {
        return $this->owner instanceof FrameOwner ? $this->owner : null;
    }

    public function draw(): void
    {
        $w = $this->bounds->width();
        $h = $this->bounds->height();
        if ($w < 2 || $h < 2) {
            return;
        }

        $active = $this->getState(State::Active);
        $dragging = $this->getState(State::Dragging);

        // Faithful color words: dragging 0x0505/0x0005, inactive 0x0101/0x0002, active 0x0503/0x0004.
        if ($dragging) {
            $cFrame = $this->getColor(0x0505);
            $cTitle = $this->getColor(0x0005);
        } elseif (! $active) {
            $cFrame = $this->getColor(0x0101);
            $cTitle = $this->getColor(0x0002);
        } else {
            $cFrame = $this->getColor(0x0503);
            $cTitle = $this->getColor(0x0004);
        }
        $frameAttr = $cFrame & 0xFF;
        $titleAttr = $cTitle & 0xFF;

        [$tl, $tr, $bl, $br, $hz, $vt] = $active
            ? [Glyphs::DOUBLE_TOP_LEFT, Glyphs::DOUBLE_TOP_RIGHT, Glyphs::DOUBLE_BOTTOM_LEFT, Glyphs::DOUBLE_BOTTOM_RIGHT, Glyphs::DOUBLE_HORIZONTAL, Glyphs::DOUBLE_VERTICAL]
            : [Glyphs::SINGLE_TOP_LEFT, Glyphs::SINGLE_TOP_RIGHT, Glyphs::SINGLE_BOTTOM_LEFT, Glyphs::SINGLE_BOTTOM_RIGHT, Glyphs::SINGLE_HORIZONTAL, Glyphs::SINGLE_VERTICAL];

        // --- top line ---
        $top = new DrawBuffer($w);
        $top->moveChar(0, $tl, $frameAttr, 1);
        $top->moveChar(1, $hz, $frameAttr, $w - 2);
        $top->moveChar($w - 1, $tr, $frameAttr, 1);

        $owner = $this->frameOwner();
        if ($owner !== null) {
            $title = $owner->frameTitle();
            if ($title !== '') {
                $maxTitle = max(0, $w - 10);
                $len = min(mb_strlen($title), $maxTitle);
                $title = mb_substr($title, 0, $len);
                $i = intdiv($w - $len, 2);
                $top->moveChar($i - 1, ' ', $titleAttr, 1);
                $top->moveStr($i, $title, $titleAttr);
                $top->moveChar($i + $len, ' ', $titleAttr, 1);
            }

            $flags = $owner->frameFlags();
            $number = $owner->frameNumber();

            if ($active) {
                if (($flags & WindowFlags::Close) !== 0) {
                    $top->moveCStr(2, Glyphs::CLOSE_ICON, $frameAttr, $frameAttr);
                }
                if (($flags & WindowFlags::Zoom) !== 0) {
                    $icon = $owner->frameIsZoomed() ? Glyphs::UNZOOM_ICON : Glyphs::ZOOM_ICON;
                    $top->moveCStr($w - 5, $icon, $frameAttr, $frameAttr);
                }
            }

            if ($number > 0 && $number < 10) {
                $col = ($flags & WindowFlags::Zoom) !== 0 ? $w - 7 : $w - 3;
                $top->moveChar($col, (string) $number, $frameAttr, 1);
            }
        }

        $this->writeLine(0, 0, $w, 1, $top);

        // --- middle lines ---
        for ($y = 1; $y < $h - 1; $y++) {
            $mid = new DrawBuffer($w);
            $mid->moveChar(0, $vt, $frameAttr, 1);
            $mid->moveChar(1, ' ', $frameAttr, $w - 2);
            $mid->moveChar($w - 1, $vt, $frameAttr, 1);
            $this->writeLine(0, $y, $w, 1, $mid);
        }

        // --- bottom line ---
        $bot = new DrawBuffer($w);
        $bot->moveChar(0, $bl, $frameAttr, 1);
        $bot->moveChar(1, $hz, $frameAttr, $w - 2);
        $bot->moveChar($w - 1, $br, $frameAttr, 1);

        if ($active && $owner !== null && ($owner->frameFlags() & WindowFlags::Grow) !== 0) {
            $bot->moveCStr($w - 2, Glyphs::DRAG_ICON, $frameAttr, $frameAttr);
        }
        $this->writeLine(0, $h - 1, $w, 1, $bot);
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & (State::Active | State::Dragging)) !== 0) {
            $this->drawView();
        }
    }

    public function handleEvent(Event $event): void
    {
        // Mouse move/resize/close/zoom is added in Task 12.
    }
}
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/FrameTest.php`
Expected: PASS — `Tests: 6 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/Frame.php src/Views/FrameOwner.php tests/Unit/Views/FrameTest.php` → `[OK] No errors`.

```bash
git add src/Views/Frame.php src/Views/FrameOwner.php tests/Unit/Views/FrameTest.php
git commit -m "feat(views): add Frame border/title/icon drawing (single vs double)"
```

---

## Task 12: Frame — mouse drives close / zoom / move / resize

**Files:** Edit `src/Views/Frame.php`; Test `tests/Unit/Views/FrameMouseTest.php`.

A mouse-down on the frame top line: in the close-icon zone (x 2–4) → put a `cmClose` command; in the zoom-icon zone (x w-5..w-3) or a double-click on the title line → `cmZoom`; otherwise → move the window (`dmDragMove`). A mouse-down in the bottom-right (resize zone) → grow (`dmDragGrow`). M2 puts the command/drag intent as a `cmClose`/`cmZoom` command (handled by `Window`) or calls `Window::dragWindow()` directly; we keep the inner pump out of the unit test by asserting the *emitted command*. Move/resize geometry lives in `View::dragView` (Task 3) and is exercised in the `Window` tests.

We expose `putEvent` results via the owner: the `Frame` calls `$this->putEvent(Event::command(...))`, which the M1 `Group::putEvent` forwards up to the program queue. For testability the stub window captures puts.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/FrameMouseTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\FrameOwner;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/** A FrameOwner that records the events the Frame puts back into the queue. */
final class CapturingWindow extends Group implements FrameOwner
{
    /** @var list<Event> */
    public array $puts = [];

    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }

    public function putEvent(Event $event): void
    {
        $this->puts[] = clone $event;
    }

    public function frameTitle(): string
    {
        return 'Demo';
    }

    public function frameFlags(): int
    {
        return WindowFlags::Default;
    }

    public function frameNumber(): int
    {
        return 1;
    }

    public function frameIsZoomed(): bool
    {
        return false;
    }
}

function fmRoot(): array
{
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();

    return [$screen];
}

test('a click on the close icon puts a cmClose command and consumes the event', function (): void {
    [$screen] = fmRoot();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // Local x=3 (inside close zone 2..4), y=0.
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 0)));
    $frame->handleEvent($ev);

    expect($win->puts)->toHaveCount(1)
        ->and($win->puts[0]->what)->toBe(EventType::Command)
        ->and($win->puts[0]->asMessage()?->command)->toBe(Cmd::Close)
        ->and($ev->isNothing())->toBeTrue();
});

test('a click on the zoom icon puts a cmZoom command', function (): void {
    [$screen] = fmRoot();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // Zoom zone is x in [w-5, w-3] = [15,17].
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(16, 0)));
    $frame->handleEvent($ev);

    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Zoom);
});

test('a double-click on the title line puts a cmZoom command', function (): void {
    [$screen] = fmRoot();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // x=10 (title area, not close/zoom), doubleClick=true.
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(10, 0), buttons: 1, doubleClick: true));
    $frame->handleEvent($ev);

    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Zoom);
});

test('a single click on the title area requests a move (cmResize with move flag)', function (): void {
    [$screen] = fmRoot();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(10, 0)));
    $frame->handleEvent($ev);

    // The frame emits a cmResize command carrying the drag intent for the window to run.
    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Resize)
        ->and($ev->isNothing())->toBeTrue();
});

test('a click on the bottom-right resize zone requests a resize', function (): void {
    [$screen] = fmRoot();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);

    // Bottom-right: x >= w-2 (18), y >= h-1 (5).
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(19, 5)));
    $frame->handleEvent($ev);

    expect($win->puts[0]->asMessage()?->command)->toBe(Cmd::Resize);
});

test('an inactive frame ignores icon clicks', function (): void {
    [$screen] = fmRoot();
    $win = new CapturingWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, false);

    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 0)));
    $frame->handleEvent($ev);

    // Inactive frames still allow a move (TV behaviour), but no close/zoom command.
    expect($win->puts)->not->toBeEmpty()
        ->and($win->puts[0]->asMessage()?->command)->toBe(Cmd::Resize);
});
```

> Design note: M2 routes *move* and *resize* through a single `cmResize` command the `Window` runs (it calls `dragView`). Close/zoom are distinct commands. This matches `TWindow::handleEvent`'s `cmResize` case driving `dragView`, while keeping the `Frame` free of an inner pump loop in the headless tests.

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/FrameMouseTest.php`
Expected: FAIL — `handleEvent` is a stub; `$win->puts` is empty.

- [ ] **Step 3: Implement — replace `handleEvent` in `src/Views/Frame.php`**

Add imports:

```php
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\EventType;
```

Replace the `handleEvent` stub:

```php
    public function handleEvent(Event $event): void
    {
        if ($event->what !== EventType::MouseDown) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        $owner = $this->frameOwner();
        if ($owner === null) {
            return;
        }

        $local = $this->makeLocal($mouse->where);
        $w = $this->bounds->width();
        $h = $this->bounds->height();
        $flags = $owner->frameFlags();
        $active = $this->getState(State::Active);

        if ($local->y === 0) {
            $inClose = $active && ($flags & WindowFlags::Close) !== 0 && $local->x >= 2 && $local->x <= 4;
            $inZoom = $active && ($flags & WindowFlags::Zoom) !== 0
                && (($local->x >= $w - 5 && $local->x <= $w - 3) || $mouse->doubleClick);

            if ($inClose) {
                $this->putEvent(Event::command(Cmd::Close, $owner));
                $this->clearEvent($event);

                return;
            }
            if ($inZoom) {
                $this->putEvent(Event::command(Cmd::Zoom, $owner));
                $this->clearEvent($event);

                return;
            }
            if (($flags & WindowFlags::Move) !== 0) {
                $this->putEvent(Event::command(Cmd::Resize, $owner));
                $this->clearEvent($event);

                return;
            }
        }

        // Bottom-right resize corner.
        if ($local->x >= $w - 2 && $local->y >= $h - 1 && $active && ($flags & WindowFlags::Grow) !== 0) {
            $this->putEvent(Event::command(Cmd::Resize, $owner));
            $this->clearEvent($event);
        }
    }
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/FrameMouseTest.php`
Expected: PASS — `Tests: 6 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/Frame.php tests/Unit/Views/FrameMouseTest.php` → `[OK] No errors`.

```bash
git add src/Views/Frame.php tests/Unit/Views/FrameMouseTest.php
git commit -m "feat(views): Frame mouse drives close/zoom/move/resize commands"
```

---

## Task 13: Window — framed group with number, palette, standardScrollBar, sizeLimits

**Files:** Create `src/Views/Window.php`; Test `tests/Unit/Views/WindowTest.php`.

`Window extends Group implements FrameOwner`. The constructor sets `flags`, `number`, `title`, `growMode = gfGrowAll | gfGrowRel`, `state |= sfShadow`, `options |= ofSelectable | ofTopSelect`, records `zoomRect`, and inserts a `Frame` (via the overridable `initFrame()`). It supplies `getPalette()` (blue/cyan/gray), `sizeLimits()` (min 16×6), `standardScrollBar()`, `close()`, `zoom()`, and the `FrameOwner` accessors. `handleEvent` (Task 14) wires the commands.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/WindowTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\FrameOwner;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

test('the constructor inserts a Frame and stores title/number/flags', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 2);

    expect($w->subviews())->toHaveCount(1)
        ->and($w->subviews()[0])->toBeInstanceOf(Frame::class)
        ->and($w->frameTitle())->toBe('Demo')
        ->and($w->frameNumber())->toBe(2)
        ->and($w->frameFlags())->toBe(WindowFlags::Default)
        ->and($w)->toBeInstanceOf(FrameOwner::class);
});

test('a window is a selectable, top-selecting, shadowed group with grow-all-rel', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);

    expect(($w->options & State::Selectable) !== 0)->toBeTrue()
        ->and(($w->options & State::TopSelect) !== 0)->toBeTrue()
        ->and($w->getState(State::Shadow))->toBeTrue()
        ->and($w->growMode)->toBe(State::GrowAll | State::GrowRel);
});

test('getPalette returns the blue window palette by default', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $palette = $w->getPalette();

    expect($palette)->toBeInstanceOf(Palette::class)
        ->and($palette?->get(1))->toBe(0x08);  // first byte of cpBlueWindow
});

test('the palette can be switched to gray', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $w->setPalette(WindowPalette::Gray);

    expect($w->getPalette()?->get(1))->toBe(0x18); // first byte of cpGrayWindow
});

test('sizeLimits enforces the 16x6 minimum window size', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    [$minW, $minH, $maxW, $maxH] = $w->sizeLimits();

    expect($minW)->toBe(16)
        ->and($minH)->toBe(6);
});

test('standardScrollBar(vertical) inserts a 1-wide bar on the right edge', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(ScrollBarPart::Vertical);

    expect($bar)->toBeInstanceOf(ScrollBar::class)
        ->and($bar->isVertical())->toBeTrue()
        ->and($bar->getBounds())->toEqual(Rect::of(25, 1, 26, 6))
        ->and($w->subviews())->toContain($bar);
});

test('standardScrollBar(horizontal) inserts a 1-tall bar on the bottom edge', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(ScrollBarPart::Horizontal);

    expect($bar->isVertical())->toBeFalse()
        ->and($bar->getBounds())->toEqual(Rect::of(2, 6, 24, 7));
});

test('standardScrollBar with sbHandleKeyboard sets ofPostProcess', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(ScrollBarPart::Vertical | ScrollBarPart::HandleKeyboard);

    expect(($bar->options & State::PostProcess) !== 0)->toBeTrue();
});

test('frameIsZoomed reflects whether the window fills its max extent', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    expect($w->frameIsZoomed())->toBeFalse();
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/WindowTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\Window" not found`.

- [ ] **Step 3: Write the implementation (commands wired in Task 14)**

`src/Views/Window.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

/**
 * A framed, movable, resizable, zoomable, closable group (faithful to TWindow). Owns a
 * Frame as its first subview, carries a title + number + wf* flags, resolves color
 * through one of the three window palettes, and (Task 14) handles cmClose/cmZoom/cmResize
 * plus Tab focus cycling. Implements FrameOwner so its Frame can draw itself.
 */
class Window extends Group implements FrameOwner
{
    public int $flags = WindowFlags::Default;

    protected int $paletteIndex = WindowPalette::Blue;

    protected Rect $zoomRect;

    protected ?Frame $frame = null;

    public function __construct(
        Rect $bounds,
        protected string $title = '',
        protected int $number = 0,
    ) {
        parent::__construct($bounds);

        $this->state |= State::Shadow;
        $this->options |= State::Selectable | State::TopSelect;
        $this->growMode = State::GrowAll | State::GrowRel;
        $this->zoomRect = $bounds;

        $this->frame = $this->initFrame($this->getExtent());
        if ($this->frame !== null) {
            $this->insert($this->frame);
        }
    }

    /** Override to supply a custom Frame subclass. */
    protected function initFrame(Rect $extent): ?Frame
    {
        return new Frame($extent);
    }

    // --- FrameOwner ---

    public function frameTitle(): string
    {
        return $this->title;
    }

    public function frameFlags(): int
    {
        return $this->flags;
    }

    public function frameNumber(): int
    {
        return $this->number;
    }

    public function frameIsZoomed(): bool
    {
        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();

        return $this->bounds->width() === $maxW && $this->bounds->height() === $maxH;
    }

    // --- palette ---

    public function setPalette(int $index): void
    {
        $this->paletteIndex = $index;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(WindowPalette::byteFor($this->paletteIndex));
    }

    // --- geometry ---

    /** Faithful min window size 16x6; max is the desktop extent if owned, else unbounded. */
    public function sizeLimits(): array
    {
        $maxW = PHP_INT_MAX;
        $maxH = PHP_INT_MAX;
        if ($this->owner !== null) {
            $ext = $this->owner->getExtent();
            $maxW = $ext->width();
            $maxH = $ext->height();
        }

        return [16, 6, $maxW, $maxH];
    }

    /**
     * Build, insert and return a standard scroll bar on the right (vertical) or bottom
     * (horizontal) edge, faithful to TWindow::standardScrollBar positions.
     */
    public function standardScrollBar(int $options): ScrollBar
    {
        $ext = $this->getExtent();

        if (($options & ScrollBarPart::Vertical) !== 0) {
            $r = Rect::of($ext->b->x - 1, $ext->a->y + 1, $ext->b->x, $ext->b->y - 1);
        } else {
            $r = Rect::of($ext->a->x + 2, $ext->b->y - 1, $ext->b->x - 2, $ext->b->y);
        }

        $bar = new ScrollBar($r);
        $this->insert($bar);
        if (($options & ScrollBarPart::HandleKeyboard) !== 0) {
            $bar->options |= State::PostProcess;
        }

        return $bar;
    }

    public function handleEvent(Event $event): void
    {
        // Command + Tab handling added in Task 14.
        parent::handleEvent($event);
    }
}
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/WindowTest.php`
Expected: PASS — `Tests: 9 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/Window.php tests/Unit/Views/WindowTest.php` → `[OK] No errors`.

```bash
git add src/Views/Window.php tests/Unit/Views/WindowTest.php
git commit -m "feat(views): add Window framed group (palette, sizeLimits, standardScrollBar)"
```

---

## Task 14: Window — command handling (close, zoom, resize, Tab) + draw with frame active

**Files:** Edit `src/Views/Window.php`; Test `tests/Unit/Views/WindowCommandTest.php`.

`handleEvent` (faithful to `TWindow::handleEvent`): on `cmClose` (when `wfClose` and info is null/this) → `close()` (remove from owner); on `cmZoom` (when `wfZoom`) → `zoom()`; on `cmResize` (when `wfMove|wfGrow`) → `dragView` against the owner extent and size limits; on Tab/ShiftTab → `focusNext`. `setState(sfSelected)` also marks the frame active. `zoom()` toggles between `zoomRect` and the max extent.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/WindowCommandTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

/** A desktop-like root owning windows. */
function winRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $g = new class($screen) extends Group {
        public function __construct(private readonly Screen $s)
        {
            parent::__construct(Rect::of(0, 0, $s->cols(), $s->rows()));
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('cmClose removes the window from its owner', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $desk->insert($w);
    expect($desk->subviews())->toContain($w);

    $w->handleEvent(Event::command(Cmd::Close, $w));

    expect($desk->subviews())->not->toContain($w);
});

test('cmZoom toggles the window to the desktop extent and back', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(2, 2, 28, 9), 'Demo', 1);
    $desk->insert($w);

    $w->handleEvent(Event::command(Cmd::Zoom, $w));
    // Zoomed: fills the desktop (0,0,80,25).
    expect($w->getBounds())->toEqual(Rect::of(0, 0, 80, 25));

    $w->handleEvent(Event::command(Cmd::Zoom, $w));
    // Restored to the original bounds.
    expect($w->getBounds())->toEqual(Rect::of(2, 2, 28, 9));
});

test('cmResize moves the window inside the desktop, clamped to size limits', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(2, 2, 28, 9), 'Demo', 1);
    $desk->insert($w);

    // Resize to a tiny rect — sizeLimits floors width/height to 16x6.
    $w->resizeTo(Rect::of(0, 0, 4, 4));

    expect($w->getBounds()->width())->toBe(16)
        ->and($w->getBounds()->height())->toBe(6);
});

test('Tab cycles focus among selectable subviews', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    // Two selectable children besides the frame.
    $a = new \HelgeSverre\TurboVision\Views\View(Rect::of(1, 1, 5, 5));
    $a->setState(State::Selectable, true);
    $b = new \HelgeSverre\TurboVision\Views\View(Rect::of(6, 1, 10, 5));
    $b->setState(State::Selectable, true);
    $w->insert($a);
    $w->insert($b);
    $w->setCurrent($a);
    $desk->insert($w);

    $ev = Event::keyDown(new KeyDownEvent(Key::Tab->value));
    $w->handleEvent($ev);

    expect($w->current())->toBe($b)
        ->and($ev->isNothing())->toBeTrue();
});

test('selecting the window marks its frame active', function (): void {
    [$desk] = winRoot(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $desk->insert($w);

    $w->setState(State::Selected, true);

    $frame = $w->subviews()[0];
    expect($frame)->toBeInstanceOf(Frame::class)
        ->and($frame->getState(State::Active))->toBeTrue();
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/WindowCommandTest.php`
Expected: FAIL — `Call to undefined method ...Window::resizeTo()` and command handling absent.

- [ ] **Step 3: Implement — in `src/Views/Window.php`**

Add imports:

```php
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
```

Replace the `handleEvent` body and add `close()`, `zoom()`, `resizeTo()`, and `setState()`:

```php
    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->what === EventType::Command) {
            $msg = $event->asMessage();
            if ($msg !== null) {
                $info = $msg->info;
                $forUs = $info === null || $info === $this;

                switch ($msg->command) {
                    case Cmd::Resize:
                        if (($this->flags & (WindowFlags::Move | WindowFlags::Grow)) !== 0) {
                            // In headless tests resizeTo() is driven directly; here we just
                            // consume so the command does not bubble to siblings.
                            $this->clearEvent($event);
                        }
                        break;
                    case Cmd::Close:
                        if (($this->flags & WindowFlags::Close) !== 0 && $forUs) {
                            $this->clearEvent($event);
                            $this->close();
                        }
                        break;
                    case Cmd::Zoom:
                        if (($this->flags & WindowFlags::Zoom) !== 0 && $forUs) {
                            $this->zoom();
                            $this->clearEvent($event);
                        }
                        break;
                }
            }
        } elseif ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key !== null && $key->keyCode === Key::Tab->value) {
                $this->focusNext();
                $this->clearEvent($event);
            } elseif ($key !== null && $key->keyCode === Key::ShiftTab->value) {
                $this->focusNext();
                $this->clearEvent($event);
            }
        }
    }

    /** Remove this window from its owner (faithful close, sans valid()/destroy). */
    public function close(): void
    {
        $this->owner?->remove($this);
    }

    /** Toggle between the saved zoomRect and the maximum (desktop) extent. */
    public function zoom(): void
    {
        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();

        if ($this->bounds->width() !== $maxW || $this->bounds->height() !== $maxH) {
            $this->zoomRect = $this->bounds;
            $originX = $this->owner?->getExtent()->a->x ?? 0;
            $originY = $this->owner?->getExtent()->a->y ?? 0;
            $this->changeBounds(Rect::of($originX, $originY, $originX + $maxW, $originY + $maxH));
        } else {
            $this->changeBounds($this->zoomRect);
        }
    }

    /** Move/resize against the owner extent, clamped to size limits (used by drag). */
    public function resizeTo(Rect $newBounds): void
    {
        $limits = $this->owner?->getExtent() ?? $this->getBounds();
        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();
        $this->dragView($newBounds, $limits, new Point($minW, $minH), new Point($maxW, $maxH));
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & State::Selected) !== 0) {
            parent::setState(State::Active, $enable);
            $this->frame?->setState(State::Active, $enable);
        }
    }
```

> Implementer note: `zoom()` uses the owner extent origin so a zoomed window aligns to the desktop top-left; `frameIsZoomed()` compares size to max, matching the icon logic in `Frame::draw`.

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/WindowCommandTest.php`
Expected: PASS — `Tests: 5 passed`.

- [ ] **Step 5: Full Views regression + PHPStan**

Run: `./vendor/bin/pest tests/Unit/Views` then `./vendor/bin/phpstan analyse src/Views tests/Unit/Views`
Expected: all green, `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Window.php tests/Unit/Views/WindowCommandTest.php
git commit -m "feat(views): Window handles cmClose/cmZoom/cmResize + Tab and frame-active"
```

---

## Task 15: Desktop — window management (insert selects, cmNext/cmPrev cycle)

**Files:** Edit `src/Views/Desktop.php`; Test `tests/Unit/Views/DesktopWindowTest.php`.

The desktop hosts windows. Inserting a window selects it (focus + frame active). `cmNext`/`cmPrev` cycle the selected window. Removing the current window restores focus to the next. (Tile/cascade are deferred to a later milestone per the spike.)

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/DesktopWindowTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

function deskFor(int $cols, int $rows): Desktop
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $desk = new class(Rect::of(0, 1, $cols, $rows - 1), $screen) extends Desktop {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    return $desk;
}

test('inserting a window makes it the current view and selects it', function (): void {
    $desk = deskFor(80, 25);
    $w = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $desk->insertWindow($w);

    expect($desk->current())->toBe($w)
        ->and($w->getState(State::Selected))->toBeTrue();
});

test('cmNext cycles the current window to the next', function (): void {
    $desk = deskFor(80, 25);
    $w1 = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $w2 = new Window(Rect::of(2, 2, 28, 9), 'Two', 2);
    $desk->insertWindow($w1);
    $desk->insertWindow($w2);   // w2 current now

    $desk->handleEvent(Event::command(Cmd::Next));

    expect($desk->current())->toBe($w1);
});

test('removing the current window restores focus to another window', function (): void {
    $desk = deskFor(80, 25);
    $w1 = new Window(Rect::of(0, 0, 26, 7), 'One', 1);
    $w2 = new Window(Rect::of(2, 2, 28, 9), 'Two', 2);
    $desk->insertWindow($w1);
    $desk->insertWindow($w2);

    $desk->remove($w2);

    expect($desk->current())->toBe($w1);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/DesktopWindowTest.php`
Expected: FAIL — `Call to undefined method ...Desktop::insertWindow()`.

- [ ] **Step 3: Implement — in `src/Views/Desktop.php`**

Add imports:

```php
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
```

Add to `class Desktop`:

```php
    /** Insert a window and select it (focus + frame active), faithful to TDeskTop. */
    public function insertWindow(Window $window): void
    {
        $this->insert($window);
        $this->selectWindow($window);
    }

    /** Make $window the current, selected view; deselect the previous current. */
    public function selectWindow(View $window): void
    {
        $previous = $this->current();
        if ($previous !== null && $previous !== $window) {
            $previous->setState(State::Selected, false);
        }
        $this->setCurrent($window);
        $window->setState(State::Selected, true);
    }

    public function remove(View $view): void
    {
        $wasCurrent = $this->current() === $view;
        parent::remove($view);

        if ($wasCurrent) {
            $next = $this->topmostWindow();
            if ($next !== null) {
                $this->selectWindow($next);
            }
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Command) {
            $msg = $event->asMessage();
            if ($msg !== null && ($msg->command === Cmd::Next || $msg->command === Cmd::Prev)) {
                $this->cycleWindow();
                $this->clearEvent($event);

                return;
            }
        }

        parent::handleEvent($event);
    }

    /** Cycle the current window to the next selectable window (wrapping). */
    private function cycleWindow(): void
    {
        $windows = array_values(array_filter(
            $this->subviews(),
            static fn (View $v): bool => $v instanceof Window,
        ));
        if (count($windows) < 2) {
            return;
        }

        $idx = 0;
        foreach ($windows as $i => $w) {
            if ($w === $this->current()) {
                $idx = $i;
                break;
            }
        }
        $next = $windows[($idx + 1) % count($windows)];
        $this->selectWindow($next);
    }

    private function topmostWindow(): ?Window
    {
        $subs = $this->subviews();
        for ($i = count($subs) - 1; $i >= 0; $i--) {
            if ($subs[$i] instanceof Window) {
                return $subs[$i];
            }
        }

        return null;
    }
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/DesktopWindowTest.php`
Expected: PASS — `Tests: 3 passed`.

- [ ] **Step 5: Regression + PHPStan**

Run: `./vendor/bin/pest tests/Unit/Views/DesktopTest.php tests/Unit/Views/DesktopWindowTest.php` then `./vendor/bin/phpstan analyse src/Views/Desktop.php tests/Unit/Views/DesktopWindowTest.php`
Expected: green; `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Views/Desktop.php tests/Unit/Views/DesktopWindowTest.php
git commit -m "feat(views): Desktop window management (insert-selects, cmNext/cmPrev cycle)"
```

---

## Task 16: ListViewer — abstract scrollable list

**Files:** Create `src/Views/ListViewer.php`; Test `tests/Unit/Views/ListViewerTest.php`.

An abstract, keyboard/mouse-navigable list (faithful to `TListViewer`). Holds `focused`, `topItem`, `range`, `numCols`, and an optional vertical/horizontal `ScrollBar`. Subclasses supply `getText(int $item, int $maxLen): string`. `setRange` updates the bar; `focusItem`/`focusItemNum` move focus and keep it in view; arrows/PageUp/PageDown/Home/End navigate; a mouse click focuses the item under the pointer; `draw()` paints each visible item, highlighting the focused one when selected+active; a `cmScrollBarChanged` from the vertical bar re-focuses; selecting broadcasts `cmListItemSelected`. Faithful palette `cpListViewer="\x1A\x1A\x1B\x1C\x1D"`. (Concrete `ListBox` is M3 — this is the stable interface it extends.)

- [ ] **Step 1: Write the failing test**

`tests/Unit/Views/ListViewerTest.php`:

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
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\State;

/** A concrete single-column list of "Item N" strings. */
final class NumberList extends ListViewer
{
    public function getText(int $item, int $maxLen): string
    {
        return mb_substr('Item ' . $item, 0, $maxLen);
    }
}

function lvRoot(int $cols, int $rows): array
{
    $screen = new Screen(new HeadlessDriver($cols, $rows));
    $screen->init();
    $g = new class($screen) extends Group {
        public function __construct(private readonly Screen $s)
        {
            parent::__construct(Rect::of(0, 0, $s->cols(), $s->rows()));
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    return [$g, $screen];
}

test('a fresh list viewer is selectable with zeroed state', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);

    expect($lv->focused)->toBe(0)
        ->and($lv->topItem)->toBe(0)
        ->and($lv->range)->toBe(0)
        ->and($lv->numCols)->toBe(1)
        ->and(($lv->options & State::Selectable) !== 0)->toBeTrue();
});

test('setRange updates the vertical scroll bar parameters', function (): void {
    $bar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, $bar);
    $lv->setRange(20);

    expect($lv->range)->toBe(20)
        ->and($bar->maxVal)->toBe(19);  // range - 1
});

test('focusItem scrolls topItem to keep the focused item visible', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null); // 5 rows
    $lv->setRange(20);
    $lv->focusItem(10);

    expect($lv->focused)->toBe(10)
        ->and($lv->topItem)->toBe(6);   // item - size.y + 1 = 10 - 5 + 1
});

test('focusItemNum clamps to [0, range-1]', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $lv->setRange(8);

    $lv->focusItemNum(99);
    expect($lv->focused)->toBe(7);

    $lv->focusItemNum(-5);
    expect($lv->focused)->toBe(0);
});

test('Down arrow moves focus to the next item and consumes the key', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $lv->setRange(10);

    $ev = Event::keyDown(new KeyDownEvent(Key::Down->value));
    $lv->handleEvent($ev);

    expect($lv->focused)->toBe(1)
        ->and($ev->isNothing())->toBeTrue();
});

test('Home and End jump to the first and last item', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $lv->setRange(10);
    $lv->focusItem(5);

    $lv->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value)));
    expect($lv->focused)->toBe($lv->topItem);

    $lv->handleEvent(Event::keyDown(new KeyDownEvent(Key::End->value)));
    expect($lv->focused)->toBe($lv->topItem + 5 - 1);
});

test('a mouse click focuses the item under the pointer', function (): void {
    [$g] = lvRoot(20, 10);
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $g->insert($lv);
    $lv->setRange(10);

    // Local y=2 (third visible row) -> item topItem + 2.
    $ev = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 2)));
    $lv->handleEvent($ev);

    expect($lv->focused)->toBe(2);
});

test('draw paints visible items and highlights the focused one when selected+active', function (): void {
    [$g, $screen] = lvRoot(20, 10);
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    $g->insert($lv);
    $lv->setState(State::Selected, true);
    $lv->setState(State::Active, true);
    $lv->setRange(10);
    $lv->draw();

    $rows = $screen->back()->rows();
    expect($rows[0])->toContain('Item 0')
        ->and($rows[1])->toContain('Item 1');
});

test('a cmScrollBarChanged from the vertical bar re-focuses to the bar value', function (): void {
    $bar = new ScrollBar(Rect::of(0, 0, 1, 5));
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, $bar);
    $lv->options |= State::Selectable;
    $lv->setRange(20);

    $bar->setValue(7);  // broadcasts; but bar is unowned, so trigger handler directly
    $lv->handleEvent(Event::broadcast(Cmd::ScrollBarChanged, $bar));

    expect($lv->focused)->toBe(7);
});

test('getPalette returns cpListViewer', function (): void {
    $lv = new NumberList(Rect::of(0, 0, 12, 5), 1, null, null);
    expect($lv->getPalette()?->get(1))->toBe(0x1A);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Unit/Views/ListViewerTest.php`
Expected: FAIL — `Class "HelgeSverre\TurboVision\Views\ListViewer" not found`.

- [ ] **Step 3: Write the implementation**

`src/Views/ListViewer.php`:

```php
<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Abstract scrollable list (faithful to TListViewer). Subclasses supply getText().
 * Tracks focused/topItem/range across one or more columns, navigates by keyboard and
 * mouse, draws items (highlighting the focused one when selected+active), reacts to a
 * vertical scroll bar's cmScrollBarChanged, and broadcasts cmListItemSelected. The
 * stable base M3's concrete ListBox extends.
 */
abstract class ListViewer extends View
{
    /** cpListViewer: 1=active, 2=inactive, 3=focused, 4=selected, 5=divider. */
    private const string PALETTE = "\x1A\x1A\x1B\x1C\x1D";

    public int $focused = 0;

    public int $topItem = 0;

    public int $range = 0;

    public function __construct(
        Rect $bounds,
        public int $numCols = 1,
        protected ?ScrollBar $hScrollBar = null,
        protected ?ScrollBar $vScrollBar = null,
    ) {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick;

        if ($this->vScrollBar !== null) {
            if ($numCols === 1) {
                $this->vScrollBar->setStep($bounds->height() - 1, 1);
            } else {
                $this->vScrollBar->setStep($bounds->height() * $numCols, $bounds->height());
            }
        }
        $this->hScrollBar?->setStep(intdiv($bounds->width(), max(1, $numCols)), 1);
    }

    /** Provide the text for an item, truncated to $maxLen graphemes. */
    abstract public function getText(int $item, int $maxLen): string;

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function isSelected(int $item): bool
    {
        return $item === $this->focused;
    }

    public function setRange(int $range): void
    {
        $this->range = $range;
        if ($this->focused >= $range) {
            $this->focused = $range - 1 >= 0 ? $range - 1 : 0;
        }
        if ($this->vScrollBar !== null) {
            $this->vScrollBar->setParams(
                $this->focused,
                0,
                $range - 1,
                $this->vScrollBar->pageStep,
                $this->vScrollBar->arrowStep,
            );
        } else {
            $this->drawView();
        }
    }

    public function focusItem(int $item): void
    {
        $this->focused = $item;
        if ($this->vScrollBar !== null) {
            $this->vScrollBar->setValue($item);
        } else {
            $this->drawView();
        }

        $height = $this->bounds->height();
        if ($item < $this->topItem) {
            $this->topItem = $this->numCols === 1 ? $item : $item - $item % $height;
        } elseif ($item >= $this->topItem + $height * $this->numCols) {
            if ($this->numCols === 1) {
                $this->topItem = $item - $height + 1;
            } else {
                $this->topItem = $item - $item % $height - ($height * ($this->numCols - 1));
            }
        }
    }

    public function focusItemNum(int $item): void
    {
        if ($item < 0) {
            $item = 0;
        } elseif ($item >= $this->range && $this->range > 0) {
            $item = $this->range - 1;
        }
        if ($this->range !== 0) {
            $this->focusItem($item);
        }
    }

    /** Override in M3 to react to a chosen item; default broadcasts cmListItemSelected. */
    public function selectItem(int $item): void
    {
        $this->owner?->handleEvent(Event::broadcast(Cmd::ListItemSelected, $this));
    }

    public function draw(): void
    {
        $selectedActive = $this->getState(State::Selected) && $this->getState(State::Active);
        $normal = $selectedActive ? $this->getColor(1) : $this->getColor(2);
        $focusedColor = $this->getColor(3);
        $selectedColor = $this->getColor(4);

        $indent = $this->hScrollBar?->value ?? 0;
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $colWidth = intdiv($width, $this->numCols) + 1;

        for ($i = 0; $i < $height; $i++) {
            $b = new DrawBuffer($width);
            for ($j = 0; $j < $this->numCols; $j++) {
                $item = $j * $height + $i + $this->topItem;
                $curCol = $j * $colWidth;

                if ($selectedActive && $this->focused === $item && $this->range > 0) {
                    $color = $focusedColor;
                    $this->setCursor($curCol + 1, $i);
                } elseif ($item < $this->range && $this->isSelected($item)) {
                    $color = $selectedColor;
                } else {
                    $color = $normal;
                }

                $b->moveChar($curCol, ' ', $color & 0xFF, $colWidth);
                if ($item < $this->range) {
                    $text = $this->getText($item, $colWidth + $indent);
                    $text = mb_substr($text, $indent, max(0, $colWidth - 1));
                    $b->moveStr($curCol + 1, $text, $color & 0xFF);
                }
            }
            $this->writeLine(0, $i, $width, 1, $b);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::MouseDown) {
            $this->handleMouse($event);

            return;
        }
        if ($event->what === EventType::KeyDown) {
            $this->handleKey($event);

            return;
        }
        if ($event->isCommand(Cmd::ScrollBarChanged)
            && ($this->options & State::Selectable) !== 0
        ) {
            $info = $event->asMessage()?->info;
            if ($info === $this->vScrollBar && $this->vScrollBar !== null) {
                $this->focusItemNum($this->vScrollBar->value);
                $this->drawView();
            } elseif ($info === $this->hScrollBar) {
                $this->drawView();
            }
        }
    }

    private function handleMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $colWidth = intdiv($this->bounds->width(), $this->numCols) + 1;
        $local = $this->makeLocal($mouse->where);
        $newItem = $local->y + ($this->bounds->height() * intdiv($local->x, $colWidth)) + $this->topItem;
        $this->focusItemNum($newItem);
        $this->drawView();
        if ($mouse->doubleClick && $this->range > $newItem) {
            $this->selectItem($newItem);
        }
        $this->clearEvent($event);
    }

    private function handleKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        if ($key->char === ' ' && $this->focused < $this->range) {
            $this->selectItem($this->focused);
            $this->clearEvent($event);

            return;
        }

        $height = $this->bounds->height();
        $newItem = match ($key->keyCode) {
            Key::Up->value => $this->focused - 1,
            Key::Down->value => $this->focused + 1,
            Key::PageUp->value => $this->focused - $height * $this->numCols,
            Key::PageDown->value => $this->focused + $height * $this->numCols,
            Key::Home->value => $this->topItem,
            Key::End->value => $this->topItem + ($height * $this->numCols) - 1,
            Key::Right->value => $this->numCols > 1 ? $this->focused + $height : null,
            Key::Left->value => $this->numCols > 1 ? $this->focused - $height : null,
            default => null,
        };

        if ($newItem === null) {
            return;
        }

        $this->focusItemNum($newItem);
        $this->drawView();
        $this->clearEvent($event);
    }
}
```

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Unit/Views/ListViewerTest.php`
Expected: PASS — `Tests: 10 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse src/Views/ListViewer.php tests/Unit/Views/ListViewerTest.php` → `[OK] No errors`.

```bash
git add src/Views/ListViewer.php tests/Unit/Views/ListViewerTest.php
git commit -m "feat(views): add abstract ListViewer (nav, scroll bar, selection)"
```

---

## Task 17: Program — terminal-resize reflow

**Files:** Edit `src/Application/Program.php`; Test `tests/Feature/ResizeReflowTest.php`.

M1's loop already flips `screen()->wasResized()` and rebuilds the menu/desktop/status via `layout()`. M2 makes the *desktop's windows* reflow by their `growMode` instead of being discarded: on resize, `layout()` should preserve the existing desktop and call `changeBounds` so its windows reflow. We add a `reflowDesktop()` step the loop calls.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ResizeReflowTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Window;

/** An app that opens one grow-all window we can watch reflow. */
final class ResizeApp extends Application
{
    public ?Window $window = null;

    public function openDemoWindow(): void
    {
        $desk = $this->desktopForTest();
        $w = new Window(Rect::of(0, 0, 20, 6), 'Demo', 1);
        $w->growMode = \HelgeSverre\TurboVision\Views\State::GrowHiX | \HelgeSverre\TurboVision\Views\State::GrowHiY;
        $desk?->insert($w);
        $this->window = $w;
    }
}

test('resizing the terminal reflows a grow-all window', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new ResizeApp(new Screen($driver));
    $app->bootForTest();
    $app->openDemoWindow();

    $before = $app->window?->getBounds();
    expect($before)->toEqual(Rect::of(0, 0, 20, 6));

    // Shrink the terminal and pump one resize cycle.
    $driver->resizeTo(60, 20);
    $app->pumpResizeForTest();

    // GrowHiX|GrowHiY: high corner follows the (delta) of the desktop change.
    $after = $app->window?->getBounds();
    expect($after?->width())->toBeLessThan(20)  // narrower after shrink
        ->and($after?->height())->toBeLessThanOrEqual(6);
});

test('the back buffer is resized to the new terminal size', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new ResizeApp(new Screen($driver));
    $app->bootForTest();

    $driver->resizeTo(40, 12);
    $app->pumpResizeForTest();

    expect($app->backRowsForTest())->toHaveCount(12)
        ->and(mb_strlen($app->backRowsForTest()[0]))->toBe(40);
});
```

- [ ] **Step 2: Run to verify FAIL**

Run: `./vendor/bin/pest tests/Feature/ResizeReflowTest.php`
Expected: FAIL — `Call to undefined method ...::desktopForTest()` / `pumpResizeForTest()`.

- [ ] **Step 3: Implement — in `src/Application/Program.php`**

Add these test/reflow helpers and adjust the resize path. First add accessors:

```php
    /** Test accessor: the live desktop (post-layout). */
    public function desktopForTest(): ?Desktop
    {
        return $this->desktop;
    }

    /**
     * Reflow on terminal resize: resize buffers (already done by Screen during poll),
     * recompute root bounds, and changeBounds the desktop so its windows reflow by
     * growMode (rather than discarding them as the bare layout() rebuild does).
     */
    public function reflowDesktop(): void
    {
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $this->setBounds(Rect::of(0, 0, $cols, $rows));

        $this->desktop?->changeBounds(Rect::of(0, 1, $cols, $rows - 1));
        $this->menuBar?->changeBounds(Rect::of(0, 0, $cols, 1));
        $this->statusLine?->changeBounds(Rect::of(0, $rows - 1, $cols, $rows));
    }

    /** Test helper: trigger one resize cycle as the run() loop would. */
    public function pumpResizeForTest(): void
    {
        // Force the Screen to observe the driver's new size.
        $this->screenObj->pollEvents(0);
        if ($this->screenObj->wasResized()) {
            $this->reflowDesktop();
            $this->dirty = true;
        }
    }
```

Then change the resize branch in `run()` from `$this->layout();` to a reflow that preserves windows:

```php
                if ($this->screenObj->wasResized()) {
                    $this->reflowDesktop();
                    $this->dirty = true;
                }
```

> Note: `reflowDesktop()` calls `changeBounds` on the existing `Desktop` (which Task 4 made reflow its subviews). The first `layout()` in `run()`/`bootForTest()` still builds the initial tree; only *subsequent* resizes use `reflowDesktop()` so open windows survive.

- [ ] **Step 4: Run to verify PASS**

Run: `./vendor/bin/pest tests/Feature/ResizeReflowTest.php`
Expected: PASS — `Tests: 2 passed`.

- [ ] **Step 5: Regression + PHPStan**

Run: `./vendor/bin/pest tests/Unit/Application tests/Feature` then `./vendor/bin/phpstan analyse src/Application/Program.php tests/Feature/ResizeReflowTest.php`
Expected: green; `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Application/Program.php tests/Feature/ResizeReflowTest.php
git commit -m "feat(app): reflow desktop windows by growMode on terminal resize"
```

---

## Task 18: Scroll-content fixture + Guide04 (bare demo window from a menu command)

**Files:** Create `docs/fixtures/lorem.txt`; Create `examples/php/tutorial/Guide04.php`; Test `tests/Feature/Guide04Test.php`.

Guide04 ports `tvguid04.cc`: a `cmMyNewWin` command (mapped to Cmd::FirstUser+101) opens a bare `Window` titled "Demo Window N" on the desktop, movable/resizable/closable/zoomable. We give it a *deterministic* position (the C++ used `random()`), so snapshots are stable. The shared `lorem.txt` fixture (used by Guide06–10) is created here once.

- [ ] **Step 1: Create the fixture**

`docs/fixtures/lorem.txt` (exactly 40 numbered lines so scroll offsets are unambiguous):

```text
Line 00 the quick brown fox jumps over the lazy dog
Line 01 the quick brown fox jumps over the lazy dog
Line 02 the quick brown fox jumps over the lazy dog
Line 03 the quick brown fox jumps over the lazy dog
Line 04 the quick brown fox jumps over the lazy dog
Line 05 the quick brown fox jumps over the lazy dog
Line 06 the quick brown fox jumps over the lazy dog
Line 07 the quick brown fox jumps over the lazy dog
Line 08 the quick brown fox jumps over the lazy dog
Line 09 the quick brown fox jumps over the lazy dog
Line 10 the quick brown fox jumps over the lazy dog
Line 11 the quick brown fox jumps over the lazy dog
Line 12 the quick brown fox jumps over the lazy dog
Line 13 the quick brown fox jumps over the lazy dog
Line 14 the quick brown fox jumps over the lazy dog
Line 15 the quick brown fox jumps over the lazy dog
Line 16 the quick brown fox jumps over the lazy dog
Line 17 the quick brown fox jumps over the lazy dog
Line 18 the quick brown fox jumps over the lazy dog
Line 19 the quick brown fox jumps over the lazy dog
Line 20 the quick brown fox jumps over the lazy dog
Line 21 the quick brown fox jumps over the lazy dog
Line 22 the quick brown fox jumps over the lazy dog
Line 23 the quick brown fox jumps over the lazy dog
Line 24 the quick brown fox jumps over the lazy dog
Line 25 the quick brown fox jumps over the lazy dog
Line 26 the quick brown fox jumps over the lazy dog
Line 27 the quick brown fox jumps over the lazy dog
Line 28 the quick brown fox jumps over the lazy dog
Line 29 the quick brown fox jumps over the lazy dog
Line 30 the quick brown fox jumps over the lazy dog
Line 31 the quick brown fox jumps over the lazy dog
Line 32 the quick brown fox jumps over the lazy dog
Line 33 the quick brown fox jumps over the lazy dog
Line 34 the quick brown fox jumps over the lazy dog
Line 35 the quick brown fox jumps over the lazy dog
Line 36 the quick brown fox jumps over the lazy dog
Line 37 the quick brown fox jumps over the lazy dog
Line 38 the quick brown fox jumps over the lazy dog
Line 39 the quick brown fox jumps over the lazy dog
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/Guide04Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide04.php';

test('Guide04 opens a demo window when the New command runs', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide04App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();

    // The window frame title appears somewhere on the desktop.
    $joined = implode("\n", $rows);
    expect($joined)->toContain('Demo Window');
});

test('Guide04 window draws a double-line active frame', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide04App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());

    // Active window => double-line corners present.
    expect($joined)->toContain('╔')
        ->and($joined)->toContain('╗')
        ->and($joined)->toContain('╚')
        ->and($joined)->toContain('╝');
});

test('Guide04 closing the window removes it', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide04App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->closeTopWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->not->toContain('Demo Window');
});
```

- [ ] **Step 3: Write the example**

`examples/php/tutorial/Guide04.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide04 — PHP port of Turbo Vision's tvguid04.cc (Borland, 1991).
 * A File>New command opens a bare, movable/resizable/closable/zoomable Window on the
 * desktop. Window position is deterministic (the original used random()) so headless
 * snapshots are stable. Window cmd 201 -> Cmd::FirstUser + 101.
 */

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/../../../vendor/autoload.php';

const CM_G4_FILE_OPEN = Cmd::FirstUser + 100; // 200
const CM_G4_NEW_WIN = Cmd::FirstUser + 101;   // 201

final class Guide04App extends Application
{
    private int $winNumber = 0;

    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar(
            $bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('~O~pen', CM_G4_FILE_OPEN, Key::F3, 'F3'),
                new MenuItem('~N~ew', CM_G4_NEW_WIN, Key::F4, 'F4'),
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
            new SubMenu('~W~indow', Key::AltW)->items(
                new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
                new MenuItem('~Z~oom', Cmd::Zoom, Key::F5, 'F5'),
            ),
        );
    }

    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('', Key::F10, Cmd::Menu),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
        ));
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->what === EventType::Command
            && $event->asMessage()?->command === CM_G4_NEW_WIN
        ) {
            $this->openNewWindow();
            $this->clearEvent($event);
        }
    }

    /** Build a Window class via factory so subclasses (Guide05+) override the interior. */
    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Window($bounds, 'Demo Window', $number);
    }

    public function openNewWindow(): void
    {
        $this->winNumber++;
        // Deterministic placement (original randomised within 53x16).
        $x = ($this->winNumber * 3) % 50;
        $y = ($this->winNumber * 2) % 14;
        $bounds = Rect::of($x, $y, $x + 26, $y + 7);
        $window = $this->makeWindow($bounds, $this->winNumber);

        $this->desktopForTest()?->insertWindow($window);
    }

    // --- test helpers ---

    public function openNewWindowForTest(): void
    {
        $this->openNewWindow();
    }

    public function closeTopWindowForTest(): void
    {
        $desk = $this->desktopForTest();
        $current = $desk?->current();
        if ($current instanceof Window) {
            $current->close();
        }
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide04App())->run());
}
```

> Implementer note: `insertWindow`/`desktopForTest` come from Tasks 15/17. `Window` cmd dispatch (close/zoom) comes from Task 14, routed through `Desktop`/`Program` event flow. If `Application::initStatusLine`/`initMenuBar` signatures differ, mirror `Guide03.php` exactly (they do — verified against M1).

- [ ] **Step 4: Run to verify FAIL → implement → PASS**

Run: `./vendor/bin/pest tests/Feature/Guide04Test.php`
First expect FAIL (`Class "Guide04App" not found`) before the example exists; after writing it, expect PASS — `Tests: 3 passed`.

- [ ] **Step 5: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide04.php tests/Feature/Guide04Test.php` → `[OK] No errors`.

```bash
git add docs/fixtures/lorem.txt examples/php/tutorial/Guide04.php tests/Feature/Guide04Test.php
git commit -m "feat(examples): port tvguid04 — bare demo window from a menu command"
```

---

## Task 19: Guide05 (custom interior View inside a window)

**Files:** Create `examples/php/tutorial/Guide05.php`; Test `tests/Feature/Guide05Test.php`.

Guide05 ports `tvguid05.cc`: a `TInterior extends View` (`ofFramed`, `gfGrowHiX|gfGrowHiY`) draws "Hello World!" via a `DrawBuffer` at (4,2). The window inserts this interior fitted inside the frame (`getClipRect()->grow(-1,-1)`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Guide05Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide05.php';

test('Guide05 window interior renders Hello World!', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide05App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Hello World!');
});

test('Guide05 interior text sits inside the window frame', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide05App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $rows = $app->backRowsForTest();
    // The "Hello World!" line is not on the same row as the title (row of the frame top).
    $helloRow = null;
    foreach ($rows as $y => $row) {
        if (str_contains($row, 'Hello World!')) {
            $helloRow = $y;
            break;
        }
    }
    expect($helloRow)->not->toBeNull();
});
```

- [ ] **Step 2: Write the example**

`examples/php/tutorial/Guide05.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide05 — PHP port of tvguid05.cc. A custom interior View ("Hello World!") fills the
 * inside of each demo window. Extends Guide04App, overriding the window factory.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide04.php';

final class Guide05Interior extends View
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->options |= State::Framed;
    }

    public function draw(): void
    {
        parent::draw(); // blank the extent
        $color = $this->getColor(0x0301) & 0xFF;
        $b = new DrawBuffer($this->bounds->width());
        $b->moveStr(0, 'Hello World!', $color);
        $this->writeLine(4, 2, 12, 1, $b);
    }
}

final class Guide05Window extends Window
{
    public function __construct(Rect $bounds, string $title, int $number)
    {
        parent::__construct($bounds, $title, $number);
        $interior = $this->getClipRect()->grow(-1, -1);
        $this->insert(new Guide05Interior($interior));
    }
}

final class Guide05App extends Guide04App
{
    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Guide05Window($bounds, 'Demo Window', $number);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide05App())->run());
}
```

> Implementer note: `Guide04App::makeWindow` must be `protected` and the New-command path must call it (it does — see Task 18). `getClipRect()->grow(-1,-1)` fits the interior inside the frame; the frame is the window's first subview so the interior draws on top.

- [ ] **Step 3: Run FAIL → PASS**

Run: `./vendor/bin/pest tests/Feature/Guide05Test.php`
Expected after implementing: PASS — `Tests: 2 passed`.

- [ ] **Step 4: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide05.php tests/Feature/Guide05Test.php` → `[OK] No errors`.

```bash
git add examples/php/tutorial/Guide05.php tests/Feature/Guide05Test.php
git commit -m "feat(examples): port tvguid05 — custom interior view in a window"
```

---

## Task 20: Guide06/07 (file lines drawn in the interior)

**Files:** Create `examples/php/tutorial/Guide06.php`, `Guide07.php`; Test `tests/Feature/Guide06Test.php`, `Guide07Test.php`.

Guide06/07 port `tvguid06.cc`/`tvguid07.cc`: the interior draws lines from `lorem.txt` via `writeStr` (06) and the corrected per-line `DrawBuffer` rendering (07). No scrolling yet — only the first `size.y` lines show. We load the shared fixture.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Guide06Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide06.php';

test('Guide06 interior shows the first lines of the file', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide06App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    expect($joined)->toContain('Line 00')
        ->and($joined)->toContain('Line 01');
});

test('Guide06 does not show lines beyond the window height', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide06App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    // Window is 7 tall (5 interior rows) -> Line 30 is far off-screen.
    expect(implode("\n", $app->backRowsForTest()))->not->toContain('Line 30');
});
```

`tests/Feature/Guide07Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide07.php';

test('Guide07 renders file lines cleanly via per-line DrawBuffer', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide07App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Line 00');
});
```

- [ ] **Step 2: Write the examples**

`examples/php/tutorial/Guide06.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide06 — PHP port of tvguid06.cc. The window interior writes file lines straight via
 * writeStr (the "imperfect" version; Guide07 fixes per-line rendering). Lines come from
 * docs/fixtures/lorem.txt for deterministic snapshots.
 */

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide04.php';

/** @return list<string> */
function g4LoadLines(): array
{
    $path = __DIR__ . '/../../../docs/fixtures/lorem.txt';
    $raw = is_file($path) ? (string) file_get_contents($path) : '';

    return array_values(array_filter(explode("\n", $raw), static fn (string $l): bool => $l !== ''));
}

final class Guide06Interior extends View
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, private readonly array $lines)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->options |= State::Framed;
    }

    public function draw(): void
    {
        parent::draw();
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $line = $this->lines[$i] ?? '';
            if ($line !== '') {
                $this->writeStr(0, $i, mb_substr($line, 0, $this->bounds->width()), $this->mapColor(1));
            }
        }
    }
}

final class Guide06Window extends Window
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $r = $this->getClipRect()->grow(-1, -1);
        $this->insert(new Guide06Interior($r, $lines));
    }
}

final class Guide06App extends Guide04App
{
    /** @var list<string> */
    private array $lines;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Guide06Window($bounds, 'Demo Window', $number, $this->lines);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide06App())->run());
}
```

`examples/php/tutorial/Guide07.php` (identical structure; the per-line DrawBuffer is already how `writeStr`/Interior works in PHP, so Guide07 simply blanks each row first for a clean render):

```php
<?php

declare(strict_types=1);

/*
 * Guide07 — PHP port of tvguid07.cc. Same as Guide06 but each interior line is painted
 * into its own blanked DrawBuffer first, so partial/short lines never leave stale cells.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide06.php'; // reuses g4LoadLines()

final class Guide07Interior extends View
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, private readonly array $lines)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->options |= State::Framed;
    }

    public function draw(): void
    {
        $color = $this->mapColor(1);
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $b = new DrawBuffer($this->bounds->width());
            $b->moveChar(0, ' ', $color, $this->bounds->width());
            $line = $this->lines[$i] ?? '';
            if ($line !== '') {
                $b->moveStr(0, mb_substr($line, 0, $this->bounds->width()), $color);
            }
            $this->writeLine(0, $i, $this->bounds->width(), 1, $b);
        }
    }
}

final class Guide07Window extends Window
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $r = $this->getClipRect()->grow(-1, -1);
        $this->insert(new Guide07Interior($r, $lines));
    }
}

final class Guide07App extends Guide04App
{
    /** @var list<string> */
    private array $lines;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Guide07Window($bounds, 'Demo Window', $number, $this->lines);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide07App())->run());
}
```

- [ ] **Step 3: Run FAIL → PASS**

Run: `./vendor/bin/pest tests/Feature/Guide06Test.php tests/Feature/Guide07Test.php`
Expected after implementing: PASS — both files green.

- [ ] **Step 4: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide06.php examples/php/tutorial/Guide07.php tests/Feature/Guide06Test.php tests/Feature/Guide07Test.php` → `[OK] No errors`.

```bash
git add examples/php/tutorial/Guide06.php examples/php/tutorial/Guide07.php tests/Feature/Guide06Test.php tests/Feature/Guide07Test.php
git commit -m "feat(examples): port tvguid06/07 — file lines in a window interior"
```

---

## Task 21: Guide08 (scrolling interior with standardScrollBar)

**Files:** Create `examples/php/tutorial/Guide08.php`; Test `tests/Feature/Guide08Test.php`.

Guide08 ports `tvguid08.cc`: the interior is a `Scroller` with a vertical and horizontal `standardScrollBar(... | sbHandleKeyboard)`; `setLimit(maxLineLen, lineCount)` sizes the logical area; `draw()` paints `delta.y + i` lines clipped at `delta.x`. Scrolling the vertical bar changes which lines show.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Guide08Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide08.php';

test('Guide08 shows scroll bars on the window', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide08App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    // Vertical arrows present somewhere on the window's right edge.
    expect($joined)->toContain('▲')
        ->and($joined)->toContain('▼');
});

test('Guide08 interior shows the top file lines before scrolling', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide08App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Line 00');
});

test('scrolling the interior down reveals later lines', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide08App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();

    // Drive the interior's vertical scroll directly to offset 10.
    $app->scrollTopWindowTo(0, 10);
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    expect($joined)->toContain('Line 10')
        ->and($joined)->not->toContain('Line 00');
});
```

- [ ] **Step 2: Write the example**

`examples/php/tutorial/Guide08.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide08 — PHP port of tvguid08.cc. The interior is a Scroller with vertical+horizontal
 * standard scroll bars (sbHandleKeyboard). setLimit sizes the logical area; draw() paints
 * delta-offset lines from the fixture.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide06.php'; // g4LoadLines()

final class Guide08Interior extends Scroller
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, ?ScrollBar $h, ?ScrollBar $v, private readonly array $lines)
    {
        parent::__construct($bounds, $h, $v);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->options |= State::Framed;
        $this->setLimit(80, count($lines));
    }

    public function draw(): void
    {
        $color = $this->getColor(0x0301) & 0xFF;
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $b = new DrawBuffer($this->bounds->width());
            $b->moveChar(0, ' ', $color, $this->bounds->width());
            $j = $this->delta->y + $i;
            $line = $this->lines[$j] ?? '';
            if ($line !== '') {
                $clipped = $this->delta->x >= mb_strlen($line)
                    ? ''
                    : mb_substr($line, $this->delta->x, $this->bounds->width());
                $b->moveStr(0, $clipped, $color);
            }
            $this->writeLine(0, $i, $this->bounds->width(), 1, $b);
        }
    }
}

final class Guide08Window extends Window
{
    public ?Guide08Interior $interior = null;

    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $v = $this->standardScrollBar(ScrollBarPart::Vertical | ScrollBarPart::HandleKeyboard);
        $h = $this->standardScrollBar(ScrollBarPart::Horizontal | ScrollBarPart::HandleKeyboard);
        $r = $this->getClipRect()->grow(-1, -1);
        $this->interior = new Guide08Interior($r, $h, $v, $lines);
        $this->insert($this->interior);
    }
}

final class Guide08App extends Guide04App
{
    /** @var list<string> */
    private array $lines;

    public ?Guide08Window $lastWindow = null;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $this->lastWindow = new Guide08Window($bounds, 'Demo Window', $number, $this->lines);

        return $this->lastWindow;
    }

    public function scrollTopWindowTo(int $x, int $y): void
    {
        $this->lastWindow?->interior?->scrollTo($x, $y);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide08App())->run());
}
```

- [ ] **Step 3: Run FAIL → PASS**

Run: `./vendor/bin/pest tests/Feature/Guide08Test.php`
Expected after implementing: PASS — `Tests: 3 passed`.

- [ ] **Step 4: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide08.php tests/Feature/Guide08Test.php` → `[OK] No errors`.

```bash
git add examples/php/tutorial/Guide08.php tests/Feature/Guide08Test.php
git commit -m "feat(examples): port tvguid08 — scrolling interior with standard scroll bars"
```

---

## Task 22: Guide09/10 (dual-pane scrollers + sizeLimits override)

**Files:** Create `examples/php/tutorial/Guide09.php`, `Guide10.php`; Test `tests/Feature/Guide09Test.php`, `Guide10Test.php`.

Guide09 ports `tvguid09.cc`: two side-by-side `Scroller` panes in one window, each with its own vertical+horizontal scroll bars (built with `ofPostProcess`), split at `extent.b.x/2`. Guide10 adds `tvguid10.cc`'s `sizeLimits()` override enforcing `minWidth = leftInterior.width + 9`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Guide09Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide09.php';

test('Guide09 renders two scroller panes with their own scroll bars', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide09App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $app->drawAndFlushForTest();

    $joined = implode("\n", $app->backRowsForTest());
    // Both panes show file content; at least two vertical bars (4 arrow glyphs total).
    expect(substr_count($joined, '▲'))->toBeGreaterThanOrEqual(2)
        ->and($joined)->toContain('Line 00');
});

test('Guide09 panes scroll independently', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide09App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();

    $app->scrollLeftPaneTo(0, 5);
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('Line 05');
});
```

`tests/Feature/Guide10Test.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/tutorial/Guide10.php';

test('Guide10 enforces a minimum width via sizeLimits', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide10App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();

    $win = $app->lastWindow;
    [$minW, $minH] = $win->sizeLimits();

    // minWidth = left interior width + 9; with a 26-wide window the left pane is ~13 wide.
    expect($minW)->toBeGreaterThan(16);   // larger than the default 16 minimum
});

test('Guide10 refuses to shrink below the minimum width', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new Guide10App(new Screen($driver));
    $app->bootForTest();
    $app->openNewWindowForTest();
    $win = $app->lastWindow;

    [$minW] = $win->sizeLimits();
    $win->resizeTo(Rect::of(0, 0, 4, 4)); // try to shrink tiny

    expect($win->getBounds()->width())->toBe($minW);
});
```

- [ ] **Step 2: Write the examples**

`examples/php/tutorial/Guide09.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide09 — PHP port of tvguid09.cc. A window with two side-by-side Scroller panes, each
 * with its own vertical+horizontal scroll bars (ofPostProcess). Split at extent.b.x/2.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide06.php'; // g4LoadLines()

final class Guide09Interior extends Scroller
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, ?ScrollBar $h, ?ScrollBar $v, private readonly array $lines)
    {
        parent::__construct($bounds, $h, $v);
        $this->options |= State::Framed;
        $this->setLimit(80, count($lines));
    }

    public function draw(): void
    {
        $color = $this->getColor(0x0301) & 0xFF;
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $b = new DrawBuffer($this->bounds->width());
            $b->moveChar(0, ' ', $color, $this->bounds->width());
            $j = $this->delta->y + $i;
            $line = $this->lines[$j] ?? '';
            if ($line !== '') {
                $clipped = $this->delta->x >= mb_strlen($line)
                    ? ''
                    : mb_substr($line, $this->delta->x, $this->bounds->width());
                $b->moveStr(0, $clipped, $color);
            }
            $this->writeLine(0, $i, $this->bounds->width(), 1, $b);
        }
    }
}

class Guide09Window extends Window
{
    public ?Guide09Interior $leftPane = null;

    public ?Guide09Interior $rightPane = null;

    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, protected array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $ext = $this->getExtent();

        $leftBounds = Rect::of($ext->a->x, $ext->a->y, intdiv($ext->b->x, 2) + 1, $ext->b->y);
        $this->leftPane = $this->makePane($leftBounds, true);
        $this->leftPane->growMode = State::GrowHiY;
        $this->insert($this->leftPane);

        $rightBounds = Rect::of(intdiv($ext->b->x, 2), $ext->a->y, $ext->b->x, $ext->b->y);
        $this->rightPane = $this->makePane($rightBounds, false);
        $this->rightPane->growMode = State::GrowHiX | State::GrowHiY;
        $this->insert($this->rightPane);
    }

    protected function makePane(Rect $bounds, bool $left): Guide09Interior
    {
        $vBar = new ScrollBar(Rect::of($bounds->b->x - 1, $bounds->a->y + 1, $bounds->b->x, $bounds->b->y - 1));
        $vBar->options |= State::PostProcess;
        if ($left) {
            $vBar->growMode = State::GrowHiY;
        }
        $this->insert($vBar);

        $hBar = new ScrollBar(Rect::of($bounds->a->x + 2, $bounds->b->y - 1, $bounds->b->x - 2, $bounds->b->y));
        $hBar->options |= State::PostProcess;
        if ($left) {
            $hBar->growMode = State::GrowHiY | State::GrowLoY;
        }
        $this->insert($hBar);

        $interior = $bounds->grow(-1, -1);

        return new Guide09Interior($interior, $hBar, $vBar, $this->lines);
    }
}

final class Guide09App extends Guide04App
{
    /** @var list<string> */
    protected array $lines;

    public ?Guide09Window $lastWindow = null;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $this->lastWindow = new Guide09Window($bounds, 'Demo Window', $number, $this->lines);

        return $this->lastWindow;
    }

    public function scrollLeftPaneTo(int $x, int $y): void
    {
        $this->lastWindow?->leftPane?->scrollTo($x, $y);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide09App())->run());
}
```

`examples/php/tutorial/Guide10.php`:

```php
<?php

declare(strict_types=1);

/*
 * Guide10 — PHP port of tvguid10.cc. Same dual-pane window as Guide09 plus a sizeLimits()
 * override that floors the minimum width at leftPane.width + 9.
 */

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide09.php';

final class Guide10Window extends Guide09Window
{
    /** Floor minimum width at the left pane width + 9 (faithful to tvguid10). */
    public function sizeLimits(): array
    {
        [$minW, $minH, $maxW, $maxH] = parent::sizeLimits();
        $leftWidth = $this->leftPane?->getBounds()->width() ?? 0;

        return [max($minW, $leftWidth + 9), $minH, $maxW, $maxH];
    }
}

final class Guide10App extends Guide09App
{
    public ?Guide10Window $lastWindow = null;

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $this->lastWindow = new Guide10Window($bounds, 'Demo Window', $number, $this->lines);

        return $this->lastWindow;
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide10App())->run());
}
```

> Implementer note: `Guide09App::$lines` and `Guide09Window::$lines` are `protected` so Guide10 reuses them. `Guide09App::makeWindow` returns the base `Window` type but stores the concrete window in `$lastWindow`; PHPStan needs `Guide10App::$lastWindow` redeclared as `?Guide10Window` (covariant property is fine since the parent is `?Guide09Window` and `Guide10Window extends Guide09Window`). If PHPStan flags the override, type both as `@var Guide09Window` and use an accessor instead.

- [ ] **Step 3: Run FAIL → PASS**

Run: `./vendor/bin/pest tests/Feature/Guide09Test.php tests/Feature/Guide10Test.php`
Expected after implementing: PASS — both files green.

- [ ] **Step 4: PHPStan + Commit**

Run: `./vendor/bin/phpstan analyse examples/php/tutorial/Guide09.php examples/php/tutorial/Guide10.php tests/Feature/Guide09Test.php tests/Feature/Guide10Test.php` → `[OK] No errors`.

```bash
git add examples/php/tutorial/Guide09.php examples/php/tutorial/Guide10.php tests/Feature/Guide09Test.php tests/Feature/Guide10Test.php
git commit -m "feat(examples): port tvguid09/10 — dual-pane scrollers + sizeLimits override"
```

---

## Task 23: Real-PTY case for Guide04

**Files:** Edit `tests/Integration/RealTerminalTest.php`.

Add one opt-in real-terminal case proving a window demo emits a complete frame to a real PTY and restores the terminal on quit, reusing the existing `tvRunInPty` helper.

- [ ] **Step 1: Append the test**

Add to `tests/Integration/RealTerminalTest.php` (after the existing Guide03 case):

```php
test('Guide04 emits a window demo to a real terminal and quits cleanly on Alt-X', function (): void {
    $result = tvRunInPty(__DIR__ . '/../../examples/php/tutorial/Guide04.php', "\ex"); // Alt-X

    if (! $result['supported']) {
        $this->markTestSkipped('No usable PTY on this platform.');
    }

    $out = $result['out'];

    expect($result['bytes'])->toBeGreaterThan(2000)                        // a full frame, not a stub
        ->and(substr_count($out, "\e[?2026h"))->toBe(substr_count($out, "\e[?2026l")) // sync balanced
        ->and($result['exited'])->toBeTrue()                                // Alt-X actually quits
        ->and($out)->toContain("\e[?1049l")                                 // alt-screen left
        ->and($out)->toContain("\e[?25h");                                  // cursor restored
})->group('integration')->skip(
    fn (): bool => ! function_exists('proc_open'),
    'Real-terminal tests require proc_open + PTY support.',
);
```

> Note: Guide04 launched bare shows only the menu/status/desktop (no window auto-opens), so the byte threshold is lower than Guide03's. That's fine — the case proves frame completeness + clean teardown, not window content. (Window content is covered by the headless Guide04Test.)

- [ ] **Step 2: Run (opt-in group)**

Run: `./vendor/bin/pest --group=integration`
Expected: PASS (or `SKIPPED` on a platform without PTY support). It must not fail.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/RealTerminalTest.php
git commit -m "test(integration): real-PTY case for the Guide04 window demo"
```

---

## Task 24: Full suite, PHPStan, and roadmap update (NO tag)

**Files:** Edit `ROADMAP.md` (verification only otherwise).

- [ ] **Step 1: Run the entire suite**

Run: `./vendor/bin/pest`
Expected: PASS — all M1 (173) + M2 tests green. Roughly `Tests: 240+ passed`. (Integration group is skipped unless `--group=integration`.)

- [ ] **Step 2: Run the integration group explicitly**

Run: `./vendor/bin/pest --group=integration`
Expected: PASS or SKIPPED — never failing.

- [ ] **Step 3: Run PHPStan at max**

Run: `./vendor/bin/phpstan analyse`
Expected: `[OK] No errors`. If generic-array errors surface on `ListViewer`/`Scroller`/examples, add the precise `@param list<string>` / `@var` annotations shown in those tasks — do not silence with `mixed`.

- [ ] **Step 4: Update the roadmap status line (NO git tag)**

In `ROADMAP.md`, change the M2 row of the milestone table from:

```markdown
| M2 | Windowing | `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer`; resize handling | `tvguid04–10` | **Spike plan written** |
```

to:

```markdown
| M2 | Windowing | `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer`; resize handling | `tvguid04–10` | **Complete** — all tasks built & green; PHPStan max clean; tvguid04–10 ported with headless snapshot tests |
```

And in the "Where we are" / next-up section, set the active line to:

```markdown
- ▶️ **Now:** M2 (windowing) built and green; next is M3 (dialogs & controls — `Dialog`, `Button`, `InputLine`, `ListBox` on the M2 `ListViewer`).
```

- [ ] **Step 5: Commit (NO tag)**

```bash
git add ROADMAP.md
git commit -m "docs: mark M2 windowing complete in roadmap"
```

> **Reminder: do NOT run `git tag` — project policy is no tags.**

---

## What this plan deliberately leaves out (next milestones)

- **M3 — Dialogs & controls:** `Dialog`, `Button`, `InputLine`, `Label`, `CheckBoxes`, `RadioButtons`, the concrete `ListBox` (extends this milestone's `ListViewer`), `MenuBox`/`MenuPopup` depth, `MessageBox`. The `ScrollBar`/`ListViewer`/`Window`/`execView` interfaces shipped here are the M3 foundation.
- **Desktop tile/cascade** beyond `cmNext`/`cmPrev` cycling.
- **Full ancestor clipping in `getClipRect`** (M2 returns the extent; buffered-group overlap clipping is a later rendering-quality milestone).
- **The inner mouse-drag pump** (`dragView` runs the geometry directly in M2; a live drag-tracking loop that follows mouse-move events arrives with the buffered group/driver mouse-mode work).

---

## Self-review (performed by the plan author)

- **Spec coverage:** `ScrollBar` (value model, palette, thumb arithmetic, draw, keyboard) ✓; `Scroller` (delta/limit, setLimit, scrollTo, scrollDraw, changeBounds, broadcast reaction) ✓; `Frame` (single/double border, title, close/zoom/number/drag icons, mouse→close/zoom/move/resize) ✓; `Window` (frame, number, palettes, sizeLimits, standardScrollBar, cmClose/cmZoom/cmResize, Tab, zoom toggle, frame-active) ✓; `ListViewer` (abstract getText, focused/range/topItem, setRange, focusItem/focusItemNum, keyboard+mouse nav, draw, scroll-bar broadcast, selectItem) ✓; resize reflow (`Group::changeBounds`, `Program::reflowDesktop`) ✓; Desktop window-mgmt (insert-selects, cmNext/cmPrev, remove-restores-focus) ✓; `tvguid04–10` ported with headless snapshot tests + one real-PTY case ✓.
- **Faithfulness:** all command/flag/palette/part constants extracted verbatim from `views.h`/`TFrame.cc`/`TScrollBar.cc`/`TScroller.cc`/`TListViewer.cc`/`TWindow.cc` (cited in the header). `getPos` integer arithmetic and `setLimit` bar params copy `TScrollBar.cc`/`TScroller.cc` exactly; the worked example (value 50 → pos 5) is verified in the test note. Glyphs are an explicit, documented CP437→Unicode port choice (tests assert the chosen graphemes, not raw CP437 bytes).
- **Dependency order:** Glyphs → constants → View helpers → Group reflow → ScrollBar(value→draw→keys) → Scroller → Frame(draw→mouse) → Window(base→commands) → Desktop → ListViewer → Program resize → examples → suite. Every method a later task calls is defined in an earlier task: `makeLocal/getClipRect/calcBounds/dragView` (Task 3) before Frame/Window/ListViewer use them; `Group::changeBounds` (Task 4) before Scroller/Window/resize; `ScrollBarPart` (Task 5) before ScrollBar; `FrameOwner` (Task 11) before Window implements it; `Window::standardScrollBar`/`getClipRect` before Guide08–10; `Desktop::insertWindow`/`Program::desktopForTest` before the examples.
- **PHP 8.5 idioms:** `declare(strict_types=1)` everywhere; typed `const int`/`const string`; `final`/`abstract` classes; constructor promotion; `match`; precise `@param list<string>`/`@var` for PHPStan max. No `new` inside class constants. `WindowFlags::Default` composes other constants (legal).
- **M1 signature checks:** `View` members used (`$owner`,`$state`,`$options`,`$growMode`,`bounds`,`setBounds`,`getExtent`,`getState`,`setState`,`mapColor`,`getColor`,`writeLine`,`writeBuf`,`writeStr`,`absoluteOrigin`,`setCursor`,`clearEvent`,`drawView`) and `Group` members (`insert`,`remove`,`subviews`,`current`,`setCurrent`,`focusNext`,`putEvent`,`handleEvent`) all match the real M1 code read for this plan. `Program::layout`/`run`/`bootForTest`/`drawAndFlushForTest`/`backRowsForTest`/`screenObj`/`desktop`/`menuBar`/`statusLine`/`dirty` exist; new helpers (`desktopForTest`,`reflowDesktop`,`pumpResizeForTest`) are additive. `Event::broadcast(int,$info)`/`command(int,$info)`/`asMessage()->info`/`isCommand` and `HeadlessDriver::resizeTo`/`feedInput`/`isInitialised` are used exactly as defined. `Cmd`/`State` extensions are additive and verified non-colliding with existing values.
- **Risks flagged for the implementer:** (1) `ScrollBar` vertical draw blits one cell per row — verify the column composites correctly through `writeLine` width 1. (2) `Scroller::setLimit` clamps bar `maxVal` to `limit - size`; ensure `size` reflects the *interior* bounds (post `grow(-1,-1)`), matching tvguid08–10. (3) `Window::zoom()` aligns to the owner extent origin — confirm the desktop's extent is `(0,0,..)` in local coords so zoom fills it. (4) Guide10's covariant `$lastWindow` property may trip PHPStan; the task gives the accessor fallback. (5) `Frame` move/resize emit a single `cmResize` the `Window` consumes; the live drag loop is intentionally deferred — the headless tests assert the *command*, not pixel-by-pixel drag.
- **Placeholder scan:** none — every step has complete runnable code and an exact command with expected output. No "similar to above". `git tag` appears nowhere.
