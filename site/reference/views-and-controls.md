# Views and controls

`View` is the base class for every visible object. A `Group` owns an ordered view tree; later children are drawn above earlier children. Bounds are `Rect` values in owner-local cell coordinates, with an exclusive bottom-right corner.

```php
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\StaticText;

$caption = new StaticText(Rect::of(2, 1, 38, 3), 'Ready');
$caption->growMode = State::GrowHiX;
$owner->insert($caption);
```

## Core view contract

| Member | Contract |
| --- | --- |
| `__construct(Rect $bounds)` | Creates a view. Extents must be non-negative and within the drawable-cell limit. |
| `getBounds(): Rect` / `getExtent(): Rect` | Returns owner-local bounds / the same size translated to `(0, 0)`. |
| `locate(Rect $bounds)` | Moves and resizes through `sizeLimits()`; redraws the old and new area when owned. |
| `moveTo(int $x, int $y)` / `growTo(int $width, int $height)` | Preserves size / origin respectively. |
| `setState(int $flag, bool $enable)` | Changes state bits and performs focus, cursor, and visibility notifications. Use this rather than mutating `state` when side effects are required. |
| `focus(): bool` / `select()` | Requests focus from the owner; a view must be visible, enabled, owned, and `Selectable`. |
| `draw()` / `drawView()` | Override `draw()` to paint; use `drawView()` to repaint a visible, exposed view. |
| `handleEvent(Event $event)` | Event extension point. Clear an event after handling it. |
| `valid(int $command): bool` | Returns whether a modal operation may finish. The base implementation returns `true`. |
| `dataSize()` / `getData()` / `setData(mixed $data)` | Form-data contract. The base view transfers no datum. |
| `getPalette()` / `getColor(int $color)` | Return a local palette or resolve a logical palette color through the owner chain. |

### Drawing and coordinates

`draw()` receives no canvas argument. Paint in local coordinates with `writeStr()`, `writeChar()`, `writeLine()`, `writeBuf()`, or `DrawBuffer`; every write is clipped to the view and its ancestors. `absoluteOrigin()`, `makeLocal(Point $global)`, and `localToGlobal(Point $local)` convert coordinates. `mouseInView()` and `containsMouse()` test root-relative mouse positions.

Use `setCursor()`, `showCursor()`, and `hideCursor()` to manage a focused control's terminal cursor. The cursor is shown only when the view is visible, focused, and has `State::CursorVis`. `blockCursor()` and `normalCursor()` set or clear `State::CursorIns` for application insert/overwrite state; they do not select a hardware cursor shape.

### State, options, and grow mode

These are bit flags. `state`, `options`, and `growMode` are separate flag words; do not combine values from different families.

| Family | Constants | Use |
| --- | --- | --- |
| State | `Visible`, `CursorVis`, `CursorIns`, `Shadow`, `Active`, `Selected`, `Focused`, `Dragging`, `Disabled`, `Modal`, `Default`, `Exposed` | Runtime presentation and interaction state. |
| Options | `Selectable`, `TopSelect`, `FirstClick`, `Framed`, `PreProcess`, `PostProcess`, `Buffered`, `Tileable`, `CenterX`, `CenterY`, `Centered`, `Validate` | Focus eligibility, routing, insertion, and desktop layout behavior. |
| Grow mode | `GrowLoX`, `GrowLoY`, `GrowHiX`, `GrowHiY`, `GrowAll`, `GrowRel`, `GrowFixed` | Edges affected when an owning group changes size. `GrowRel` scales selected edges proportionally. |

`CenterX`, `CenterY`, and `Centered` are applied when the view is inserted. `TopSelect` brings a selected view forward. A first click focuses a selectable child; it is delivered only when that child has `FirstClick`. `PreProcess` children receive focused keyboard/command events before the current child; `PostProcess` children receive them afterwards. `Tileable` is required for `Desktop::tile()` and `Desktop::cascade()`.

## Containers and windows

### `Group`

`Group` is the standard owner for child views.

