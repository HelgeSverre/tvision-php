# M2 Windowing — Spike Plan (outline)

> Spike-level plan: scope, classes, task outline, acceptance, risks. NOT full TDD code.
> Promote to a detailed plan (like the M1 plans) right before building, once M1's View/Group exist.

**Milestone goal:** Add full windowing support — framed, movable, resizable, closable/zoomable windows; horizontal and vertical scroll bars; a scroller viewport onto a larger logical area; and an abstract list viewer — so that the C++ tutorial programs `tvguid04`–`tvguid10` can be faithfully reproduced in PHP. Also wire `SIGWINCH` terminal-resize handling into the existing event loop so windows and their contents reflow live.

**Depends on:** `Geometry\{Point, Rect}`, `Drawing\{Cell, Buffer, DrawBuffer, Palette}`, `Events\{Event, EventType, Key, Cmd}`, `Views\{View, Group, Desktop}`, `Application\{Program, Application}` (all from M1).

**Acceptance examples:**
- `examples/php/tutorial/tvguid04.php` — bare `Window` inserted into desktop; move/resize/close/zoom via keyboard and mouse.
- `examples/php/tutorial/tvguid05.php` — custom `View` subclass inside a window (`ofFramed`, `gfGrowHiX|gfGrowHiY`, draws "Hello World!" via `DrawBuffer`).
- `examples/php/tutorial/tvguid06.php` — interior draws file lines via `writeStr`; exposed clip rect used in `draw()`.
- `examples/php/tutorial/tvguid07.php` — same as 06 with correct `DrawBuffer`-per-line rendering.
- `examples/php/tutorial/tvguid08.php` — `Scroller` interior with horizontal + vertical `ScrollBar` via `standardScrollBar()`; `setLimit()` and `delta` used in `draw()`.
- `examples/php/tutorial/tvguid09.php` — two side-by-side `Scroller` panes in one window, each with its own scroll bars.
- `examples/php/tutorial/tvguid10.php` — same as 09 plus `sizeLimits()` override enforcing minimum width.
- All seven examples pass headless snapshot tests AND run on a real terminal without corruption.

---

## Classes to build (new this milestone)

| PHP class (namespace) | Original TV class | Responsibility | Key methods / notes |
|-----------------------|-------------------|----------------|---------------------|
| `Views\Window` | `TWindow` | Framed, movable, resizable, closable, zoomable group. Owns a `Frame` child. | `__construct(Rect, string $title, int $number)`, `close()`, `zoom()`, `sizeLimits(Point &$min, Point &$max)`, `standardScrollBar(int $flags): ScrollBar`, `initFrame(Rect): Frame`, `handleEvent(Event)`. Flags: `wfMove\|wfGrow\|wfClose\|wfZoom` (default all). Commands handled: `cmClose`, `cmZoom`, `cmNext`. |
| `Views\Frame` | `TFrame` | Draws the border, title, and icons (close/zoom/resize) for a window; responds to mouse clicks on icons. | `__construct(Rect)`, `draw(): void` (reads owner `Window` title + flags + state), `handleEvent(Event)`. `growMode = gfGrowHiX\|gfGrowHiY`. Static `$initFrame` glyph string (19 chars, box-drawing set). |
| `Views\ScrollBar` | `TScrollBar` | A single-row or single-column scroll bar. Renders track, thumb, and arrow buttons; updates attached scrollers via `cmScrollBarChanged` broadcast. | `__construct(Rect)`, `draw(): void`, `setValue(int)`, `setRange(int $min, int $max)`, `setStep(int $arrowStep, int $pageStep)`, `handleEvent(Event)`. Public `$value`, `$minVal`, `$maxVal`. Auto-detect orientation from bounds aspect ratio. |
| `Views\Scroller` | `TScroller` | Viewport onto a virtual area wider/taller than the view. Translates scroll-bar `cmScrollBarChanged` broadcasts into a `delta` offset used in `draw()`. | `__construct(Rect, ?ScrollBar $hBar, ?ScrollBar $vBar)`, `scrollTo(int $x, int $y)`, `setLimit(int $x, int $y)`, `scrollDraw(): void`, `checkDraw(): void`. Public `Point $delta`, `Point $limit`. |
| `Views\ListViewer` | `TListViewer` | Abstract, keyboard/mouse-navigable list of items with optional scroll bar support. Concrete subclasses supply `getText()`. | `__construct(Rect, int $numCols, ?ScrollBar $hBar, ?ScrollBar $vBar)`, `abstract getText(int $item, int $maxLen): string`, `focusItem(int $item)`, `setRange(int $range)`, `draw(): void`, `handleEvent(Event)`. Public `int $focused`, `int $topItem`, `int $range`, `int $numCols`. |

