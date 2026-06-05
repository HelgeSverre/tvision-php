# M4 Editor & Files — Spike Plan (outline)

> Spike-level plan: scope, classes, task outline, acceptance, risks. NOT full TDD code.
> Promote to a detailed plan right before building, once M1-M3 exist.

**Milestone goal:** Deliver the complete input-validator family wired into `InputLine`;
a fully functional multi-line text editor (`Editor`, `FileEditor`, `EditWindow`, `Memo`,
`Indicator`) with gap buffer, selection, clipboard, undo, search/replace, and
file I/O; and the standard file/directory dialogs (`FileDialog`, `ChDirDialog`) with
their supporting collections and list views. Together these pass the acceptance
targets derived from `validator.cc` and `tvedit.cc`.

**Depends on:** M1 (Geometry, Drawing, Drivers, Events, Views, Application); M2
(Window, Frame, ScrollBar, Scroller, ListViewer); M3 (Dialog, Button, InputLine,
clusters, Label, ListBox, MessageBox, setData/getData, deep menus, History).

**Acceptance examples:**
- `examples/php/tutorial/validator.cc` — PHP translation runs headless: dialog with
  `RangeValidator`-guarded date fields, `PictureValidator` masks; invalid keystrokes
  rejected or reformatted in-place; dialog closes only when all fields pass.
- `examples/php/tutorial/tvedit.cc` — PHP `TVDemo` boots: opens files via
  `FileDialog`, edits in `EditWindow` with scroll bars and `Indicator`, Find/Replace
  dialogs work, clipboard window toggles, `ChDirDialog` changes cwd; clean quit.
- Headless buffer-snapshot tests for each validator type and for `FileDialog` layout.
- Real-terminal smoke test: `tvedit.php` opens a file, scrolls, edits, saves.

---

## Classes to build (new this milestone)