| Method | Contract |
| --- | --- |
| `insert(View $view)` | Adds an unowned child. The first visible, enabled selectable child becomes current. Cycles and already-owned views are rejected. |
| `remove(View $view)` | Detaches a child, selects an eligible replacement when necessary, and redraws exposed content. |
| `subviews(): list<View>` / `current(): ?View` | Returns children in insertion/Z order and the current child. |
| `setCurrent(?View $view)` | Selects a child or clears current focus. |
| `selectNext()` / `selectPrevious()` | Cycles focus among eligible selectable children. |
| `lock()` / `unlock()` | Defers group drawing; nested locks are supported. |
| `execView(View $modal): int` | Inserts if necessary, executes a modal loop, removes the view if it was inserted, and returns its ending command. |
| `endModal(int $command)` | Requests completion of the active modal scope after validation. |
| `putEvent(Event $event)` | Queues an event on the root group/program for later dispatch. |

Groups reflow each child through `calcBounds()` when their own bounds change. `getData()` returns transferable child values in insertion order; `setData()` accepts the matching indexed array.

### `Desktop`

`Desktop` is a `Group` with a `Background` inserted first. Use `insertWindow(Window $window)` to add and select a window. `selectWindow()` raises a top-select window. `Cmd::Next` and `Cmd::Prev` cycle selectable windows. `tile(Rect $bounds)` and `cascade(Rect $bounds)` operate only on visible children with `State::Tileable`; `tileColumnsFirst` selects column-first tiling.

### `Window` and `Frame`

```php
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

$window = new Window(Rect::of(4, 2, 58, 18), 'Search results', 1);
$window->setFlags(WindowFlags::Move | WindowFlags::Close | WindowFlags::Zoom);
$window->options |= State::Tileable;
$desktop->insertWindow($window);
```

`Window::__construct(Rect $bounds, string $title = '', int $number = 0)` creates a selectable, top-select window with a frame, shadow, and proportional grow mode. Its minimum size is `16 × 6`; an owned window cannot exceed its owner's extent.

| API | Contract |
| --- | --- |
| `setTitle()` / `getTitle()` | Changes or returns the frame title. |
| `setNumber()` | Sets the window number shown by the frame. |
| `setFlags()` | Sets `WindowFlags::Move`, `Grow`, `Close`, and/or `Zoom`. `Default` contains all four. |
| `setPalette(int $index)` | Selects a `WindowPalette` (`Blue`, `Cyan`, or `Gray`). |
| `standardScrollBar(ScrollBarOrientation|int $orientation, bool $handleKeyboard = false)` | Inserts a standard edge bar. With an integer, `ScrollBarPart::Vertical` and `HandleKeyboard` compatibility bits are accepted. `handleKeyboard: true` adds `PostProcess`, so the bar receives focused keyboard events after the current child. |

`Frame` is inserted automatically as the first child and paints the border, title, controls, and shadow. It routes title-bar and border gestures to its `Window` owner. Do not insert another frame unless overriding `initFrame()` in a window subclass.

### `Dialog`

`Dialog::__construct(Rect $bounds, string $title = '')` is a gray `Window` with `Move | Close`, no grow mode, and modal command handling. `Escape` queues `Cmd::Cancel`; `Enter` broadcasts `Cmd::Default`. While modal, `Cmd::Ok`, `Cancel`, `Yes`, and `No` end the dialog. Cancellation bypasses child validation; other completion commands call `valid()` on all children.

<DocCapture
  src="/captures/reference/core-dialog-controls.png"
  alt="A gray Build options dialog containing a labelled input line, checkboxes, radio buttons, and action buttons"
  caption="A dialog composes ordinary views: labels focus their linked field, cluster controls show their selected values, and a default button receives the default command."
/>

## Basic display views

