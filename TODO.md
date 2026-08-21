# TODO — Whole-Project Code Review (2026-08-21)

Findings from a full audit of `src/` (162 files / ~20K lines): 4 parallel subsystem
reviews + cross-cutting analysis. Every HIGH finding was independently re-verified
against source. Baseline at time of review: Pest 730 passed / PHPStan max clean.

---

## 🔴 High-priority bugs

- [ ] **Batched input events bypass menu/status-line preprocessing**
      `src/Application/Program.php:445–465` — `getEvent()` preprocesses only
      `$events[0]`; events 1..n from one poll are queued raw in `$pending` and later
      shifted out without `preprocess()`. Second key of any burst can't trigger
      Alt-hotkeys or status shortcuts; docblock promises otherwise.
      Fix: queue *all* polled events, then dequeue-and-preprocess whatever is returned (~5 lines).

- [ ] **One invalid UTF-8 byte silently discards surrounding + all subsequent output**
      `src/Text/Terminal.php:505–509` — `preg_match_all('/\X/u')` returns `false` on
      any invalid byte → single `?`, rest abandoned. Contradicts documented per-glyph
      fallback (line 176); one binary byte in a log stream kills everything after it.
      Fix: byte-wise resync fallback (ASCII/valid sequences pass, `?` per bad byte).

- [ ] **Carriage-return rewrite is O(n²) — 13.7 s freeze measured**
      `src/Text/Terminal.php:389–398` — after `\r`, each glyph re-splits the whole
      tail line (`TerminalText::graphemes($line)` per keystroke). ~1100× an equal append.
      Fix: cache tail-line grapheme array; invalidate on `newLine()/appendEmptyLine()/evictHead()/trimTailToBudget()`.

- [ ] **Ctrl+PageUp/PageDown handlers are dead code for real terminals**
      `src/Outline/OutlineViewer.php:332–335` — guards match plain `Key::PageUp->value`
      + Ctrl modifier bit, but the decoder folds combos into
      `Key::CtrlPageUp->value` (`src/Drivers/EscapeDecoder.php:634–644`).
      Fix: match both identities (`Editor.php:743` does this correctly for CtrlHome).

- [ ] **File-dialog focus mirroring is dead code**
      `src/Dialogs/FileInputLine.php:14`, `src/Dialogs/FileInfoPane.php` — both handle
      `EventType::Broadcast` but never add `EventMask::Broadcast` to `$eventMask`
      (default mask excludes it), so `Group::acceptsEvent()` never routes broadcasts
      to them. Arrowing through a FileList never mirrors the name/info pane.
      Fix: `$this->eventMask |= EventMask::Broadcast;` in both constructors (+1 integration test).

- [ ] **Two isolated static clipboards = silent data loss**
      `src/Editors/Editor.php:41` vs `src/Dialogs/InputLine.php:37` — copy in the
      Editor (^C), paste into an InputLine (^V) yields stale InputLine-only text.
      Fix: one shared `Clipboard` holder (see API section) both delegate to.

- [ ] **Editor loses the goal column; cursor drifts left through ragged lines**
      `src/Editors/Editor.php:694–699` — `moveVertically()` recomputes x from the
      pointer clamped to each short line's end; original column never restored.
      Fix: track `$goalColumn` on horizontal moves, consume on vertical ones.

---

## 🟠 Medium-priority bugs