| PHP class (namespace) | Original TV class | Responsibility | Key methods / notes |
|---|---|---|---|
| `Validators\Validator` | `TValidator` | Abstract base for all validators | `isValidInput()`, `isValid()`, `validate()`, `error()`, `transfer()`; `$status`, `$options` flags (`voFill`, `voTransfer`) |
| `Validators\FilterValidator` | `TFilterValidator` | Allow-list of valid characters per keystroke | `isValidInput()` rejects chars not in `$validChars` string; `isValid()` same; `error()` shows MessageBox |
| `Validators\RangeValidator` | `TRangeValidator` | Integer range check; owns data transfer | Extends `FilterValidator` (digit chars + sign); `isValid()` parses int, checks `[$min, $max]`; `transfer()` marshals `int` ↔ string for `setData`/`getData` |
| `Validators\LookupValidator` | `TLookupValidator` | Abstract lookup base | `isValid()` delegates to `lookup()`; `lookup()` returns `false` by default |
| `Validators\StringLookupValidator` | `TStringLookupValidator` | Match input against a `StringCollection` | `lookup()` binary-searches the sorted collection; `newStringList()` replaces collection; `error()` shows MessageBox |
| `Validators\PictureValidator` | `TPXPictureValidator` | Paradox-style picture-mask formatting | `picture()` recursive-descent parser returning `PicResult` enum; `isValidInput()` applies fill if `voFill`; `isValid()` requires `prComplete`; `error()` shows MessageBox |
| `Validators\PicResult` | `TPicResult` enum | Result codes from picture matching | `Complete`, `Incomplete`, `Empty`, `Error`, `Syntax`, `Ambiguous`, `IncompNoFill` |
| `Editors\Indicator` | `TIndicator` | Line:column + modified-flag view | `setValue(Point, bool)` called by `Editor`; `draw()` renders `Ln N  Col N`; drag-state highlight |
| `Editors\Editor` | `TEditor` | Core multi-line editor (gap buffer, selection, undo, search) | `insertBuffer()`, `insertText()`, `insertFrom()`, `deleteRange()`, `search()`, `find()`, `replace()`, `doSearchReplace()`, `undo()`; static `$clipboard`, `$editorDialog`, `$editorFlags`, `$findStr`, `$replaceStr`; `convertEvent()` key→cmd; `lock()`/`unlock()`/`update()` redraw throttle |
| `Editors\Memo` | `TMemo` | `Editor` embedded in a dialog/form | Extends `Editor`; `getData()`/`setData()`/`dataSize()` using `MemoData` struct; suppresses `Tab` key so focus can move |
| `Editors\FileEditor` | `TFileEditor` | `Editor` bound to a file on disk | `loadFile()`, `save()`, `saveAs()`, `saveFile()`; dynamic buffer growth in 4 KB increments (`setBufSize()`); handles `cmSave`/`cmSaveAs`; backup file (`~`) when `efBackupFiles` set |
| `Editors\EditWindow` | `TEditWindow` | Window hosting a `FileEditor` or clipboard | Creates scroll bars + `Indicator`; `getTitle()` → filename or "Clipboard"; `close()` hides rather than destroys clipboard; handles `cmUpdateTitle` broadcast |
| `Editors\EditorDialog` | `TEditorDialog` (function ptr) | Callback interface for editor dialogs | PHP: interface/closure; codes `edOutOfMemory`…`edReplacePrompt`; default impl returns `Cmd::Cancel` |
| `Editors\FindDialogRec` | `TFindDialogRec` | Data record for Find dialog setData/getData | Readonly value object: `$find` (string), `$options` (int) |
| `Editors\ReplaceDialogRec` | `TReplaceDialogRec` | Data record for Replace dialog | Readonly value object: `$find`, `$replace`, `$options` |
| `Dialogs\FileDialog` | `TFileDialog` | Open/save file dialog | Composes `FileInputLine` + `FileList` + `History` + `FileInfoPane` + buttons; `getFileName()` expands to full path; `valid()` checks path; `fdXxx` option flags |
| `Dialogs\FileInputLine` | `TFileInputLine` | Input line specialized for file names | Extends `InputLine`; handles `cmFileFocused` broadcast to sync from list selection |
| `Dialogs\FileList` | `TFileList` | Two-column sorted file+dir list box | Extends `SortedListBox`; `readDirectory(dir, wildcard)` scans fs via `glob`/`scandir`; `focusItem()` broadcasts `cmFileFocused`; `selectItem()` broadcasts `cmFileDoubleClicked`; `getText()` appends `/` for dirs |
| `Dialogs\FileInfoPane` | `TFileInfoPane` | Size/date pane responding to `cmFileFocused` | `draw()` renders size + formatted date; updates on `cmFileFocused` broadcast |
| `Dialogs\SortedListBox` | `TSortedListBox` | List box backed by a `SortedCollection`; incremental-search on typing | `newList()`, `getKey()`, `shiftState` for keyboard search |
| `Dialogs\ChDirDialog` | `TChDirDialog` | Change-directory dialog | `DirListBox` + `InputLine` + History + OK/Chdir/Revert buttons; `valid()` checks path exists; `cdXxx` flags |
| `Dialogs\DirListBox` | `TDirListBox` | Single-column directory-tree list box | Extends `ListBox`; `newDirectory()` builds `DirCollection`; double-click puts `cmChangeDir`; tree-graphic prefixes |
| `Collections\FileCollection` | `TFileCollection` | Sorted collection of `SearchRec` entries | Extends `SortedCollection`; `compare()` sorts dirs before files, `..` first |
| `Collections\DirCollection` | `TDirCollection` | Collection of `DirEntry` objects | Extends `Collection`; unsorted, insertion-ordered |
| `Collections\DirEntry` | `TDirEntry` | Path + display-text pair | Readonly value object: `string $dir`, `string $text` |
| `Collections\SearchRec` | `TSearchRec` | Single directory-scan result | Readonly value object: `$attr` (file/dir/readonly), `$size`, `$mtime`, `$name` |

---

## Builds on (existing)

- **M1:** `View`, `Group`, `DrawBuffer`, `Palette`, `Event`/`EventType`/`Key`/`Cmd`,
  `Rect`/`Point`, `HeadlessDriver` for testing.
- **M2:** `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer` — `EditWindow`
  and file list boxes are direct consumers.
- **M3:** `Dialog`, `Button`, `InputLine` (validator hook already stubbed or to be
  added here), `ListBox`, `MessageBox`, `History`, `Label`, `CheckBoxes`.
  `InputLine::setValidator()` and the `isValidInput` / `validate` call sites are the
  glue between M3 and this milestone's validators.

---

## Task outline (build order)

1. **`Validator` abstract base** (`src/Validators/Validator.php`)
   Wire `InputLine::setValidator()`, `InputLine::isValidInput()` call after each
   keystroke, and `InputLine::valid()` delegation. Unit: base returns `true`; test
   that `InputLine` without a validator still passes.

2. **`FilterValidator`** (`src/Validators/FilterValidator.php`)
   Test: digits-only field rejects alpha; error calls `MessageBox::show()`.

3. **`RangeValidator`** (`src/Validators/RangeValidator.php`)
   Test: 1–31 day field; headless setData/getData round-trips `int ↔ string`
   via `transfer()`. Mirrors `validator.cc` date example.

4. **`LookupValidator` + `StringLookupValidator`** (`src/Validators/`)
   Test: month-name lookup against a `StringCollection`; `newStringList()` swap.

5. **`PictureValidator`** (`src/Validators/PictureValidator.php`)
   Port the recursive-descent `picture()` engine (scan/group/iteration/checkComplete);
   `PicResult` enum. Test: `"##/##/####"` fixed date, `"*#"` variable-length,
   `"&&"` two-letter. This is the most complex single class in the milestone.

