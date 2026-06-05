# M3 Menus-deep + Dialogs — Spike Plan (outline)

> Spike-level plan: scope, classes, task outline, acceptance, risks. NOT full TDD code.
> Promote to a detailed plan right before building, once M1/M2 views exist.

**Milestone goal:** Complete pull-down menu navigation (`MenuBox` drop-downs, hotkey traversal, submenu chaining, `MenuPopup`) and the full dialog/control widget set (`Dialog`, `Button`, `InputLine`, `CheckBoxes`, `RadioButtons`, `Cluster`, `Label`, `ListBox`) plus the `MessageBox`/`inputBox` one-call helpers and the `setData`/`getData` form round-trip mechanism. After M3, every C++ tutorial example from `tvguid11` through `tvguid16`, plus `nomenus`, `listbox`, and `splash`, can be reproduced in PHP.

**Depends on:** M1 primitives (`Geometry`, `Drawing`, `Events`, `Drivers`), M1 view tree (`Views\View`, `Views\Group`, `Views\StaticText`, `Views\Background`, `Views\Desktop`, `Menus\MenuBar` stub, `Application\Program`/`Application`), M2 windowing (`Views\Window`, `Views\Frame`, `Views\ScrollBar`, `Views\Scroller`, `Views\ListViewer`).

**Acceptance examples:**
- `tvguid11` — bare `Dialog` inserted into desktop (no buttons)
- `tvguid12` — `Dialog` with a single `Button`
- `tvguid13` — `Dialog` with OK + Cancel `Button`s, modal `execView` returns command
- `tvguid14` — adds `CheckBoxes`, `RadioButtons`, `Label`
- `tvguid15` — adds `InputLine` + `Label`
- `tvguid16` — `setData`/`getData` round-trip with a `DialogData`-shaped PHP array/VO
- `nomenus` — app with no menu bar, uses `MessageBox::show()` at start, dialog, custom `Desktop`
- `listbox` — `ListBox` inside a `Dialog` with `setData`/`getData` using a collection + selection
- `splash` — dialog shown at boot from constructor via `executeDialog()`; `StaticText` centering (`\003`)
- All run headless (scripted events) and pass buffer-snapshot assertions; all run on a real terminal

---

## Classes to build (new this milestone)

