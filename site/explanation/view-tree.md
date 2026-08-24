# The retained view tree

A TurboVision interface is a live tree of view objects. Create a view once, insert it into an owning `Group`, and keep changing that object as the application runs. The same tree supplies drawing, focus, hit testing, window stacking, and modal interaction.

```text
Program
├── MenuBar
├── Desktop
│   ├── Background
│   ├── Window: Inventory
│   │   ├── Frame
│   │   └── application controls
│   └── Window: Search
│       ├── Frame
│       └── application controls
└── StatusLine
```

`Program`, `Desktop`, `Window`, and `Dialog` are groups. A group owns an ordered list of children; ordinary controls usually extend `View`. The view tree is the interface's source of truth—there is no separate layout or widget registry to keep in sync.

## Build the tree deliberately

Insert a child into the group that should own its position, lifetime, and focus. The child must be unowned at insertion time; a view cannot appear in two groups or become an ancestor of itself.

```php
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;

$window = new Window(Rect::of(4, 2, 54, 16), 'Report', 1);
$window->insert(new StaticText(
    Rect::of(2, 2, 42, 5),
    'The text rectangle is local to the window.',
));

$desktop->insertWindow($window);
```

Use the public ownership operations instead of assigning `owner` yourself:

- `Group::insert($view)` attaches a previously unowned child.
- `Group::remove($view)` detaches a child and restores an eligible sibling as the current view when necessary.
- `Desktop::insertWindow($window)` inserts and selects a window.

Removal does not destroy a PHP object. Keep a reference when you need to reinsert it later; otherwise let normal PHP object lifetime release it. Do not reuse a view while it still has an owner.

## Each level has its own coordinate system

`Rect::of($left, $top, $right, $bottom)` is expressed in the immediate owner's coordinate space. The bottom-right edge is exclusive, so `Rect::of(4, 2, 54, 16)` is 50 columns wide and 14 rows high.

For the example above:

```text
desktop coordinates                    window-local coordinates

(4,2) ┌──────────────────────────┐    (0,0) ┌──────────────────────────┐
      │                          │          │                          │
      │  (2,2) text starts here  │          │  (2,2) text starts here  │
      │                          │          │                          │
      └──────────────────────────┘          └──────────────────────────┘
            (54,16)                               (50,14)
```

The window's rectangle is local to the desktop. Its text rectangle is local to the window. `getExtent()` returns a view's own rectangle translated to `(0, 0)`, which is usually the rectangle to use when placing a child inside a custom group.

Mouse positions remain root-relative while they travel through the tree. Convert them at the view that handles the event:

```php
$mouse = $event->asMouse();
if ($mouse === null) {
    return;
}

$local = $this->makeLocal($mouse->where);
if ($local->x >= 2 && $local->x < 12 && $local->y === 1) {
    // The pointer is in this view's local button area.
}
```

Use `localToGlobal()` for the reverse conversion, for example when a drag calculation starts in local coordinates. `absoluteOrigin()` is available when you need the root-relative origin, but `makeLocal()` and `localToGlobal()` make intent clearer.

## Order is both paint order and stacking order

Children are stored in insertion order. Later children are above earlier children:

```php
$desktop->insertWindow($backWindow);
$desktop->insertWindow($frontWindow); // Drawn and hit-tested above $backWindow.
```

The renderer clips a child to its own extent, its ancestors, the screen, and the opaque portions of higher siblings. Redrawing a window behind another one therefore does not paint over the front window. Use `makeFirst()` to move an owned view to the front, or `putInFrontOf($other)` to position it immediately above a sibling.

`Window` opts into `State::TopSelect`, so selecting or clicking a window brings it forward. This is why the visual stacking order and the active window normally agree.

<DocCapture
  src="/captures/explanation/view-tree-stacking.png"
  alt="Two overlapping terminal windows: the later Search window has an active double-line frame and visibly covers part of the earlier Inventory window."
  caption="The later, selected Search window is both the topmost painted surface and the active focus branch."
/>

## Focus follows one branch

Every `Group` has at most one current child. When a selectable child becomes current, the group sets its `Focused` and `Selected` state; if that child is another group, focus continues down its current child. That forms one focused path from the program to a leaf control.

Make a custom control eligible for focus with `State::Selectable`:

```php
use HelgeSverre\TurboVision\Views\State;

$this->options |= State::Selectable | State::FirstClick;
```

`FirstClick` changes an important mouse detail: when a click first selects the control, that same click is also delivered to the control. Without it, the click selects the control but is consumed before the control handles it. This is appropriate for controls that should activate immediately on their first click.

`Group::setCurrent()` changes focus directly. `focus()` asks the owner to make a visible, enabled, selectable view current. `selectNext()` and `selectPrevious()` are available on a group when a custom container needs to move its own focus.

Hiding or disabling the current child causes its owner to choose another eligible selectable sibling. A hidden or disabled view is not a candidate for focused event delivery.

## Resize through the tree

Changing a group's bounds calculates a size delta and asks each child for adjusted bounds through `calcBounds()`. A child's `growMode` controls which of its four edges follow that delta.

```php
use HelgeSverre\TurboVision\Views\State;

// Keep left/top fixed; extend the right and bottom edges with the owner.
$this->growMode = State::GrowHiX | State::GrowHiY;
```

This is the usual mode for an interior view that should fill a resizable window. `Window` uses relative grow behavior by default, so it adapts when the desktop changes size. A centered child can opt into `State::CenterX`, `State::CenterY`, or both; its owner recenters it during insertion and resize.

Call `locate()`, `moveTo()`, or `growTo()` to change a view's bounds. They apply the view's size limits and repaint the owner so the old footprint is restored. Avoid changing `bounds` directly except inside framework-level layout code.

## Drawing is local and retained

Override `draw()` to paint the view's current state. Coordinates passed to `writeLine()`, `writeBuf()`, and related drawing methods are local to that view. `drawView()` checks visibility and exposure before invoking `draw()`.

```php
public function draw(): void
{
    $this->fillExtent($this->mapColor(1));
    $this->writeStr(1, 1, 'Ready', $this->mapColor(2));
}
```

When application state changes, update the state and request a redraw of the affected view with `drawView()`. If a visible view is moved, resized, removed, or hidden, its owner is repainted too, which replaces its old cells with the background or views below it. Groups can use `lock()` and `unlock()` around several related changes to defer their full redraw until the outermost unlock.

## A practical debugging sequence

When a view is missing, clipped, or receives input in the wrong place, inspect the tree in this order:

1. Confirm the view was inserted into the intended group and has the expected `owner`.
2. Compare `getBounds()` with the owner's `getExtent()`; they must be in the same local coordinate space.
3. Check `State::Visible` on the view and every ancestor.
4. Check whether a later opaque sibling covers it; later children are higher in Z-order.
5. For keyboard input, confirm the view is selectable, enabled, and on the current branch. For mouse input, convert `MouseEvent::$where` with `makeLocal()` before comparing coordinates.

Once the ownership, local geometry, and focus path are correct, drawing and event routing follow from the same tree.