- [ ] **Ghost pixels when views removed under a modal**
      `src/Views/Group.php:74–92, 732–733` — `execView()` redraws only the modal per
      event; programmatic/idle removals never invalidate anything. (Masked in direct
      interactions by `run()`'s dirty-after-event policy.) Fix: owner `drawView()` after
      removing a visible child, matching the `hide()` precedent.

- [ ] **Silent `stty raw -echo` failure leaves cooked terminal marked initialised**
      `src/Drivers/AnsiDriver.php:88–94,150–158` — stty runner has no failure channel;
      result ignored. Fix: validate the transition (re-read `stty -g` vs saved), throw
      inside the existing unwind path.

- [ ] **Modal loops don't honor closed-input graceful-shutdown policy**
      `src/Application/Program.php:190–193` — `InputClosedException` during
      `executeDialog()` escapes to app code; only `run()` catches it.
      Fix: catch in the modal pump too (end modal with `Cmd::Quit`), or centralize in `pumpEvent()`.

- [ ] **Duplicate explicit help contexts silently overwrite topics**
      `src/Help/HelpCompiler.php:43–48` — names dedup-checked, context values not;
      topic B becomes unreachable while its generated symbol resolves to C's text.
      Fix: track used contexts, throw on collision.

- [ ] **ViewResourceNode constructor skips load-path validation**
      `src/Resources/ViewResourceNode.php:17–26` — hand-built nodes accept closures/
      resources/non-node children; `toArray()` emits them and round-trips fail.
      Fix: funnel construction through the same validator `fromArray()` uses.

- [ ] **Held `ESC ESC [` ambiguity destroyed at timeout flush**
      `src/Drivers/EscapeDecoder.php:213,307–332` — double-Esc presses vanish after
      the quiet timeout. Fix: emit one Esc event per leading ESC byte in `flushPendingEvents`.

- [ ] **Cursor-presented state committed before fallible driver write**
      `src/Terminal/Screen.php:151–193` — failed flush diverges hardware cursor model
      until some later frame moves it. Fix: snapshot/restore cursor state around `write()`.

- [ ] **Cluster mnemonics gated behind focus check despite PreProcess flag**
      `src/Dialogs/Cluster.php:139` — typing "o" while a text field has focus never
      activates `~O~ne`; Button/Label handle this correctly.
      Fix: mnemonic matching before/outside the focus gate.

- [ ] **SearchOptions PromptOnReplace/DoReplace accepted but ignored**
      `src/Editors/SearchOptions.php:12–14`, `src/Editors/Editor.php:380–429` — flags
      advertise behavior that doesn't exist; `EditorDialogKind::ReplacePrompt/Find/
      SearchFailed/OutOfMemory` have no emission path. Honor or clearly deprecate.

- [ ] **MultiCheckBoxes shift overflow + RadioButtons setData inconsistency**
      `src/Dialogs/MultiCheckBoxes.php:47` — ≥8 items × 8-bit range shifts past int
      width → garbage bits. `src/Dialogs/RadioButtons.php:30–34` — clamps `sel` but
      not `value`. Guard the shift; clamp both.

- [ ] **Atomic writes promote 0600 temp perms; no directory fsync**
      `src/Help/AtomicFileWriter.php:20–46` — replaced files become owner-only
      regardless of umask. Fix: `chmod` before rename (preserve existing mode or apply umask).

- [ ] **Numeric-string data keys break StreamCodec round-trip**
      `src/Persistence/StreamCodec.php:236–291` — codec rejects documents it wrote
      itself (json_decode key coercion). Fix: reject numeric-string keys at encode time with clear message.

- [ ] **Root-origin mismatch renders offset roots blank, silently**
      `src/Views/View.php:107–119` vs `798–808` — document the constraint or normalize.

---

## 🔁 Duplicated code

- [ ] **~25 hand-rolled clamps** (`max(0, min(...))` across Editor, InputLine,
      OutlineViewer, ScrollBar, MessageBox, …) — blocked on `IntMath::clamp()` (below).
- [ ] **Atomic-write machinery copied** — `ResourceFile::writeResources()`
      (`src/Resources/ResourceFile.php:210–264`) ≈ line-for-line `AtomicFileWriter::write()`;
      already drifted (different exception types). Route through one mechanism.
- [ ] **Menu layout walk ×5** — MenuBar draw/topIndexAtColumn/topItemX +
      StatusLine draw/commandAtColumn each re-walk items accumulating widths.
      Extract `layout(): list<array{item,startX,endX}>` per class.
- [ ] **Mnemonic handling ×4** despite `Mnemonic` class — menus reimplement extraction
      with subtly different regex (`.` vs `\X`) + visible-length stripping twice.
      Extend `Mnemonic::visibleLength()`, route lookups through it.
- [ ] **Fill-extent boilerplate ×3** (View/Background/StaticText) — extract `fillExtent()`.
- [ ] **Occlusion row-math duplicated** — `View.php:310–326` vs `Group.php:332–359`.
- [ ] **SubMenu→MenuItem lowering ×3** — `SubMenu.php:29–37`, `Menu.php:16–25`,
      `MenuBar.php:52–61`. Add `SubMenu::toMenuItem(): MenuItem`.
- [ ] **Outline cycle detection ×3** + fresh `SplObjectStorage` allocated per accessor
      call on hot paths (`src/Outline/Outline.php:35–68`, `OutlineViewer.php:495–540`).
- [ ] **Terminal relayout-redraw sequence ×4** (`src/Text/Terminal.php:142–237`) —
      extract `relayoutAndDraw()`; also retention check ×2 (:381/:431).
- [ ] **Smaller copies:** `itemEnabled()` verbatim (MenuBar/MenuBox) · warning-suppression
      closures ×2 in AnsiDriver · identical EINTR stall branches (`AnsiDriver.php:300–316`)
      · MAX_CELLS guard Buffer-vs-Screen · Program band math layout()/reflowDesktop()
      · cluster geometry ×3 · run-guard boilerplate in **22 example files**.

---

## 🔧 Refactoring opportunities

- [ ] **`EscapeDecoder::legacyKeyCode()`** (`:609–672`) — 60-line triple-nested match →
      static lookup tables keyed by modifier primary. `altKey()` (`:817–826`) linearly
      scans all Key cases per Alt keystroke → precompute once.
- [ ] **Dead code:** Desktop's unreachable tile branch (`Desktop.php:129,241–246`) ·
      PictureValidator's `$terminator` param (only ever `null`; two branches unreachable)
      · never-produced `PicResult::Ambiguous/IncompleteWithoutFill` · no-op
      `focusNext()/focusPrevious()` aliases · do-nothing `FileInputLine` ctor.
- [ ] **Magic numbers:** bare `1/3/22/24/26 =>` clipboard keycodes where
      `Key::CtrlA/CtrlC/CtrlV/CtrlX/CtrlZ->value` exist (`Editor.php:750–754`,
      `InputLine.php:262–278`) · literal `0x03` twice in `Program.php` → `Key::CtrlC` ·
      hardcoded default attr `0x07` ×7 → `Cell::blank()` · poll-ms/min-window/auto-scroll
      ticks/History caps/MenuBox widths → named constants.
- [ ] **Per-frame waste:** HelpViewer re-runs word-wrap per ref×row (~180 layouts/draw,
      `HelpViewer.php:74–92`) · `View::writeRowCells` recomputes origin+clip chain per row
      · AnsiDriver installs/restores error handler every loop iteration.
- [ ] **Consistency nits:** inline FQCNs instead of imports (`Group.php:339,486`,
      `ListViewer.php:328`) · mixed `\InvalidArgumentException` qualified/unqualified ·
      separator-convention mixing in `FilePath`/`DirListBox` · `HelpFile` lax ctor key
      casting + misleading `invalidTopic()` name (it builds the friendly fallback).

---

## 📝 PHPDoc gaps

- [ ] **Undocumented core hooks:** `Program::pumpEvent()` (no docblock; contract:
      dequeue one event, reflow/idle/present as needed, caller dispatches),
      `handleModalEvent()` (return contract + undocumented Ctrl-C clearing),
      `getEvent()` (pending-batch caveat — see HIGH #1), `run()` (always returns 0;
      document or return `$endState`).
- [ ] **Docs contradicting code:** `Terminal::doSputn` per-glyph promise (broken by
      UTF-8 bug above) · `Driver::init()` wording inverted · `TextDevice::write`
      implies partial acceptance · `Group.php:430` C++ "byte-record pointer" language.
- [ ] **Missing `@throws`:** `View::setOwner/setBounds`, `Group::insert/setCurrent/
      reorderInFrontOf/execView`, `Memo/InputLine::setData`, `RangeValidator::transfer`,
      `Driver::pollInput` (documents 1 of 3 thrown types), `OutputTextStream::printf` (ValueError).
- [ ] **Stale:** unused `@phpstan-type SttyRunner` (`AnsiDriver.php:21`) · ListViewer's
      documented-but-never-painted divider palette entry.

---

## 🧩 API & abstraction additions

**Geometry first — dissolves most duplication above:**

- [ ] `IntMath::clamp(int $value, int $min, int $max)` — highest-leverage single addition.
- [ ] `Rect`: `intersects()`, `union()`, `contains()`, `inset()`, `centeredIn(Rect)`
      (Group hand-rolls centering), `clampInto(Rect)` (Window drag logic),
      `fromSize()/size()`, `__toString()` (Point has one; Rect doesn't — hurts test diffs).
- [ ] `Point`: `scale()`, `negate()`, static `min()/max()`, `clampTo(Rect)`.
- [ ] `readonly SizeLimits { minW, minH, maxW, maxH }` value object — kills positional
      `array{0:int,1:int,2:int,3:int}` knowledge spread across 5 sites
      (`View.php:170,405`, `Window.php:92,360,377`, `Desktop.php:150`).

**Interface conformance & iteration:**

- [ ] `CommandSet` implements `Countable, IteratorAggregate` (has `count()` but
      `count($set)` fatals).
- [ ] `SortedCollection`: TV-parity `search()` (binary search) and `remove()`;
      consumers currently hand-roll linear scans.
- [ ] `Group`: `IteratorAggregate` over children + `first()/last()/windows(): list<Window>`
      (Desktop hand-rolls the filter 3×).
- [ ] `Buffer`: `IteratorAggregate/Countable` + `row(y): array<Cell>`.
- [ ] `DrawBuffer`: `putCell()`, `cellAt()`, `moveBuffer()` copy primitive, `__toString()`.

**Ergonomics:**

- [ ] `View::localToGlobal(Point)` — inverse of `makeLocal()`; needed by any drag impl.
- [ ] Immutable `Cell::with(fg:, bg:, blink:)` + `Cell::blank()/sentinel()` named
      constructors (retires the `-1/"\0"` sentinel leak documented inside Cell's ctor).
- [ ] Fluent `Window::setTitle/setNumber/setPalette` returning `static`.
- [ ] Shared `Clipboard` service (also fixes split-clipboard HIGH bug).
- [ ] `Application::runIfMain(__FILE__)` — replaces run-guard boilerplate in 22 examples.
- [ ] `PictureNode::$type` free-form string → 4-case enum.
- [ ] `AnsiDriver::forStdio(...)` named constructor (positional ctor invites bool-trap calls).
- [ ] Injectable clock for `EscapeDecoder` double-click timing (currently wall-clock
      `microtime(true)`, unlike every other injectable clock).

---

## Top quick wins (payoff ÷ effort)

1. Fix `getEvent()` preprocessing (`Program.php:445`) — ~5 lines.
2. Add `EventMask::Broadcast` to FileInputLine + FileInfoPane — two lines.
3. Byte-wise UTF-8 fallback in `Terminal::consumeText` — ~15 lines, ends silent data loss.
4. `IntMath::clamp()` + adopt it — one method retires ~25 duplication sites.
5. Route `ResourceFile` through `AtomicFileWriter` — deletes ~45 lines, unifies durability.