---

## Builds on (existing)

- `View` — M2 classes extend or contain `View`; `Window` extends `Group`, `Frame`/`ScrollBar`/`Scroller`/`ListViewer` extend `View`.
- `Group` — `Window` extends `Group`; `insert()`, Z-order, event routing, composite draw all inherited.
- `DrawBuffer` — `Frame`, `ScrollBar`, `Scroller`, `ListViewer` all call `moveChar`/`moveStr`/`moveCStr` + `writeLine` in their `draw()` methods.
- `Palette` — each new class defines its own palette string (faithful TV color indexes); resolution chains through owner up to root as M1 established.
- `Event` / `Cmd` / `Key` — `Window` listens for `cmClose`, `cmZoom`, `cmNext`; `ScrollBar` emits and listens for `cmScrollBarChanged`; drag events use `dmDragMove | dmDragGrow`.
- `Rect` / `Point` — `sizeLimits`, `calcBounds`, `getExtent`, `getClipRect`, `grow` used throughout.
- `Desktop` — windows are inserted into the desktop; M2 adds tiling/cascade helpers if needed for `cmNext` cycling.

---

## Task outline (build order)

1. **`Views\ScrollBar`** (`src/Views/ScrollBar.php` + `tests/Views/ScrollBarTest.php`). Build first because `Scroller` and `ListViewer` depend on it. Tests: orientation detection from bounds, `setValue`/`setRange` clamping, thumb-position calculation, `draw()` buffer snapshot (horizontal and vertical), `cmScrollBarChanged` broadcast on value change, keyboard arrow/page handling when `sbHandleKeyboard` flag set.

2. **`Views\Scroller`** (`src/Views/Scroller.php` + `tests/Views/ScrollerTest.php`). Tests: `setLimit` stores `limit`; `scrollTo` clamps to `[0, limit]` and updates `delta`; `checkDraw` syncs scroll-bar positions; `scrollDraw` calls `drawView`; `setState` propagates active state to bars; headless snapshot with a static subclass verifying `delta` drives draw offset correctly.

3. **`Views\Frame`** (`src/Views/Frame.php` + `tests/Views/FrameTest.php`). Tests: correct single/double box-drawing glyph selection per `wfGrow`/`wfClose`/`wfZoom` flags; title centered and truncated; close-icon click generates `cmClose` command event; zoom-icon click generates `cmZoom`; double-click on title bar generates `cmZoom`; state (active/inactive/dragging) changes drawing style.

4. **`Views\Window`** (`src/Views/Window.php` + `tests/Views/WindowTest.php`). Tests: constructor inserts `Frame`; `standardScrollBar(sbVertical)` creates bar at correct position and inserts it; `standardScrollBar(sbHorizontal)` likewise; `handleEvent` routes `cmClose` → `close()`, `cmZoom` → `zoom()`, `cmNext` → next window focus; `sizeLimits` returns sensible min/max; `zoom()` toggles between `zoomRect` and full desktop extent; mouse drag on title moves window; mouse drag on resize icon grows window.