| Type | Constructor and behavior |
| --- | --- |
| `Background` | `new Background(Rect $bounds, string $pattern = '░')`. Fills its entire extent using the pattern. |
| `StaticText` | `new StaticText(Rect $bounds, string $text, TextAlignment $alignment = TextAlignment::Left)`. Wraps text to width. `StaticText::centered()` and `::rightAligned()` are shortcuts; a leading `"\003"` also requests centered text. |
| `ParamText` | `new ParamText(Rect $bounds, string $text = '')`. Use `setText(string $format, string|int|float|bool|null ...$args)` to format and repaint. |
| `Label` | `new Label(Rect $bounds, string $label, ?View $link = null)`. `~X~` marks a mnemonic; matching `Alt+X` focuses the linked view. |

## Menus and status lines

Menu and status entries carry a command integer. Enabled state is resolved through the owning application's command set, so a `Cmd::CommandSetChanged` broadcast redraws menu and status views.

### Menu data

```php
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;

$file = (new SubMenu('~F~ile', Key::AltF))
    ->items(
        new MenuItem('~O~pen', Cmd::Open, Key::F3, 'Open a file'),
        MenuItem::separator(),
        new MenuItem('E~x~it', Cmd::Quit, Key::AltX),
    );

$menuBar = new MenuBar(Rect::of(0, 0, 80, 1), $file);
```

| Type | Constructor and operations |
| --- | --- |
| `MenuItem` | `new MenuItem(string $name, int $command, ?Key $key = null, string $help = '', ?Menu $subMenu = null, int $helpCtx = 0)`. The `~X~` marker highlights a mnemonic. Use `MenuItem::separator()` for a non-command divider. A submenu host has a non-null `$subMenu`; ordinary actionable items have a non-zero command. |
| `Menu` | `new Menu(array $items = [])`; `Menu::of(SubMenu ...$subMenus)` builds top-level items. Use `add(MenuItem $item)` and `items(): array`. |
| `SubMenu` | `new SubMenu(string $name, ?Key $key = null, int $helpCtx = 0)`. Append `MenuItem` and nested `SubMenu` values with `items(...$items)`; it returns the same `SubMenu`. `menu()` returns its `Menu`; `toMenuItem()` lowers it to a submenu entry. |

`MenuBar::__construct(Rect $bounds, SubMenu|Menu ...$menus)` accepts individual top-level submenus and/or complete menus. It has `PreProcess` routing and grows along the high X edge.

| `MenuBar` operation | Contract |
| --- | --- |
| `menu(): Menu` | Returns the merged top-level menu. |
| `activeIndex(): int` | Returns the active top-level index, or `-1` while closed. |
| `selectedIndex(): int` | Returns the selection in the deepest open menu box, or `0` when none is open. |
| `activeSubMenu(): ?Menu` | Returns the currently active top-level submenu. |
| `openBoxes(): array` | Returns open `MenuBox` views from top-level to deepest nested menu. |
| `activateTopAtColumn(int $column)` | Opens or activates the top-level item at a local column. |
| `hoverPopupItem(int $index)` / `activatePopupItem(int $index)` | Selects or activates an item in the deepest open menu. |
| `dismissPopup()` | Closes every open menu box and its overlay. |
| `commandAtColumn(int $localX): int` | Returns the enabled command at a top-level column, or `0`. |

`Cmd::Menu` toggles the bar. A menu item's `Key` is active globally while the menu bar participates in preprocessing. A matching enabled command item queues its command on the owner; a matching item with a submenu opens that submenu instead. Menu mnemonics (`~X~`) work while a menu is open. The menu overlay captures pointer activity while a pull-down is visible; an outside click dismisses it.

### Status lines

```php
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;

$status = new StatusLine(
    Rect::of(0, 24, 80, 25),
    StatusDef::all(
        new StatusItem('~F1~ Help', Key::F1, Cmd::Help),
        new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
    ),
);
$status->setHintProvider(static fn (int $context): string => $context === 0 ? 'Ready' : '');
```

| Type | Constructor and operations |
| --- | --- |
| `StatusItem` | `new StatusItem(string $text, ?Key $key, int $command)`. Text can be empty for a key-only binding. |
| `StatusDef` | `new StatusDef(int $min, int $max)`. `items(StatusItem ...$items)` appends and returns the definition. `StatusDef::all(...$items)` covers contexts `0` through `0xFFFF`. The first definition whose inclusive range contains the help context supplies the visible items. |
| `StatusLine` | `new StatusLine(Rect $bounds, StatusDef ...$defs)`. `setHelpContext(int $context)` selects a definition; `update()` obtains it from the owner tree. `setHintProvider(?callable $provider)` installs `callable(int): string` text displayed after the active hints. |