6. **`validator.php` acceptance example** (`examples/php/tutorial/validator.php`)
   PHP translation of `validator.cc`; headless snapshot test of dialog layout and
   rejection behavior.

7. **`Indicator`** (`src/Editors/Indicator.php`)
   `View` subclass; `setValue(Point $location, bool $modified)` triggers `drawView()`.
   Test: headless draw shows `Ln 1  Col 1` and modified star `*`.

8. **`Editor` core — buffer + cursor + movement** (`src/Editors/Editor.php`, part 1)
   Gap buffer as PHP `string` (pre/gap/post split); `bufChar()`, `bufPtr()`,
   `nextChar()`, `prevChar()`, `lineStart()`, `lineEnd()`, `nextLine()`, `prevLine()`,
   `nextWord()`, `prevWord()`. Unit-test the buffer arithmetic in isolation.

9. **`Editor` — insertion, deletion, selection, undo**
   `insertBuffer()`, `insertText()`, `deleteRange()`, `deleteSelect()`, `setSelect()`,
   `setCurPtr()`, `startSelect()`, `hideSelect()`, `undo()` (single-level: `delCount`
   + `insCount`). Test: type-delete-undo cycle restores text.

10. **`Editor` — draw + scroll + event handling**
    `draw()` (calls `formatLine()` / `drawLines()`), `trackCursor()`, `scrollTo()`,
    `convertEvent()` (maps key codes to `cmCharLeft`…`cmUpdateTitle` commands),
    `handleEvent()`. Headless: insert text, scroll, snapshot matches expected layout.
    Also: `lock()`/`unlock()`/`update()`/`doUpdate()` redraw throttle; `setCmdState()`.

11. **`Editor` — clipboard + search/replace**
    `clipCopy()`, `clipCut()`, `clipPaste()`, `insertFrom()`; `search()`,
    `find()`, `replace()`, `doSearchReplace()`. Static `$clipboard`, `$editorDialog`
    hook, `$findStr`/`$replaceStr`/`$editorFlags`. Test: copy-paste between two
    `Editor` instances; find highlights match.

12. **`Memo`** (`src/Editors/Memo.php`)
    Extends `Editor`; `getData()`/`setData()`/`dataSize()` using a length-prefixed
    buffer; suppresses `Tab`. Test: round-trip through dialog `setData`/`getData`.

13. **`FileEditor`** (`src/Editors/FileEditor.php`)
    `loadFile()` reads file into buffer; `saveFile()` writes back (with `~` backup
    when flagged); `save()`/`saveAs()` invoke `$editorDialog(edSaveAs, …)`;
    dynamic buffer growth. Test: load a temp file, mutate, save, read back.

14. **`EditWindow`** (`src/Editors/EditWindow.php`)
    Extends `Window`; constructor inserts `ScrollBar` × 2 + `Indicator` + `FileEditor`;
    `getTitle()` returns filename or "Clipboard"; `close()` hides clipboard window.
    Headless: window title shows filename; `cmUpdateTitle` redraws frame.

15. **`tvedit.php` acceptance example** (`examples/php/tutorial/tvedit.php`)
    PHP port of `tvedit.cc` (`TVDemo`): full menu, new/open/save/save-as,
    find/replace dialogs, clipboard window, tile/cascade, `ChDirDialog`. Headless
    smoke test; real-terminal manual test.

16. **Collections + `SortedListBox`** (`src/Collections/`, `src/Dialogs/SortedListBox.php`)
    `SearchRec`, `FileCollection` (sorted, dirs-first compare), `DirEntry`,
    `DirCollection`. `SortedListBox` with incremental keyboard search via `getKey()`.
    Test: `FileCollection` sort order: `..` first, dirs before files, alpha within.

17. **`FileList` + `FileInfoPane`** (`src/Dialogs/FileList.php`, `FileInfoPane.php`)
    `FileList::readDirectory()` via `scandir` + `glob`; broadcasts `cmFileFocused` /
    `cmFileDoubleClicked`; `getText()` appends `/` for dirs. `FileInfoPane` renders
    size + timestamp on `cmFileFocused`.

18. **`FileInputLine`** (`src/Dialogs/FileInputLine.php`)
    Extends `InputLine`; handles `cmFileFocused` broadcast to update text from list.

19. **`FileDialog`** (`src/Dialogs/FileDialog.php`)
    Composes all file-dialog sub-views; wildcard expansion into `FileList`;
    `getFileName()` expands to absolute path; `fdXxx` button flags; `valid()` checks
    filename legality. Headless: dialog layout snapshot; open-file round-trip.

