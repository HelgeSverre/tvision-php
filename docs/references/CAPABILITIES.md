# Turbo Vision — Capabilities Overview

A narrative tour of *what the library actually does*, so the PHP port can be scoped
against real capabilities rather than a class list. Each section points at the
classes (see `CLASS-INDEX.md`) and source (`source/tvision-0.8/lib/`) that provide it.

## 1. The application skeleton & event loop

Every TV program is a subclass of `TApplication` (which is `TProgram` + screen
setup). `TApplication` owns three standard children — a **menu bar** across the top,
a **status line** across the bottom, and a **desktop** filling the middle — and runs
the **event loop**:

```
getEvent → handleEvent (route down the focused view chain) → idle → repeat
```

`run()` pumps events until a `cmQuit` command arrives. The same loop dispatches
keyboard, mouse, command, and broadcast events. This is the spine of the whole
framework and the first thing to stand up in PHP.

- Classes: `TApplication`, `TProgram`, `TDeskTop`, `TBackground`.
- Source: `app.h`, `TProgram.cc`, `TApplication.cc`, `system.cc`.
- Smallest example: `examples/cpp/tutorial/tvguid01.cc`.

## 2. The view hierarchy & drawing model

Everything visible is a `TView` — a rectangular region that knows how to `draw()`
itself into a `TDrawBuffer` (a cells+attributes array), handle events, and report a
desired size/position. Views compose into `TGroup`s (the desktop, windows and
dialogs are all groups), forming a tree. Key mechanics the port must reproduce:

- **Bounds & coordinates** via `TPoint`/`TRect`; views own their `origin`/`size`.
- **Z-order, clipping and partial redraw** — groups clip children, `drawView()` only
  repaints what's exposed.
- **State flags** (`sfVisible`, `sfActive`, `sfFocused`, `sfSelected`, `sfDragging`,
  `sfModal`, …) toggled via `setState()`.
- **Option flags** (`ofSelectable`, `ofTopSelect`, `ofFramed`, `ofPreProcess`,
  `ofPostProcess`, `ofCentered`, …).
- **Grow/drag modes** so views reflow when their owner resizes.
- **Palettes** — each view maps its logical colors through its owner's palette up to
  the application's root palette, giving consistent theming.

- Classes: `TView`, `TGroup`, `TFrame`, `TDrawBuffer`, `TPalette`.
- Source: `views.h` (the 130 KB core), `TView.cc`, `TGroup.cc`, `TFrame.cc`.
- Custom-view examples: `tvguid05.cc`, `load.cc`.

## 3. Windows, frames, scrolling

`TWindow` is a framed, movable, resizable, closable, zoomable, selectable group with
a numbered title. `TFrame` draws the border/title/icons. Scrolling is provided by
`TScrollBar` + `TScroller` (a viewport onto a larger logical area) and `TListViewer`
(a scrollable, multi-column, keyboard/mouse-navigable item list).

- Classes: `TWindow`, `TFrame`, `TScrollBar`, `TScroller`, `TListViewer`.
- Source: `views.h`, `TWindow.cc`, `TScrollBar.cc`, `TScroller.cc`, `TListViewer.cc`.
- Progressive examples: `tvguid06`–`tvguid10` (scrolling, scroll bars, multiple panes, resize).

## 4. Menus & status line

A full menu system: a horizontal `TMenuBar`, drop-down `TMenuBox`es, context
`TMenuPopup`s, built from nested `TMenuItem`/`TSubMenu` definitions with shortcuts,
hotkeys, disabled state, and help contexts. The `TStatusLine` shows context-sensitive
hints + clickable key bindings that change based on the active help context.

- Classes: `TMenuBar`, `TMenuBox`, `TMenuPopup`, `TMenu`, `TMenuItem`, `TSubMenu`,
  `TStatusLine`, `TStatusItem`, `TStatusDef`, `TMenuView`.
- Source: `menus.h`, `TMenuBar.cc`, `TMenuBox.cc`, `TMenuView.cc`, `TStatusLine.cc`.
- Examples: `tvguid02` (status line commands), `tvguid03` (both menu + status).

