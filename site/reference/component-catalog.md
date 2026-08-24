# Component catalog

Use this page to choose a visible building block. Constructors and full behavioral contracts are in [Views and controls](./views-and-controls); this catalog emphasizes when to use each component and the methods normally used first.

## Application surfaces

| Component | Use it for | Start with |
| --- | --- | --- |
| `Application` | A complete program with terminal lifecycle, desktop, menu, and status line | `run()`, `initDeskTop()`, `initMenuBar()`, `initStatusLine()`, `handleEvent()` |
| `Desktop` | The workspace that owns and orders top-level windows | `insertWindow()`, `current()`, `tile()`, `cascade()` |
| `Window` | A movable, resizable, zoomable, closable surface | `insert()`, `setTitle()`, `setFlags()`, `standardScrollBar()` |
| `Dialog` | A window-shaped form, usually executed modally | `insert()`, `setData()`, `getData()`, `valid()` |
| `Group` | A custom owner and event-routing scope for child views | `insert()`, `remove()`, `setCurrent()`, `execView()` |
| `View` | A custom visible or interactive component | `draw()`, `handleEvent()`, `setState()`, `drawView()` |

Prefer `Program::insertWindow()` from application code because it performs focus and validity checks. Use `executeDialog()` for modal work.

## Text, labels, and actions

| Component | Use it for | Start with |
| --- | --- | --- |
| `StaticText` | Wrapped read-only text with left, center, or right alignment | constructor, `centered()`, `rightAligned()` |
| `ParamText` | Read-only text updated from a format string | `setText()` |
| `Label` | A caption whose mnemonic focuses a linked control | constructor with the linked `View` |
| `Button` | A command action in a dialog or window | constructor with command and `ButtonFlag` |
| `Background` | A repeated desktop or panel fill pattern | constructor |
| `Frame` | A window border, title, controls, and shadow | normally created by `Window`; override `initFrame()` only for custom frames |

`~X~` marks a mnemonic in labels, buttons, menu items, and status text.

## Input and choices

| Component | Use it for | Start with |
| --- | --- | --- |
| `InputLine` | A single editable field, optionally validated | `text()`, `setText()`, `getData()`, `setData()` |
| `FileInputLine` | A path input inside file-selection workflows | the `InputLine` API |
| `CheckBoxes` | Independent yes/no choices encoded as a bit mask | `getData()`, `setData()` |
| `MultiCheckBoxes` | Choices with more than two states per item | `getData()`, `setData()` |
| `RadioButtons` | One choice from a fixed set, encoded as an index | `getData()`, `setData()` |
| `History` | A button/dropdown that recalls earlier input values | link it to an `InputLine` and history id |

Attach a validator to `InputLine` for filtered characters, numeric ranges, lookup values, or picture masks. The [dialogs recipe](/cookbook/dialogs-and-forms) shows validation and commit behavior.

## Collections and navigation

| Component | Use it for | Start with |
| --- | --- | --- |
| `ScrollBar` | A horizontal or vertical numeric range | `setParams()`, `setRange()`, `setValue()` |
| `Scroller` | A custom viewport over a larger logical surface | subclass `draw()`, then `setLimit()`, `scrollTo()` |
| `ListBox` | Selectable strings, optionally in columns | `newList()`, `list()`, `getData()` |
| `SortedListBox` | A string list with incremental prefix search | the `ListBox` API, `searchTerm()` |
| `ListViewer` | A list backed by your own model | implement `getText()`, call `setRange()` |
| `Outline` | A tree already represented by `Node` values | constructor with root node |
| `OutlineViewer` | A tree backed by your own hierarchical model | implement node access and expansion methods |

See [Use scrolling, lists, and editors](/cookbook/scrolling-lists-and-editors) for complete compositions.

## Menus and status

| Component | Use it for | Start with |
| --- | --- | --- |
| `MenuBar` | Persistent top-level pull-down menus | `SubMenu::items()`, `MenuItem`, `dismissPopup()` |
| `MenuPopup` | A temporary context menu at an application-chosen position | construct with bounds and a `Menu`, then pass it to `Group::execView()` |
| `MenuItem` | A command, shortcut, submenu host, or separator | constructor, `separator()` |
| `SubMenu` | A named group of menu entries | `items()`, `menu()` |
| `StatusLine` | Visible shortcuts and context-sensitive hints | `StatusDef::all()`, `setHelpContext()`, `setHintProvider()` |

Menus and status items emit the same integer command events as buttons and custom views. Command enablement automatically affects their active state.

## Editors and text devices

| Component | Use it for | Start with |
| --- | --- | --- |
| `Editor` | An in-memory multiline editor | `text()`, `setText()`, `setSelect()` |
| `Memo` | Editable multiline text that participates in dialog data transfer | `getData()`, `setData()` |
| `FileEditor` | A UTF-8 file-backed editor with explicit failure reporting | `loadFile()`, `save()`, `saveAs()`, `lastError` |
| `EditWindow` | A ready-made file editor window with bars and indicator | `editor`, `setCloseResolver()` |
| `Indicator` | Cursor/modified-state display for an editor | normally composed by `EditWindow` |
| `Terminal` | A bounded, scrollable text output surface | `write()` or `output()` |
| `TextDevice` | A custom scrollable append-only text surface | extend for specialized output behavior |

## Standard dialogs

| Component | Use it for | Start with |
| --- | --- | --- |
| `MessageBox` | Information, warning, error, confirmation, or short text prompt | `show()` with `MsgBoxFlag`, or `input()` |
| `FileDialog` | Open, replace, or select a path with wildcard navigation | `getFileName()`, result `FileCommand` |
| `ChDirDialog` | Interactive current-directory selection | execute it modally and inspect the result |
| `ColorDialog` | Edit the application's palette | construct with a `Palette` and color groups; call `commit()` only on acceptance |

`FileList`, `DirListBox`, `FileInfoPane`, `HistoryViewer`, and `HistoryWindow` are reusable pieces behind the standard file and history workflows. Reach for them when customizing those dialogs rather than starting from a bare `ListViewer`.

## Help surfaces

| Component | Use it for | Start with |
| --- | --- | --- |
| `HelpWindow` | A standard context-help window | construct with a `HelpFile` and context id |
| `HelpViewer` | Scrollable topic display with cross-reference navigation | `switchToTopic()` through the help workflow |
| `HelpFile` | Read or write compiled help topics | `load()`, topic lookup, `save()` |
| `HelpCompiler` | Compile authored help input for distribution | command-line compiler or class API |

Applications normally override `createHelpView()` and let `Cmd::Help` use the focused view's help context. See [Add compiled help and safe persistence](/cookbook/help-and-persistence).

## Which base class should I extend?

| Need | Extend |
| --- | --- |
| Paint one custom surface or handle local input | `View` |
| Own several cooperating child controls | `Group` or, for a top-level surface, `Window` |
| Paint a logical area larger than its rectangle | `Scroller` |
| Present rows from a custom model | `ListViewer` |
| Present a hierarchy from a custom model | `OutlineViewer` |
| Build an application entry point | `Application` |

Composition is usually preferable to subclassing a concrete control. Subclass where the base class exposes the behavior you need to specialize; otherwise place standard controls inside a window or group and communicate with commands.