When a status item's `Key` matches an enabled item, `StatusLine` rewrites the current key event as `EventType::Command` with that command. Clicking a visible enabled item queues a command on its owner. `commandAtColumn(int $localX)` reports the command at a local column or `0`.

## Scrolling, lists, and outlines

### `ScrollBar`

Construct with `new ScrollBar(Rect $bounds, ?ScrollBarOrientation $orientation = null)`, `ScrollBar::horizontal()`, or `ScrollBar::vertical()`. If orientation is omitted, width `1` means vertical and every other width means horizontal.

Public value model: `value`, `minVal`, `maxVal`, `pageStep`, and `arrowStep`. `setParams($value, $min, $max, $pageStep, $arrowStep)` normalizes `max >= min`, clamps the value, and clamps both steps to zero or greater. `setRange()`, `setStep()`, and `setValue()` update individual portions. A changed value broadcasts `Cmd::ScrollBarChanged` with the bar as `MessageEvent::$info`; interaction also broadcasts `Cmd::ScrollBarClicked`.

| Orientation | Keyboard |
| --- | --- |
| Horizontal | Left/Right arrow step; Ctrl+Left/Ctrl+Right page step; Home/End jump to range ends. |
| Vertical | Up/Down arrow step; Page Up/Page Down page step; Home/End jump to range ends. |

These keys are handled only when the bar receives the event: make it current or add `State::PostProcess` (for example, via `Window::standardScrollBar(..., handleKeyboard: true)`). `ScrollBarPart` provides compatibility constants for arrows, pages, indicator, `Vertical`, and `HandleKeyboard`. `scrollStep(int $part)` returns the signed movement for a part code.

### `Scroller`

`Scroller` is a selectable viewport over a logical surface:

```php
$viewer = new Scroller($bounds, $horizontalBar, $verticalBar);
$viewer->setLimit($contentWidth, $contentHeight);
$viewer->scrollTo(0, 12);
```

`limit` is the non-negative logical width/height and `delta` is the active scroll offset. `setLimit()` configures supplied bars; `scrollTo()` drives them; `scrollDraw()` copies bar values into `delta` and repaints. A subclass paints its logical content offset by `delta`. It accepts a `Cmd::ScrollBarChanged` command or broadcast when `MessageEvent::$info` is one of its configured bars.

### `ListViewer`, `ListBox`, and `SortedListBox`

`ListViewer` is an abstract selectable list. Implement `getText(int $item, int $maxLen): string`, then call `setRange(int $range)` after changing the source. Its public `focused`, `topItem`, and `range` values are zero-based. `focusItemNum()` clamps an item to range before focusing it; `focusItem()` accepts the supplied item directly. `selectItem()` broadcasts `Cmd::ListItemSelected` with the viewer as payload.

`ListBox::__construct(Rect $bounds, int $numCols = 1, ?ScrollBar $scrollBar = null)` stores strings. Use `newList(iterable $items)`; every item must be `string` or `Stringable`. `list()` returns its strings. Its form datum is `['collection' => list<string>, 'selection' => int]`.

`SortedListBox` has the same constructor and list API, with case-insensitive incremental prefix search. Printable keys extend `searchTerm()` when a matching item exists; Backspace shortens it.

| List input | Effect |
| --- | --- |
| Up/Down | Move one item. |
| Page Up/Page Down | Move one visible page. Ctrl+Page Up / Ctrl+Page Down jumps to first / last item. |
| Home/End | Move to first / last item of the current page. |
| Left/Right | Move columns when `numCols > 1`. |
| Space or double-click | Calls `selectItem()` and broadcasts `Cmd::ListItemSelected`. |

### `Outline` and `OutlineViewer`