5. **`Views\ListViewer`** (`src/Views/ListViewer.php` + `tests/Views/ListViewerTest.php`). Tests: `setRange` updates scroll bars; `focusItem` scrolls `topItem` to keep item in viewport; arrow-key navigation calls `focusItem`; mouse click on item focuses it; multi-column layout distributes items across columns; `draw()` calls `getText()` for each visible item and applies selected/normal palette correctly; `cmScrollBarChanged` from attached bar triggers `focusItemNum`.

6. **`Views\GrowMode` / `Views\DragMode` flag constants** — audit that `gfGrowLoX`, `gfGrowHiX`, `gfGrowLoY`, `gfGrowHiY`, `gfGrowAll`, `gfGrowRel`, `dmDragMove`, `dmDragGrow`, `dmLimitAll` etc. are all defined (likely already in `View` from M1; if not, add them here). Tests: `View::calcBounds` reflow for each grow mode combination.

7. **`Views\Desktop` window-management additions** — add `cmNext`/`cmPrev` cycling, tile/cascade stubs, `insert` z-ordering that selects the inserted window. Tests: inserting multiple windows; `cmNext` cycles focus; removing a window restores focus to the next one.

8. **`Application\Program` SIGWINCH / resize wiring** — confirm `SIGWINCH` sets a resize flag (M1 may have partially stubbed this); ensure `Program::run()` loop checks flag, calls `driver->size()`, resizes `Buffer`, triggers `calcBounds` reflow on all children via `Group::changeBounds`, then issues a full redraw. Tests: headless driver exposes a `resize(w, h)` method; a test resizes mid-run and asserts the back buffer changes dimensions and all views reflow to new sizes.

9. **PHP tutorial examples tvguid04–tvguid10** (`examples/php/tutorial/tvguid04.php` … `tvguid07.php`). Translate the C++ sources to idiomatic PHP (typed events, no `T` prefix, constructor promotion, named args). Tests: headless snapshot test per example asserting buffer content at key interaction points (window appears, scroll bar visible, scrolled content visible).

10. **PHP tutorial examples tvguid08–tvguid10** (`examples/php/tutorial/tvguid08.php`…`tvguid10.php`). Add `Scroller` with `standardScrollBar` (08), dual-pane layout (09), `sizeLimits()` override (10). Tests: same headless snapshot pattern; verify `delta` changes buffer content after simulated scroll-bar events; verify `sizeLimits` prevents shrinking below minimum in 10.

11. **Palette wiring for M2 classes** — define faithful palette strings for `Window` (`cpBlueWindow`/`cpGrayWindow`), `Frame`, `ScrollBar`, `ListViewer` matching the TV 1.0 color constants. Tests: snapshot tests in both color and monochrome palette modes.

12. **PHPStan + CI green** — add new classes to PHPStan baseline (max level); ensure Pest suite passes for all M2 tests; update `composer.json` autoload if new paths added; confirm `bin/demo` runner works on a real terminal for `tvguid04`.

---

## Key design decisions / risks

- **`TWindowInit` C++ virtual-base init pattern dissolves in PHP.** The C++ `TWindow(bounds, title, number), TWindowInit(&initFrame)` dance is only needed for C++ virtual bases. In PHP, `Window::__construct` simply calls `$this->frame = static::initFrame($bounds)` directly. The `initFrame` override point becomes a `protected static` or `protected` method — subclasses override it to return a custom `Frame` subclass. Document this departure from the original.

- **`Frame` glyph set — CP437 box-drawing vs Unicode.** The C++ `TFrame::initFrame[19]` static string holds CP437 semigraphic bytes (single-line and double-line box corners/edges). The PHP port must map these to their Unicode equivalents (`─`, `│`, `┌`, `┐`, `└`, `┘`, `═`, `║`, etc.) for ANSI output, as M1's rendering model targets UTF-8. Maintain a constant map from the 19-byte CP437 glyph set to Unicode graphemes. Risk: some terminals may not render all box-drawing characters; test on common terminal emulators.