## 5. Dialogs & the standard control set

`TDialog` (a non-growable window, usually run **modally** via `execView()`) hosts the
widget toolkit:

- **Buttons** (`TButton`) — default/normal/broadcast styles.
- **Text fields** (`TInputLine`) with optional **validators** and **history** drop-downs.
- **Clusters**: `TCheckBoxes`, `TRadioButtons`, `TMultiCheckBoxes`.
- **Labels** (`TLabel`) that focus their associated control via hotkey.
- **Static / parameterized text** (`TStaticText`, `TParamText`).
- **List boxes** (`TListBox`) backed by collections, with scroll bars.

Dialogs support **`setData()`/`getData()`** — reading a whole dialog's contents into
/ out of a record struct in one call (the basis of forms).

- Classes: `TDialog`, `TButton`, `TInputLine`, `TLabel`, `TStaticText`, `TParamText`,
  `TCluster`, `TCheckBoxes`, `TRadioButtons`, `TMultiCheckBoxes`, `TListBox`, `THistory`.
- Source: `dialogs.h`, `TDialog.cc`, `TButton.cc`, `TInputLine.cc`, `TCluster.cc`, …
- Examples: `tvguid11`–`tvguid16` (modal dialog → buttons → checkboxes/radio/labels →
  input line → save/restore data), `nomenus.cc`, `listbox.cc`.

## 6. Input validation

Pluggable `TValidator` objects attach to input lines to constrain/verify typed data:
character **filters**, integer **ranges**, **lookup** against a value list, and
Paradox-style **picture masks** (formatted input like phone numbers / dates).

- Classes: `TValidator`, `TFilterValidator`, `TRangeValidator`, `TLookupValidator`,
  `TStringLookupValidator`, `TPXPictureValidator`.
- Source: `validate.h`, `TValidator.cc`.
- Example: `validator.cc`.

## 7. Text editor

A genuinely capable multi-line editor: `TEditor` provides a gap/edit buffer,
selection, clipboard, undo, search & replace, word-wrap, and scrolling. `TFileEditor`
binds it to a file; `TEditWindow` wraps it in a window with scroll bars and a
line:column `TIndicator`; `TMemo` is the dialog-embeddable variant.

- Classes: `TEditor`, `TFileEditor`, `TEditWindow`, `TMemo`, `TIndicator`.
- Source: `editors.h`, `TEditor.cc` (30 KB), `TFileEditor.cc`.
- Example: `tvedit.cc` (a working text editor in ~12 KB).

## 8. Standard file & directory dialogs

Ready-made `TFileDialog` (open/save with wildcard input line, sorted two-column file
list, and history) and `TChDirDialog` (change-directory tree), built on
`TDirListBox`/`TFileList`/`TFileCollection`/`TDirCollection`.

- Classes: `TFileDialog`, `TFileInputLine`, `TFileList`, `TFileInfoPane`,
  `TChDirDialog`, `TDirListBox`, `TSortedListBox`.
- Source: `stddlg.h`, `TFileDialog.cc`, `TChDirDialog.cc`, `TFileList.cc`.

## 9. Outline / tree viewer

`TOutlineViewer`/`TOutline` render an expandable/collapsible tree (e.g. a directory
tree) over a `TNode` graph — added by the port beyond Borland's original set.

- Classes: `TOutlineViewer`, `TOutline`, `TNode`. Source: `outline.h`, `TOutline.cc`.

## 10. Message boxes

One-call standard dialogs (`messageBox`, `messageBoxRect`, `inputBox`) for
information / warning / error / confirmation, with localizable button text via
`MsgBoxText`.

- Source: `msgbox.h`, `msgbox.cc`.

## 11. Color & palette customization

A whole `TColorDialog` lets the user (or app) inspect and remap the palette
interactively — color groups, item lists, live preview, and a monochrome selector.

- Classes: `TColorDialog`, `TColorSelector`, `TMonoSelector`, `TColorDisplay`,
  `TColorGroup`, `TColorGroupList`, `TColorItem(List)`.
- Source: `colorsel.h`, `colorsel.cc`.

## 12. Online help system