`OutlineViewer` is an abstract scrollable tree. It requires accessors for root, child count, child lookup, text, child presence, expansion state, and `adjust(Node $node, bool $expand)`. `Outline` supplies those accessors for linked `Node` values:

```php
use HelgeSverre\TurboVision\Outline\Node;
use HelgeSverre\TurboVision\Outline\Outline;

$root = new Node('Project', Node::siblings(new Node('src'), new Node('tests')));
$outline = new Outline($bounds, $horizontalBar, $verticalBar, $root);
```

`Node` is `new Node(string $text, ?Node $childList = null, ?Node $next = null, bool $expanded = true)`; `Node::siblings(Node ...$nodes)` builds a sibling chain in display order. After changing nodes or expansion, call `update()`. `focused` is the zero-based visible position; collapsed descendants do not occupy positions. `getNode()`, `expandAll()`, `firstThat()`, and `forEachNode()` operate on visible nodes. Selecting an item broadcasts `Cmd::OutlineItemSelected` with the viewer as payload.

## Dialog controls

| Control | Construction and transferred value |
| --- | --- |
| `Button` | `new Button(Rect $bounds, string $title, int $command, int $flags = ButtonFlag::Normal)`. A `~X~` mnemonic activates it. `Default` reacts to Enter through `Cmd::Default`; `Broadcast` sends a broadcast instead of queuing a command; `GrabFocus` focuses on click; `LeftJust` changes title alignment. |
| `InputLine` | `new InputLine(Rect $bounds, int $capacity, ?Validator $validator = null)`. Capacity includes the terminator, so `maxLen` is `capacity - 1`. `text()` / `setText()` use grapheme-aware editing; form data is the string or a validator-transferred value. |
| `CheckBoxes` | `new CheckBoxes(Rect $bounds, SItem|array|null $items)`. `value` is a bit field: bit *n* marks item *n*. |
| `RadioButtons` | Same constructor. `value` is the selected zero-based item index. |
| `MultiCheckBoxes` | `new MultiCheckBoxes(Rect $bounds, SItem|array|null $items, int $selectionRange, int $flags, string $states)`. Packs each item's state into `value`; `dataSize()` is `4`. |
| `History` | `new History(Rect $bounds, ?View $link, int $historyId)`. Opens a process-local history picker for the linked input control. |
| `ListBox` / `SortedListBox` | Selectable string lists; see above. |
| `FileDialog` | `new FileDialog(string $wildCard = '*', string $title = 'Open file', string $inputName = '~F~ile name', int $options = FileDialog::OpenButton, int $historyId = 0, ?string $directory = null)`. `getFileName()` and `getData()` return the normalized input path. Options include `OkButton`, `OpenButton`, `ReplaceButton`, `ClearButton`, `HelpButton`, and `NoLoadDir`. |
| `ChDirDialog` | `new ChDirDialog(int $options = ChDirDialog::Normal, int $historyId = 0, ?string $directory = null)`. `getData()` returns the current directory-input text. `Cmd::Ok` validates and applies that directory as the process working directory. |

`SItem::list('~O~ne', '~T~wo')` creates a linked item list; arrays of strings are also accepted. Cluster controls are navigated with arrows, activated with Space or a mnemonic, and can disable item bits through `setButtonState(int $mask, bool $enable)`.

`InputLine` handles navigation, selection, and editing keys while selected, plus `Cmd::Copy`, `Cut`, `Paste`, and `Clear`. `selectAll()` selects all text. `setValidator()` changes validation; `valid(Cmd::Cancel)` always succeeds, and failed validation focuses the field.

### Standard dialogs

| API | Result |
| --- | --- |
| `MessageBox::show(Group $host, string $message, int $options = MsgBoxFlag::OkButton): int` | Builds and runs a centered message dialog; returns `Cmd::Ok`, `Yes`, `No`, or `Cancel`. |
| `MessageBox::showRect(...)` | Same, using explicit bounds. |
| `MessageBox::inputBox(Group $host, string $title, string $label, string $value = '', int $maxLen = 80): ?string` | Returns the entered value on `Cmd::Ok`, otherwise `null`. |
| `MessageBox::input(...)` | Alias of `inputBox()`. |