- **`ScrollBar` orientation detection.** TV deduces horizontal vs vertical from `bounds.b.x - bounds.a.x > 1` (width > 1 = horizontal). Replicate exactly; misorient produces subtle rendering bugs.

- **`ScrollBar` thumb size and position arithmetic.** The thumb length and offset are calculated from `value`, `minVal`, `maxVal`, and the track length. This must be integer-arithmetic faithful to TV (no floats) to match expected snapshots. Verify against `TScrollBar.cc`.

- **`cmScrollBarChanged` coupling.** `TScroller` listens for broadcast `cmScrollBarChanged` events from its attached bars; `TListViewer` does the same. In PHP, this relies on `Group`'s broadcast event fan-out reaching the scroller inside the window. Ensure that `ofPostProcess` is set on scroll bars (as shown in `tvguid09`) so broadcasts pass correctly up and back down. This is a subtle event-routing correctness concern — cover with a dedicated integration test.

- **`Scroller::checkDraw` vs `scrollDraw` semantics.** `checkDraw` only triggers a redraw if `delta` has actually changed; `scrollDraw` always redraws. The PHP port must replicate this to avoid unnecessary redraws. Risk: an always-redraw approach masks the bug but wastes cycles and blurs the faithful-core intent.

- **`ListViewer::getText` as abstract.** `TListViewer::getText` is a pure virtual (`= 0`). In PHP, declare it `abstract`; `ListViewer` itself must be `abstract`. The concrete `ListBox` (M3) will extend it. Ensure the abstract contract is clear in doc blocks so M3 can implement it without revisiting this class.

- **Window drag / resize mouse handling.** `TWindow::handleEvent` calls `dragView` for move and `dragView` for grow (TV's internal drag loop). In PHP, this means the event loop must process mouse-move events while a drag button is held. The M1 `AnsiDriver` must already be delivering mouse-move events; confirm SGR mouse mode is enabled before M2 builds.

- **`gfGrowRel` (relative grow mode).** Used by `TWindow` itself (`gfGrowAll | gfGrowRel`) so that it maintains relative size/position when the desktop resizes. `calcBounds` must handle `gfGrowRel` correctly — fractional positioning. Risk of off-by-one if arithmetic deviates from TV's integer-scaled approach.

- **Palette chain for windows.** `Window` uses palette index offsets that must chain through `Desktop` to `Application`. The M1 `Palette` resolution chain must be confirmed to work for windows before detailing — an assumption that the chain is complete may need a targeted integration test at M2 start.

- **`SIGWINCH` race.** `pcntl_signal` is synchronous in PHP's tick model. Confirm that `declare(ticks=1)` or `pcntl_signal_dispatch()` is called in the event loop; without it, SIGWINCH may never fire. If M1 already handles this, M2 just exercises it with real views. If not, fix it in task 8.

---

## Out of scope (later milestones)

- `Dialog` and all dialog controls (`Button`, `InputLine`, `CheckBoxes`, `RadioButtons`, `Label`, `ListBox`) — M3.
- `MenuBox` / `MenuPopup` pull-down navigation depth — M3.
- `ListBox` (the concrete `ListViewer` subclass backed by a `Collection`) — M3; M2 ships only the abstract `ListViewer`.
- `MessageBox` convenience functions — M3.
- `Editor` / `FileEditor` / `EditWindow` / `Memo` — M4.
- `FileDialog` / `ChDirDialog` — M4.
- `OutlineViewer` / `Outline` / `Node` — M5.
- `ColorDialog` and palette-editing UI — M5.
- Help system (`HelpViewer`, `HelpWindow`, `HelpFile`) — M6.
- Object streaming / `ResourceFile` / `StringList` — M6.
- Desktop tile / cascade commands beyond basic `cmNext` cycling — can be deferred to M3 or M4.
- Windows driver / ConPTY support — explicitly out of scope for all milestones.
