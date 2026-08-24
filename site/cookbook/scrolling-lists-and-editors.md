# Use scrolling, lists, and editors

Choose the smallest component that owns the behavior you need. A `ListBox` owns a string collection and selection. A `Scroller` exposes a viewport over custom content. An `Outline` adds hierarchy. An `Editor` owns cursor movement, selection, undo, search, and editing behavior.

## Show a selectable string list

Use `ListBox` when rows are strings and one focused index is enough:

```php
$bar = ScrollBar::vertical(Rect::of(27, 1, 28, 11));
$list = new ListBox(Rect::of(1, 1, 27, 11), 1, $bar);
$list->newList(['Queued', 'Running', 'Complete']);

$window->insert($bar);
$window->insert($list);
```

Read `getData()` for both collection and selection, or use `list()` and the inherited public `focused` index. `SortedListBox` adds case-insensitive incremental prefix search. Subclass `ListViewer` when rows come from another model or need custom text generation.

Handle `Cmd::ListItemSelected` to respond to Space or a double-click. The event's `MessageEvent::$info` is the viewer that made the selection.

## Draw content larger than the viewport

Use `Scroller` for grids, logs, canvases, or any surface where you own the drawing:

```php
$vertical = $window->standardScrollBar(
    ScrollBarOrientation::Vertical,
    handleKeyboard: true,
);
$horizontal = $window->standardScrollBar(
    ScrollBarOrientation::Horizontal,
    handleKeyboard: true,
);

$view = new LogScroller($bounds, $horizontal, $vertical, $lines);
$view->setLimit($longestLine, count($lines));
$window->insert($view);
```

In `draw()`, translate visible coordinates through `$this->delta`. Call `setLimit()` whenever the logical content size changes and `scrollTo()` when application code needs to reveal a location. See [Add windows and scrolling](/tutorials/guide/windows-and-scrolling) for a complete subclass.

## Show hierarchical data

`Outline` displays a tree of `Node` values:

```php
$root = new Node(
    'Project',
    Node::siblings(
        new Node('src'),
        new Node('tests'),
        new Node('composer.json'),
    ),
);

$outline = new Outline($bounds, $horizontal, $vertical, $root);
```

Use `OutlineViewer` instead when the hierarchy already lives in your own model. Implement its node accessors and expansion hook rather than copying the model into `Node` objects.

## Add a text editor

`EditWindow` is the fastest complete composition: it creates a `FileEditor`, both scroll bars, and a position indicator.

```php
$window = new EditWindow(
    Rect::of(3, 2, 74, 22),
    fileName: $path,
    number: 1,
);

if ($window->editor->isValid) {
    $this->insertWindow($window);
} else {
    $message = $window->editor->lastError ?? 'Unable to open the file.';
    MessageBox::show($this, $message, MsgBoxFlag::Error | MsgBoxFlag::OkButton);
}
```

Use the editor's `text()`, `setText()`, `setSelect()`, `modified`, `save()`, and `saveAs()` methods. `FileEditor` reports expected I/O failures through `false` and `lastError`; do not discard the current document until a save succeeds.

An edited `FileEditor` deliberately fails `valid()` until the application resolves unsaved work. Install `EditWindow::setCloseResolver()` and call `FileEditor::resolveUnsaved()` from a Save / Discard / Cancel callback. This keeps a frame close, `Cmd::Close`, and application shutdown on the same data-loss policy.

Choose `Editor` for an in-memory editable buffer, `Memo` when the editor participates as dialog form data, and `FileEditor` for a path-backed document.

## Keep the layout responsive

Set `growMode` on the content and bars according to the edges they should follow. For a full-window editor or list, the content usually grows on high X and high Y. Standard window scroll bars already occupy the frame edges; manually positioned bars need matching grow modes.

Test at the minimum supported terminal size and after a resize. If panes collide, override `Window::sizeLimits()` rather than relying on clipping to hide the problem.