20. **`DirListBox` + `ChDirDialog`** (`src/Dialogs/DirListBox.php`, `ChDirDialog.php`)
    `DirListBox::newDirectory()` builds hierarchical `DirCollection` from cwd;
    tree-graphic prefix chars; double-click fires `cmChangeDir`. `ChDirDialog`
    wires input line + history + dir list + OK/Chdir/Revert. `valid()` checks
    `is_dir()`. Headless: dialog appears and changes `getcwd()` on acceptance.

---

## Key design decisions / risks

- **Gap buffer in PHP:** The C++ `TEditor` uses a single `char*` gap buffer (pre-gap
  text + gap hole + post-gap text). PHP strings are value types — the most faithful
  translation is three strings (`$pre`, `$gap` dummy, `$post`) or a single string
  with explicit gap offsets. A plain two-string `[$before, $after]` split at the
  cursor is simpler, correct, and sufficient for lines < `maxLineLength` (256 chars).
  A true gap buffer is faster for large files but adds complexity; decide at
  build time based on realistic file sizes.

- **Multibyte / wide-char in the editor:** The design spec defers full `wcwidth`-style
  width handling to M4. `Editor::charPos()` / `charPtr()` and `formatLine()` must use
  `mb_strlen` / `mb_substr` and a `wcwidth()` PHP equivalent for column arithmetic.
  This is a cross-milestone concern: once landed here, the same utility should be
  exposed for any view that needs grapheme-aware width (e.g. `DrawBuffer::moveStr`).
  Risk: PHP lacks a built-in `wcwidth`; we need an `ext-intl`-backed implementation
  or a lookup table for East-Asian double-width ranges.

- **Undo model:** Original TV implements single-level undo via `delCount` / `insCount`
  sentinels that record what was removed and inserted since the last cursor move.
  This is easy to port faithfully and sufficient for MVP. Multi-level undo is out of
  scope for M4.

- **`editorDialog` callback:** C++ uses a raw function pointer; PHP equivalent is a
  `callable` (closure or `[object, method]`). Store as a static property
  `Editor::$editorDialog`; default to a no-op returning `Cmd::Cancel`. The app
  registers its own closure (as `tvedit.cc` does) to wire Find/Replace/Save-As
  dialogs. This decoupling must be validated early.

- **Search/Replace dialogs:** `Editor::find()` and `replace()` call `$editorDialog`
  with `edFind` / `edReplace` codes and pass a `FindDialogRec` / `ReplaceDialogRec`
  value object as data. The app constructs and executes the dialog; the editor just
  calls the hook and reads the result. This keeps `Editor` free of dialog coupling.

- **Picture-mask parser complexity:** `TPXPictureValidator::picture()` is a
  recursive-descent engine with repetition, grouping, option, and alternation
  operators. It is the single most algorithmically dense piece of this milestone.
  Port it directly from `TValidator.cc`, translating the index/jndex pointer
  arithmetic to PHP array offsets. Fuzz with the example masks from the header
  docstring before calling done.

- **`FileCollection` sort order:** Directories sort before files; `..` sorts first
  within directories; otherwise case-insensitive lexicographic. This must be exact
  to match the acceptance oracle; encode as a comparator closure in `FileCollection`.

- **Validator error reporting:** TV's `error()` calls `messageBox()` (a free
  function). In PHP this becomes `MessageBox::show()`. The validator has no direct
  reference to its owner view, so `MessageBox::show()` must be callable as a static
  from within the validator — same as M3's `MessageBox` implementation.

- **`InputLine` validator integration point:** M3's `InputLine` needs two hooks that
  may not have been added yet: (a) `setValidator(Validator $v)` stored as a property,
  (b) `handleEvent()` calls `$validator->isValidInput()` after each keystroke and
  reverts on `false`, (c) `valid()` delegates to `$validator->validate()`. If M3
  shipped without these hooks, add them as a non-breaking extension in M4.

- **File-system portability:** `FileList::readDirectory()` uses PHP `scandir` +
  `glob`; no DOS-style drive letters needed. Hidden files (dot-files) should be
  filtered to match TV's original behavior (`FA_RDONLY` etc. map to `is_readable()`).

---

## Out of scope (later milestones)

- **M5:** `OutlineViewer`, `Outline`, `Node`; `ColorDialog` and palette selectors.
- **M6:** Help system (`HelpViewer`, `HelpWindow`, `HelpFile`, compiler); object
  streaming / `ResourceFile` / `StringList`.
- Multi-level undo (M4 delivers single-level only).
- Syntax highlighting or language-aware editing modes.
- Windows console / non-POSIX filesystem paths.
- `TextDevice` / `Terminal` TTY output view (separate from `Editor`; deferred).
- `NcursesDriver` or any driver beyond `AnsiDriver` + `HeadlessDriver`.