A hyperlinked, context-sensitive help system: `THelpFile` reads a compiled binary
help database; `THelpViewer`/`THelpWindow` render topics with `TCrossRef`
cross-references; the standalone `tvhc` help compiler turns a `.txt` source into the
binary format. Help contexts (`hcXxx`) tie views/menus/status-line to topics.

- Classes: `THelpViewer`, `THelpWindow`, `THelpFile`, `THelpTopic`, `THelpIndex`,
  `TParagraph`, `TCrossRef`.
- Source: `help.h`, `helpbase.h`, `tvhc/` (the compiler), `demo/DEMOHELP.*`.

## 13. Object streaming (persistence)

A pre-STL serialization layer: any `TStreamable` (which includes the whole view
tree) can be written to and read back from a `pstream`. This underpins **resource
files** (`TResourceFile` — load named views/dialogs by key) and **string lists**
(`TStringList`/`TStrListMaker` — externalized, indexable strings, an i18n primitive).

- Classes: `TStreamable`, `TStreamableTypes`, `ipstream`/`opstream` family,
  `TResourceFile`, `TResourceCollection`, `TStringList`, `TStrListMaker`.
- Source: `tobjstrm.h`, `tobjstrm.cc`, `resource.h`, `TResourceFile.cc`.
- Example: `load.cc` (build/stream custom views).

## 14. Terminal / TTY output view

`TTextDevice`/`TTerminal` give a scrollback TTY view you can write to like a stream
(`otstream`) — useful for log/console panes inside the UI.

- Source: `textview.h`, `textview.cc`, `tvtext.cc`.

## 15. The system/driver layer (the porting frontier)

Underneath everything sits the platform layer that the PHP port has to reinvent:

- **Screen** output via `ncurses`, or — on the Linux console — direct VCS/VCSA memory
  for speed and full 256-char/color rendering. `TScreen`/`TDisplay`.
- **Keyboard** input via ncurses, with arrow/function-key decoding and (console-only)
  Alt/Ctrl/Shift state detection. `tkeys.h`.
- **Mouse** via Gpm (Linux) or `moused` (FreeBSD); buttons, motion, double-click.
  `TEventQueue`.
- **Events** unified into `TEvent` (key / mouse / message / nothing).
- **Resize** handling via `SIGWINCH` (the UI reflows live).
- **Env vars** `TVLOG` (logging) and `TVOPT` (driver toggles).

- Source: `system.h`, `system.cc` (62 KB — the biggest platform file), `drivers.cc`.
- Details: `installation-handbook.md` (keyboard / screen / mouse / env-var chapters).

---

## Capability checklist (for scoping the PHP port)

| Capability | Core classes | Port priority (suggested) |
|------------|-------------|---------------------------|
| Event loop + application skeleton | Application, Program, Desktop | **P0** — nothing works without it |
| View tree + draw buffer + palettes | View, Group, DrawBuffer, Palette | **P0** |
| Terminal driver (screen/keyboard/mouse) | Screen, Display, EventQueue, Event | **P0** — the real new work |
| Windows, frames, scrolling | Window, Frame, ScrollBar, Scroller | **P1** |
| Menus + status line | MenuBar, MenuBox, StatusLine | **P1** |
| Dialogs + core controls | Dialog, Button, InputLine, clusters, Label, StaticText | **P1** |
| List viewing | ListViewer, ListBox | **P1** |
| Message boxes | MessageBox helpers | **P1** |
| Validators | Validator + subclasses | **P2** |
| Text editor | Editor, FileEditor, EditWindow, Memo | **P2** |
| File/dir dialogs | FileDialog, ChDirDialog | **P2** |
| Outline/tree viewer | OutlineViewer, Outline, Node | **P2** |
| Color/palette dialog | ColorDialog + selectors | **P3** |
| Help system + compiler | HelpViewer, HelpFile, tvhc | **P3** |
| Object streaming + resources + string lists | Streamable, ResourceFile, StringList | **P3** (or replace with PHP-native serialization) |
| Memory manager | TVMemMgr | **drop** (PHP GC) |

Priorities are a *starting hypothesis* for the design discussion, not a committed plan.
