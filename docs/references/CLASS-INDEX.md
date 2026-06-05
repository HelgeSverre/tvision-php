# Turbo Vision — Class Index

Every class in the Sigala TVision 0.8 port, grouped by module (header), with the
upstream one-line description (verbatim from the doxygen `annotated.html`) and a
**proposed un-prefixed PHP name**.

**Naming convention for the port:** drop the `T` prefix and lean on the namespace
(`HelgeSverre\TurboVision`) plus sub-namespaces instead. So `TView` → `View`,
`TWindow` → `Window`, `TApplication` → `Application`. Where dropping the `T`
collides with a PHP reserved word or a too-generic name, a note is given. The
proposed names are a **starting point for the design phase**, not final — sub-namespace
placement (`Views\`, `Dialogs\`, `Menus\`…) is to be decided when we shape the library.

Source of truth for behaviour: `source/tvision-0.8/lib/<header>` and matching `.cc`.
Per-class HTML reference: `sigala-site/html/classT<Name>.html`.

Counts: ~110 public classes/structs across 18 modules. Internal/helper structs are
listed under their module but marked _(internal)_.

---

## 1. Core objects & geometry — `objects.h`, `ttypes.h`

The root object and the value types everything else is expressed in.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TObject` | `TVObject` _(or drop)_ | The fundamental class — root of the hierarchy, manual memory mgmt in C++. In PHP this largely dissolves into the base object/GC. |
| `TPoint` | `Point` | Two-point screen coordinate (x, y). |
| `TRect` | `Rect` | Screen rectangular area (two `Point`s: a, b). |

## 2. Collections — `objects.h`, `tvobjs.h`

Borland's pre-STL container classes. In PHP most of these collapse into native
arrays / `ArrayObject` / `SplObjectStorage`, but the *sorted* and *streamable*
semantics matter for some views.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TNSCollection` | `Collection` (internal base) | Handles a non-streamable collection of objects. |
| `TNSSortedCollection` | `SortedCollectionBase` | Non-streamable collection sorted by a key (with or without duplicates). |
| `TCollection` | `Collection` | Streamable collection of items. |
| `TSortedCollection` | `SortedCollection` | Sorted, streamable collection of objects. |
| `TStringCollection` | `StringCollection` | Sorted list of ASCII strings. |

## 3. View system — `views.h`

The heart of the framework: the view tree, drawing, scrolling, palettes, events.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TView` | `View` | The base of all visible objects. |
| `TGroup` | `Group` | Provide the central driving power to TV — owns/draws/routes a subview tree. |
| `TFrame` | `Frame` | The frame (border + title) drawn around windows. |
| `TScrollBar` | `ScrollBar` | A scroll bar. |
| `TScroller` | `Scroller` | A scrolling virtual window onto a larger view. |
| `TListViewer` | `ListViewer` | Abstract base for list viewers (e.g. `ListBox`). |
| `TWindow` | `Window` | A framed, movable, resizable, selectable view. |
| `TWindowInit` | _(folded in)_ | Virtual base wiring constructors for `TWindow` (C++ init pattern). |
| `TPalette` | `Palette` | Create and manipulate palette (color-mapping) arrays. |
| `TDrawBuffer` | `DrawBuffer` | A video buffer — cell+attribute array used while drawing. |
| `TCommandSet` | `CommandSet` | Non-view class for handling sets of command codes (enable/disable). |
| `TEvent` | `Event` | Information about events (also declared in `system.h`). |

## 4. Application & desktop — `app.h`

The top-level program objects you subclass to make an app.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TProgram` | `Program` | The mother of `TApplication` — the core event loop + desktop/menubar/statusline owner. |
| `TApplication` | `Application` | The mother of all applications — `TProgram` + screen init/teardown. |
| `TDeskTop` | `Desktop` | The desktop: the tiled/cascaded backdrop that holds windows. |
| `TBackground` | `Background` | The default desktop background (pattern fill). |
| `TProgInit` | _(folded in)_ | Virtual base class for `TProgram` (init pattern). |
| `TDeskInit` | _(folded in)_ | Virtual base class for `TDeskTop` (init pattern). |

## 5. Dialogs & controls — `dialogs.h`

Modal dialogs and the standard widgets that live inside them.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TDialog` | `Dialog` | Non-growable child of `Window`, usually used as a modal view. |
| `TButton` | `Button` | The push-button view. |
| `TCluster` | `Cluster` | Base class of `CheckBoxes` and `RadioButtons`. |
| `TCheckBoxes` | `CheckBoxes` | Cluster of check boxes. |
| `TRadioButtons` | `RadioButtons` | Cluster of radio buttons. |
| `TMultiCheckBoxes` | `MultiCheckBoxes` | Cluster of multistate check boxes. |
| `TInputLine` | `InputLine` | A basic single-line string editor / text field. |
| `TLabel` | `Label` | A label attached to (and focusing) another view. |
| `TStaticText` | `StaticText` | Fixed, non-interactive text in a window. |
| `TParamText` | `ParamText` | Dynamic, `printf`-style parameterized text. |
| `TListBox` | `ListBox` | A list of items in one or more columns with optional scroll bar. |
| `THistory` | `History` | A pick-list icon giving access to previous entries for an input line. |
| `THistoryViewer` | `HistoryViewer` | The list viewer inside the history popup _(internal-ish)_. |
| `THistoryWindow` | `HistoryWindow` | The popup window holding a history list viewer. |
| `THistInit` | _(folded in)_ | Virtual base class for `THistoryWindow`. |
| `TSItem` | `SItem` | Non-view singly-linked list of strings (used to build cluster/list items). |
| `TListBoxRec` | _(internal)_ | Struct: `{collection, selection}` data record for a list box. |

## 6. Menus & status line — `menus.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TMenuView` | `MenuView` | Abstract base for menu bar and menu box (pull-down/pop-up). |
| `TMenuBar` | `MenuBar` | The horizontal menu bar across the top. |
| `TMenuBox` | `MenuBox` | A vertical menu box (a drop-down). |
| `TMenuPopup` | `MenuPopup` | A standalone pop-up menu (context menu). |
| `TMenu` | `Menu` | Wrapper tying together `MenuItem`/`SubMenu`/`MenuView`. |
| `TMenuItem` | `MenuItem` | An element of a menu (label, command, shortcut, optional submenu). |
| `TSubMenu` | `SubMenu` | A submenu menu-item (a labelled group of items). |
| `TStatusLine` | `StatusLine` | The context-sensitive status line, usually at the bottom. |
| `TStatusItem` | `StatusItem` | One clickable component (hint+key+command) of a status line. |
| `TStatusDef` | `StatusDef` | A help-context range mapping to a set of status items. |

## 7. Text editor — `editors.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TEditor` | `Editor` | A full text editor view (gap buffer, selection, undo, search/replace). |
| `TMemo` | `Memo` | An `Editor` sized for insertion into a dialog/form. |
| `TFileEditor` | `FileEditor` | An `Editor` bound to a file on disk. |
| `TEditWindow` | `EditWindow` | A window designed to hold a `FileEditor` (or the clipboard). |
| `TIndicator` | `Indicator` | The line:column position indicator in the editor's lower-left corner. |
| `TMemoData` | _(internal)_ | Struct: serialized contents of a `Memo`. |
| `TFindDialogRec` | _(internal)_ | Struct: "find" dialog data record. |
| `TReplaceDialogRec` | _(internal)_ | Struct: "replace" dialog data record. |

## 8. Terminal / TTY view — `textview.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TTextDevice` | `TextDevice` | Scrollable TTY-type text viewer / output device driver. |
| `TTerminal` | `Terminal` | A "dumb" terminal with buffered string reads and writes. |
| `TerminalBuf` | _(internal)_ | The circular buffer backing `TTerminal`. |
| `otstream` | `OutputTextStream` | C++ `ostream` adaptor writing into a `TextDevice`. |

## 9. Input validators — `validate.h`

Pluggable validation objects attached to `InputLine`s.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TValidator` | `Validator` | Abstract data-validation object. |
| `TFilterValidator` | `FilterValidator` | Restricts which characters may be typed into a field. |
| `TRangeValidator` | `RangeValidator` | Requires entered integers to fall within a range. |
| `TLookupValidator` | `LookupValidator` | Abstract: compares input against a list of acceptable values. |
| `TStringLookupValidator` | `StringLookupValidator` | Validates against a collection of valid strings. |
| `TPXPictureValidator` | `PictureValidator` | Validates input against a Paradox-style "picture" mask. |

## 10. Standard file/dir dialogs — `stddlg.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TFileDialog` | `FileDialog` | The open/save file dialog (input line + file list + history). |
| `TFileInputLine` | `FileInputLine` | Input line specialized for file names + wildcards. |
| `TFileList` | `FileList` | Sorted two-column list box of file names. |
| `TFileInfoPane` | `FileInfoPane` | Pane showing size/date of the selected file. |
| `TDirListBox` | `DirListBox` | List box of directories for changing the working dir. |
| `TChDirDialog` | `ChDirDialog` | The "change directory" dialog. |
| `TSortedListBox` | `SortedListBox` | A list box backed by a sorted collection. |
| `TFileCollection` | `FileCollection` | Sorted collection of file-name entries. |
| `TDirCollection` | `DirCollection` | Collection of `DirEntry` objects. |
| `TDirEntry` | `DirEntry` | A directory path + display description. |
| `TSearchRec` | _(internal)_ | Struct: a single directory-scan result. |

## 11. Color-selection dialog — `colorsel.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TColorDialog` | `ColorDialog` | Dialog to examine and change the standard palette. |
| `TColorSelector` | `ColorSelector` | Grid viewer of available colors. |
| `TMonoSelector` | `MonoSelector` | Monochrome attribute selector. |
| `TColorDisplay` | `ColorDisplay` | Live preview of the chosen color. |
| `TColorGroup` | `ColorGroup` | A named set of color items. |
| `TColorGroupList` | `ColorGroupList` | Scrollable list of color groups. |
| `TColorItem` | `ColorItem` | One named, indexed color entry. |
| `TColorItemList` | `ColorItemList` | List view of single color items. |
| `TColorIndex` | _(internal)_ | Struct mapping group/item to palette index. |

## 12. Outline / tree viewer — `outline.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TOutlineViewer` | `OutlineViewer` | Abstract expandable/collapsible outline (tree) viewer. |
| `TOutline` | `Outline` | Concrete outline viewer over a `Node` tree. |
| `TNode` | `Node` | A node of the outline (text, children, expanded flag). |

## 13. Help system — `help.h`, `helpbase.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `THelpViewer` | `HelpViewer` | The view that renders a help topic with cross-references. |
| `THelpWindow` | `HelpWindow` | The window wrapping a `HelpViewer`. |
| `THelpFile` | `HelpFile` | Reader for the compiled binary help file format. |
| `THelpTopic` | `HelpTopic` | A single help topic (text + cross-refs). |
| `THelpIndex` | `HelpIndex` | Topic-offset index into a help file. |
| `TParagraph` | `Paragraph` | A wrapped paragraph of help text. |
| `TCrossRef` | `CrossRef` | A hyperlink cross-reference within help text. |

> The help **compiler** (`tvhc`, source in `source/tvision-0.8/tvhc/`) turns a
> `.txt` help source into the binary `.h32` format the viewer reads. Worth porting
> as a CLI tool, or replacing with a simpler PHP-native format.

## 14. Message boxes — `msgbox.h`

| TV symbol | Proposed PHP | Description |
|-----------|-------------|-------------|
| `messageBox()` family (functions) | `MessageBox::show()` etc. | Free functions for standard info/warning/error/confirm dialogs. |
| `MsgBoxText` | `MsgBoxText` | The set of standard localizable button/label strings. |

## 15. Resources & string lists — `resource.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TResourceFile` | `ResourceFile` | A stream indexable by string keys — load views/objects by name. |
| `TResourceCollection` | `ResourceCollection` | Sorted, streamable collection of resource index entries. |
| `TResourceItem` | _(internal)_ | Struct: one key→offset resource entry. |
| `TStringList` | `StringList` | Read-only access to indexed strings stored on a stream. |
| `TStrListMaker` | `StringListMaker` | Builder that writes a `StringList` to a stream. |
| `TStrIndexRec` | _(internal)_ | Struct: string-id range → offset record. |

## 16. Object streaming / persistence — `tobjstrm.h`

Borland's pre-`serialize` object persistence layer. A view tree (or any
`TStreamable`) can be written to / read from a stream. In PHP this maps onto
`__serialize`/`Serializable` or a custom registry — a key design decision.

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TStreamable` | `Streamable` (interface) | Gives the streamable property to a class. |
| `TStreamableClass` | `StreamableClass` | Registration record (name + builder) for a streamable type. |
| `TStreamableTypes` | `StreamableTypes` | Registry of all registered streamable types. |
| `pstream` | `PStream` | Base for stream objects. |
| `ipstream` / `opstream` / `iopstream` | `InStream` / `OutStream` / `IoStream` | Read / write / read-write streamable objects. |
| `fpbase` | _(internal)_ | Base for file-backed streams. |
| `ifpstream` / `ofpstream` / `fpstream` | `FileInStream` / `FileOutStream` / `FileStream` | File-backed read / write / read-write streams. |
| `TPWrittenObjects` / `TPWObj` | _(internal)_ | Dedup table of already-written objects. |
| `TPReadObjects` | _(internal)_ | Dedup table of already-read objects. |
| `fLink` | _(internal)_ | Linked-list node used by the streamer. |

## 17. System drivers — `system.h`

The platform layer: screen, keyboard, mouse, the event queue. **This is the part
that needs the most re-imagining for PHP** (ncurses/VCS/Gpm → a PHP terminal
backend such as raw ANSI/termios, an FFI ncurses, or a driver abstraction).

| TV class / struct | Proposed PHP | Description |
|-------------------|-------------|-------------|
| `TScreen` | `Screen` | The interface to the system screen (size, buffer, refresh). |
| `TDisplay` | `Display` | Low-level display info/capabilities. |
| `TEventQueue` | `EventQueue` | Mouse/event queue and polling. |
| `TSystemError` | `SystemError` | Critical-error / signal handling. |
| `TEvent` | `Event` | Tagged union of key/mouse/message/command events. |
| `KeyDownEvent` | `KeyDownEvent` | Key-press payload (scan code + char + shift state). |
| `MouseEventType` | `MouseEvent` | Mouse payload (where, buttons, double-click). |
| `MessageEvent` | `MessageEvent` | Broadcast/command message payload (command + info ptr). |
| `CharScanType` | `CharScan` | `{charCode, scanCode}` pair. |

## 18. Memory manager — `buffers.h`

| TV class | Proposed PHP | Description |
|----------|-------------|-------------|
| `TVMemMgr` | _(drop)_ | The cache/buffer memory manager. Largely irrelevant under PHP's GC. |
| `TBufListEntry` | _(drop)_ | Cache buffer list entry. |

---

## Cross-cutting symbol groups (not classes, but we must port them)

These live as `#define`/`const`/enum across the headers and are part of the public API:

- **Commands** (`cmXxx`, e.g. `cmQuit`, `cmOK`, `cmCancel`, `cmClose`, `cmZoom`) — `tvobjs.h`, app/menu headers.
- **Key codes** (`kbXxx`, e.g. `kbEnter`, `kbEsc`, `kbF1`, `kbCtrlC`, `kbAltX`) — `tkeys.h`.
- **Event masks** (`evMouseDown`, `evKeyDown`, `evCommand`, `evBroadcast`, …) — `system.h`/`views.h`.
- **View state flags** (`sfVisible`, `sfActive`, `sfFocused`, `sfSelected`, `sfDragging`, `sfModal`, …) — `views.h`.
- **View option flags** (`ofSelectable`, `ofTopSelect`, `ofFramed`, `ofPreProcess`, `ofPostProcess`, `ofCentered`, …) — `views.h`.
- **Grow modes** (`gfGrowLoX`, `gfGrowHiX`, `gfGrowAll`, …) and **drag modes** (`dmDragMove`, `dmLimitAll`, …) — `views.h`.
- **Help contexts** (`hcXxx`) — application-defined.
- **Standard palettes** (`cpColor`, `cpBlackWhite`, `cpMonochrome` + per-view palette index constants) — `views.h` and each view.
- **Color attribute byte** (4-bit fg + 4-bit bg) conventions — `system.h`.

These constant families are as important to the public API as the classes; the
design phase should decide whether they become PHP `enum`s, class constants, or
`const` in the namespace root.

---

## Notes for the design phase (deferred — do not act yet)

- **`TObject` / `*Init` / `TVMemMgr` mostly dissolve** under PHP's object model and GC.
- **Collections → native arrays / `Spl*`** in most places; keep an explicit
  `SortedCollection` only where views rely on sorted+indexed semantics.
- **Streaming (`tobjstrm.h`) is a fork in the road:** faithfully port the binary
  streamer, or replace persistence with idiomatic PHP serialization. Affects
  `ResourceFile`, `StringList`, and "load/save desktop" features.
- **`system.h` is the real porting work:** everything above it is portable logic;
  the screen/keyboard/mouse driver is where ncurses/VCS/Gpm must become a PHP
  terminal backend. Recommend a `Drivers\` interface so the core stays pure.
- **`T`-prefix dropped** per project style; final sub-namespace layout TBD.