Combine one message type (`Warning`, `Error`, `Information`, or `Confirmation`) with button flags such as `MsgBoxFlag::OkCancel` or `YesNoCancel`.

## Editors, help, color, and text devices

### Editors

`Editor::__construct(Rect $bounds, ?ScrollBar $hScrollBar = null, ?ScrollBar $vScrollBar = null, ?Indicator $indicator = null, string $text = '')` is a selectable multiline editor. `curPtr`, `selStart`, and `selEnd` are grapheme offsets; `modified`, `overwrite`, `autoIndent`, `findStr`, `replaceStr`, and `editorFlags` are public editing state.

| Operation | Contract |
| --- | --- |
| `text()` / `length()` / `setText(string $text, bool $modified = false)` | Reads the text, its grapheme length, or replaces it and clears undo/redo history. |
| `hasSelection()` / `selectedText()` / `setSelect(int $start, int $end, bool $cursorAtStart = false)` / `selectAll()` / `hideSelect()` | Reads and controls the grapheme-range selection. |
| `copy()` / `cut()` / `paste()` / `insertText(string $text, bool $selectText = false)` | Clipboard and insertion actions; each reports success. `Editor::setClipboard()` updates the shared clipboard. |
| `deleteSelect()` / `deleteRange(int $start, int $end)` | Removes the selection or an explicit grapheme range. |
| `undo()` / `redo()` / `undoDepth()` / `undoByteSize()` / `undoByteBudget()` | Changes or inspects bounded undo state. |
| `search(string $needle, ?int $options = null, bool $wrap = true)` / `searchAgain()` | Finds text using `SearchOptions` (default `CaseSensitive`). |
| `find(FindRequest $request)` / `replace(ReplaceRequest $request)` / `replaceAll(string $find, string $replace, ?int $options = null)` | Structured find/replace operations; `replaceAll()` returns the number of replacements. |
| `setDialogHandler(?Closure $handler)` | Registers `Closure(EditorDialogRequest): int` for editor dialog requests; the last request is exposed as `lastDialog`. |
| `scrollTo(int $x, int $y)` / `positionOf(int $pointer)` / `pointerAt(int $line, int $column)` | Scrolls and converts between a grapheme pointer and logical line/column coordinates. |

`Memo` has the same constructor. Its `getData(): string` returns text; `setData()` accepts either a string or `['text' => string]`; `dataSize()` returns UTF-8 byte length. Tab is left for dialog traversal.

`FileEditor::__construct(Rect $bounds, ?ScrollBar $hScrollBar = null, ?ScrollBar $vScrollBar = null, ?Indicator $indicator = null, ?string $fileName = null)` loads a supplied path. `fileName`, `lastError`, and `isValid` are public. `loadFile(?string $path = null)`, `save()`, `saveAs(string $path)`, and `saveFile()` return `bool`. `resolveUnsaved(callable $resolver)` accepts a resolver receiving the editor and recognizes save (`Cmd::Yes`, `true`, or `'save'`) and discard (`Cmd::No` or `'discard'`) decisions; `discardChanges()` clears the modified state.

`Indicator::__construct(Rect $bounds)` renders a location/modified indicator; call `setValue(Point $location, bool $modified)`. `EditWindow::__construct(Rect $bounds, ?string $fileName = null, int $number = 0)` composes a `FileEditor`, horizontal and vertical `ScrollBar`, and `Indicator`, exposed as readonly properties. `setCloseResolver(?callable $resolver)` configures `callable(FileEditor): bool` close handling.

### Help

`HelpFile` stores compiled help topics. Use `new HelpFile(array $topics = [])`, `HelpFile::load(string $path)`, `save(string $path)`, `putTopic(int $context, HelpTopic $topic)`, `getTopic(int $context)`, `hasTopic(int $context)`, and `topics()`.