| PHP class (namespace) | Original TV class | Responsibility | Key methods / notes |
|---|---|---|---|
| `Menus\MenuBox` | `TMenuBox` | Vertical drop-down menu box spawned from `MenuBar` or nested submenus | `draw()`, `getItemRect()`, auto-size bounds to item list, shadow (`sfShadow`), frame chars |
| `Menus\MenuPopup` | `TMenuPopup` | Standalone context-menu variant of `MenuBox` | `handleEvent()` — dismisses on click outside; no parent menu pointer |
| `Dialogs\Dialog` | `TDialog` | Non-growable, movable, closable modal window | `handleEvent()` (Esc→cmCancel, Enter→cmDefault broadcast), `valid()`, `getPalette()` |
| `Dialogs\Button` | `TButton` | Push-button that emits a command or broadcast | `draw()`, `handleEvent()` (click/hotkey/cmDefault), `press()`, `makeDefault()`, `setState()`, `bfDefault`/`bfNormal`/`bfBroadcast` flags |
| `Dialogs\InputLine` | `TInputLine` | Single-line text editor field | `draw()`, `handleEvent()` (keys, mouse, block-mark, scroll), `getData()`, `setData()`, `selectAll()`, `valid()`, `maxLen`, cursor/selection state |
| `Dialogs\Cluster` | `TCluster` | Abstract base for `CheckBoxes` and `RadioButtons` | `drawBox()`, `handleEvent()` (click/arrows/Space), `getData()`, `setData()`, `dataSize()`, bitmapped `value`/`enableMask`/`sel` |
| `Dialogs\CheckBoxes` | `TCheckBoxes` | Cluster toggling independent bits | `draw()` (`[X]`/`[ ]`), `mark()`, `press()` (bit-toggle) |
| `Dialogs\RadioButtons` | `TRadioButtons` | Cluster allowing exactly one selection | `draw()` (`(.)`/`( )`), `mark()`, `movedTo()`, `press()`, `setData()` syncs `sel` to `value` |
| `Dialogs\Label` | `TLabel` | Non-interactive text that focuses a linked view via hotkey | `draw()`, `handleEvent()` (Alt-hotkey → focus `link`), constructor takes linked `View` |
| `Dialogs\SItem` | `TSItem` | Singly-linked list node of strings, used to build cluster/list items | Value object: `string $value`, `?SItem $next`; variadic PHP helper `SItem::list(...)` |
| `Dialogs\StaticText` | `TStaticText` | (already in `Views\`; re-verify `\003` centering for `splash`) | Confirm `\003` prefix → centered line rendering |
| `Dialogs\ParamText` | `TParamText` | `StaticText` with `sprintf`-style parameter substitution | `setText(string $fmt, mixed ...$args)` |
| `Dialogs\ListBox` | `TListBox` | `ListViewer` subclass displaying a string collection with scroll bar | `draw()`, `getText()`, `newList()`, `getData()`, `setData()`, `dataSize()`; data record: `{collection, selection}` |
| `Dialogs\MessageBox` | `messageBox`/`MsgBoxText` family | One-call info/warn/error/confirm dialogs; `inputBox` helper | `MessageBox::show(string, int): int`, `MessageBox::showRect(Rect, string, int): int`, `MessageBox::input(string, string, int): ?string`; flags enum `MsgBoxFlag` |

---

## Builds on (existing)

- `Views\View` — `state`/`options`/`growMode` flags, `draw()`, `handleEvent()`, `setState()`, `valid()`, `getData()`/`setData()` protocol, `drawView()`, focus/Tab machinery
- `Views\Group` — `insert()`, `execView()` (modal loop), `setData()`/`getData()` fan-out, focus chain, `selectNext()`
- `Views\Window` — `Dialog` extends this; inherits frame, title, `wfMove`/`wfClose` flags
- `Views\StaticText` — already built in M1; `Dialog` may insert it directly
- `Views\ListViewer` — `ListBox` extends this (M2); provides scrollable item navigation
- `Menus\MenuBar` / `Menus\MenuView` — M3 deepens `MenuView::execute()`, `findItem()`, `hotKey()`, `newSubView()` to spawn `MenuBox` children
- `Events\Cmd` — `cmOK`, `cmCancel`, `cmYes`, `cmNo`, `cmDefault`, `cmGrabDefault`, `cmReleaseDefault`, `cmMenu` must all be present
- `Drawing\DrawBuffer` — `moveCStr()` with `~hotkey~`, `moveChar()`, `moveStr()`

---

## Task outline (build order)

### Deep menu navigation

1. **`MenuView::execute()` + modal menu loop** — Implement the full interactive menu loop inside `MenuView`: arrow-key traversal (`nextItem`/`prevItem`/`trackKey`), mouse tracking (`trackMouse`), hotkey matching (`findItem`, `hotKey`), Esc/Enter/click dispatch. Add `findHotKey()` recursive search. Touches: `src/Menus/MenuView.php`. Tests: headless scripted F10 + arrow + Enter sequence resolves to expected command.

2. **`MenuBox`** — Vertical drop-down; auto-sizes bounds to widest item label + shortcut param column; draws frame, items, shadow (`sfShadow`), separator lines (null-name items); `getItemRect()` maps row to item. `newSubView()` in `MenuView` must return a `MenuBox`. Touches: `src/Menus/MenuBox.php`. Tests: snapshot of a 3-item drop-down at known position.

3. **`MenuBar` full interaction** — Wire `MenuBar::handleEvent()` to spawn a `MenuBox` child via `Group::execView()` on F10 or Alt-letter; horizontal left/right arrows move between top-level items and re-spawn; propagate result command. Touches: `src/Menus/MenuBar.php`. Tests: Alt-F → arrow → Enter resolves to the correct `Cmd`.

4. **`MenuPopup`** — Standalone `MenuBox` subclass that dismisses on click outside its bounds. Touches: `src/Menus/MenuPopup.php`. Tests: headless click outside dismisses with `cmCancel`.

### Dialog + modal execView

5. **`Dialog`** — Extend `Window` with `growMode=0`, flags `wfMove|wfClose`; override `handleEvent()`: Esc → `endModal(cmCancel)`, Enter → broadcast `cmDefault`, handle `cmOK`/`cmCancel`/`cmYes`/`cmNo` by calling `endModal(cmd)` (faithful `endModal` sets modal-result and unwinds `execView`); `valid(cmCancel)` returns true. `getPalette()` returns `cpGrayDialog`. Expose `executeDialog()` helper on `Program` (centers + runs modally). Touches: `src/Dialogs/Dialog.php`. Tests: headless Esc → result is `cmCancel`; Enter → result is `cmOK`.

6. **`Group::execView()` modal loop** — Verify/complete the modal sub-loop in `Group`: pushes modal state, runs inner event loop until `endModal()`, restores focus. This is the keystone M3 primitive. Touches: `src/Views/Group.php`. Tests: nested execView depth, Esc unwinds exactly one level.

### Controls

7. **`SItem`** — Immutable singly-linked string list node. PHP factory helper: `SItem::list('a', 'b', 'c')` builds the chain. Touches: `src/Dialogs/SItem.php`. Tests: linked list iteration length and values.

8. **`Cluster` (abstract)** — Holds a `TStringCollection`-equivalent (PHP: `string[]` built from `SItem`), `int $value` (bitmask), `int $enableMask`, `int $sel`. Implement: `handleEvent()` (click selects item, Space toggles/presses, arrow keys move `sel`), `drawBox()`, `getData()`/`setData()` (`dataSize()` = 2 bytes, faithful), `setState()`, `setButtonState()`. Touches: `src/Dialogs/Cluster.php`.

9. **`CheckBoxes`** — Concrete `Cluster`; `draw()` draws `[ ]`/`[X]` boxes; `mark(item)` tests bit `item` of `$value`; `press(item)` toggles that bit. Touches: `src/Dialogs/CheckBoxes.php`. Tests: headless Space-key toggles bits; `getData` returns expected bitmask.

10. **`RadioButtons`** — Concrete `Cluster`; `draw()` draws `( )`/`(.)` buttons; `mark(item)` returns `$value === $item`; `press(item)` sets `$value = $item`; `movedTo(item)` sets `$value = $item`; `setData()` also syncs `$sel = $value`. Touches: `src/Dialogs/RadioButtons.php`. Tests: pressing item 1 deselects item 0.

11. **`Button`** — Full push-button: `draw()` / `drawState()` (up/down, 3D shadow, mono-mode brackets); `handleEvent()` responds to mouse click, hotkey (`~X~`), `cmDefault` broadcast, `cmGrabDefault`/`cmReleaseDefault`; `press()` emits command or broadcast; `makeDefault()`; `setState()` grabs/releases default on focus. Flags: `ButtonFlag` backed enum (`Normal`, `Default`, `LeftJust`, `Broadcast`, `GrabFocus`). Touches: `src/Dialogs/Button.php`. Tests: headless Enter on default button; Tab shifts default; hotkey press.

12. **`InputLine`** — Single-line editor: `draw()` (passive/active/selected palette, scroll arrows when content overflows); `handleEvent()` (printable chars, Home/End/arrows, Del/BackSpace, block-select with mouse drag/Shift, insert/overwrite toggle); `getData()`/`setData()` copy string up to `maxLen`; `selectAll()`; `setState()` selects all on focus; `valid()` always true (validator hook reserved for M4). Touches: `src/Dialogs/InputLine.php`. Tests: type "hello" → `getData()` returns "hello"; mouse click positions cursor.

13. **`Label`** — Draws label text with hotkey highlight; `handleEvent()` intercepts Alt+hotkey and calls `focusView($this->link)`. Constructor: `Label(Rect, string $label, View $link)`. Touches: `src/Dialogs/Label.php`. Tests: Alt-hotkey event routes focus to linked view.

14. **`ListBox`** — Extends M2 `ListViewer`; constructor takes `(Rect, int $numCols, ?ScrollBar)`; holds a PHP array or `Collection` of strings as `$items`; `getText()` returns item string; `newList()` replaces list and resets range; `getData()`/`setData()` use a value object `ListBoxData {collection, selection}`; `dataSize()`. Touches: `src/Dialogs/ListBox.php`. Tests: `setData` with 20 items, scrolls to selection, `getData` returns correct selection index.

### Message boxes + helpers

15. **`MessageBox`** — Static helper class (not a `View`): `show(string $msg, int $options): int`, `showRect(Rect, string, int): int`, `input(string $title, string $label, int $maxLen): ?string`. Internally builds a `Dialog` with `StaticText` and the appropriate `Button` set; runs via `Program::execView()`; returns the command code (`cmOK`/`cmYes`/`cmNo`/`cmCancel`). `MsgBoxFlag` enum holds `Warning`, `Error`, `Information`, `Confirmation`, `YesButton`, `NoButton`, `OKButton`, `CancelButton`. `MsgBoxText` holds localizable button strings. Touches: `src/Dialogs/MessageBox.php`, `src/Dialogs/MsgBoxFlag.php`, `src/Dialogs/MsgBoxText.php`. Tests: headless `MessageBox::show()` returns `cmOK` on Enter.

### setData/getData form round-trip

16. **`Group::setData()` / `Group::getData()` fan-out** — Walk subview list in insert order; call each child's `setData()`/`getData()` advancing a byte offset into the record. In PHP the record is a PHP `array` (or a typed VO); children receive a slice by `dataSize()`. Verify that `Dialog` + `CheckBoxes` + `RadioButtons` + `InputLine` round-trips correctly (tvguid16 pattern). Touches: `src/Views/Group.php`. Tests: populate dialog, run modal, cancel → data unchanged; OK → data reflects edits.

### PHP acceptance examples

17. **Port `tvguid11`–`tvguid16`** — Write PHP equivalents under `examples/php/tutorial/`. Each adds one feature layer over the last. Headless test fixture scripts the appropriate keystrokes and asserts buffer snapshots + return codes. Touches: `examples/php/tutorial/tvguid11.php` … `tvguid16.php`, `tests/Acceptance/`.

18. **Port `nomenus`, `listbox`, `splash`** — `nomenus`: null menu/status, custom `Desktop`/`Background`, `MessageBox::show()` call, dialog round-trip. `listbox`: `ListBox` with `StringCollection`, `setData`/`getData`, OK branch shows second `MessageBox`. `splash`: dialog from constructor via `executeDialog()`, `ofCentered` option, `StaticText` `\003` centering. Touches: `examples/php/tutorial/{nomenus,listbox,splash}.php`, `tests/Acceptance/`.

---

## Key design decisions / risks

- **Modal `execView` loop** — `Group::execView()` must push/pop modal state cleanly and support re-entrant nesting (menu inside dialog inside app). The modal result is an `int` command code returned synchronously. PHP has no `longjmp`; the loop must be a plain `while` that checks `$this->endState` set by `endModal(int)`.

- **`setData`/`getData` record shape in PHP** — C++ uses a raw memory buffer with fixed offsets. PHP equivalent: an `array` (ordered, string-keyed or indexed) that each control slices by `dataSize()` in insertion order. Typed value objects (e.g. `readonly class DialogData`) are the idiomatic M3 skin; `getData` populates them via named properties. A `DialogRecord` interface with `toArray()`/`fromArray()` may help, but keep it simple for M3 — just use plain `array`.

- **`Cluster` value type** — C++ `value` is `unsigned long` (32 bits). PHP: `int` (64-bit on 64-bit PHP, always enough). `dataSize()` returns 2 (ushort, faithful) for `CheckBoxes`/`RadioButtons`; the group fan-out uses `dataSize()` to advance the offset, so the sizes must be exact.

- **`Button` default/broadcast protocol** — Exactly one button holds "default" at a time. Focus change broadcasts `cmGrabDefault`/`cmReleaseDefault` across siblings. This requires `Group` broadcast routing to work correctly before `Button` can be tested end-to-end.

- **Focus and Tab traversal inside `Dialog`** — `Dialog` is a `Group`; Tab/Shift-Tab must cycle through `ofSelectable` children in Z-order. This is inherited from `Group::handleEvent()` (M1/M2). Verify it works with mixed control types before building `Button` makeDefault.

- **`Label` hotkey routing** — `Label` uses `ofPreProcess` so it sees key events before the focused view. The Alt+hotkey must be consumed by `Label` and forwarded as a `focusView` call to its linked control, not propagated further.

- **`InputLine` no validator in M3** — The `TValidator` hook is reserved; in M3 `valid()` always returns `true`. The constructor can accept an optional `?Validator $validator = null` parameter that is stored but not invoked until M4.

- **`MenuBox` bounds auto-sizing** — `TMenuBox` adjusts its own bounds in the constructor to fit the widest item. In PHP this means computing `max(strlen(label) + strlen(param))` across all items and clamping to screen bounds. This must not exceed the `Driver::size()` reported terminal width.

- **`MessageBox` centering** — TV's `\003` prefix in a `StaticText` string means "center this line". This must be handled in `StaticText::draw()` before M3 acceptance; confirm it was implemented in M1/M2 or add it here.

- **`executeDialog()` helper** — `splash.cc` calls `executeDialog(dialog)` which centers the dialog (`ofCentered`), runs it modally, and destroys it. This is a `Program`-level helper; add it alongside `execView()` in M3.

- **`ListBox` collection type** — C++ uses `TCollection *`; PHP: pass an `array<string>` or a typed `Collection` object. Use a simple `string[]` for M3; `SortedCollection` deferred to M4/file dialogs.

---

## Out of scope (later milestones)

- `Validator` family (`FilterValidator`, `RangeValidator`, `PictureValidator`) — M4
- `History` / `HistoryViewer` / `HistoryWindow` pick-list — M4
- `MultiCheckBoxes` (multistate cluster) — M4 (not used in any M3 acceptance example)
- `ParamText` beyond basic `sprintf` — M4
- `FileDialog` / `ChDirDialog` / `SortedListBox` / `FileList` — M4
- `ColorDialog` and palette selectors — M5
- Help system (`HelpViewer`, `HelpFile`, help contexts beyond `hcNoContext`) — M6
- Object streaming / `ResourceFile` / `StringList` — M6
- `TextDevice` / `Terminal` TTY view — M5 or later
- `OutlineViewer` / `Outline` / `Node` — M5