`HelpTopic` is created with `new HelpTopic(array $paragraphs = [], array $crossRefs = [])`. Use `addParagraph()`, `addCrossRef()`, `paragraphs()`, `crossRefs()`, `lines(?int $width = null)`, and `crossRefLocation()` when building or inspecting topic content.

`HelpViewer::__construct(Rect $bounds, ?ScrollBar $hScrollBar, ?ScrollBar $vScrollBar, HelpFile $helpFile, int $context)` renders one topic. `switchToTopic(int $context)` resets selection and scroll position. Tab/Shift+Tab chooses a cross-reference, Enter follows it, Escape ends the modal owner with `Cmd::Close`, and a double-click follows the clicked reference.

`HelpWindow::__construct(HelpFile $helpFile, int $context)` creates a centered `50 × 18` window with keyboard-enabled horizontal and vertical bars and a public readonly `viewer`.

### Color controls

`ColorDialog::__construct(Palette $palette, iterable $groups, bool $monochrome = false, ?callable $onCommit = null)` creates a centered Colors dialog. `$groups` is normalized to color groups; `$onCommit` receives the committed `Palette`. Readonly `groups`, `items`, `display`, `foregroundSelector`, `backgroundSelector`, and `monoSelector` expose its components.

| Operation | Contract |
| --- | --- |
| `getData(): Palette` / `setData(mixed $data)` | Reads the working palette or replaces it; `setData()` requires a `Palette`. |
| `commit(): Palette` | Returns the working palette and invokes the optional commit callback. |
| `cancel()` | Restores the working palette from its original entries. |
| `getIndexes(): ColorIndex` / `setIndexes(ColorIndex $indexes)` | Reads or restores selected group/item indexes. |

`ColorDisplay::__construct(Rect $bounds, string $text = 'Text ', int $color = 0x07, ?callable $onChanged = null)` previews an attribute. `setColor(int $color, bool $announce = true)` updates it. `ColorSelector::__construct(Rect $bounds, ColorSelectorType $type, int $color = 0, ?callable $onChanged = null)` selects foreground or background attributes; `setColor(int $color, bool $announce = false)` updates it. `ColorGroupList` and `ColorItemList` extend the list-viewer contract and are supplied by `ColorDialog`.

### Text devices and terminal output

`TextDevice` is an abstract `Scroller` for stream-like output: `__construct(Rect $bounds, ?ScrollBar $hScrollBar = null, ?ScrollBar $vScrollBar = null)`, `write(string $text): int`, abstract `doSputn(string $text): int`, and `flush(): void`. `write()` returns the accepted byte count.

`Terminal::__construct(Rect $bounds, ?ScrollBar $hScrollBar = null, ?ScrollBar $vScrollBar = null, int $maxBytes = 65536, int $maxLines = 2048, bool $wrap = true, int $tabWidth = 4)` is a bounded append-only terminal. All retention limits and tab width must be positive.

| Operation | Contract |
| --- | --- |
| `write()` / `doSputn()` / `output()` | Appends text or returns an `OutputTextStream` adapter. `doSputn()` accepts terminal control input including LF, CR, TAB, and Backspace. |
| `lineCount()` / `scrollbackBytes()` / `scrollback()` | Inspects retained logical lines and UTF-8 byte payload. |
| `maxScrollbackBytes()` / `maxScrollbackLines()` | Returns configured retention limits. |
| `isWrapping()` / `setWrapping(bool $wrap)` | Reads or changes soft wrapping, reflowing retained output. |
| `clear()` | Drops retained output and restores the empty tail line. |
| `scrollBy(int $rows)` / `scrollToBottom()` | Moves through visual rows or returns to the newest output. |

## Custom control checklist

1. Extend `View` for a leaf control or `Group` for an owner.
2. Give it explicit bounds and insert it into an already-owned group.
3. Set `options`, `eventMask`, and `growMode` for the interaction and resize behavior it needs.
4. Override `draw()` and `handleEvent()`; resolve colors through `getColor()` or `mapColor()`.
5. Clear events handled by the control. Implement `dataSize()`, `getData()`, `setData()`, and `valid()` when it participates in dialog data or validation.
