# M5 Outline & Color — Spike Plan (outline)

> Spike-level plan: scope, classes, task outline, acceptance, risks. NOT full TDD code.
> Promote to a detailed plan right before building, once M1-M4 exist.

**Milestone goal:** Deliver an expandable/collapsible tree viewer (`OutlineViewer`, `Outline`, `Node`) and a live palette-editing dialog (`ColorDialog` with its satellite views and data model). Together they close the two remaining interactive showcase features: a navigable directory-tree outline and an in-app color customizer that remaps the application palette without restarting.

**Depends on:** M1 (`Geometry`, `Drawing\Palette`/`DrawBuffer`/`Cell`, `Events`), M1-Plan-3 (`Views\View`/`Group`/`Application`), M2 (`Views\Scroller`/`ScrollBar`/`ListViewer`), M3 (`Dialogs\Dialog`/`Button`/`Label`/`Cluster`/`CheckBoxes`)

**Acceptance examples:**
- Headless: snapshot test of `OutlineViewer` rendering a small three-level `Node` tree — glyphs (`├`, `└`, `│`), focus highlight, collapsed/expanded state.
- Headless: snapshot test of `ColorDialog` with a two-group palette — group list, item list, selector state, preview cell rendered with correct fg/bg.
- Real terminal: `bin/demo outline` runs a live directory-tree outline; arrow keys navigate, Enter/Space toggles expand/collapse, `Ctrl-A` expands all.
- Real terminal: `bin/demo color` opens the `ColorDialog` on the running app's palette; selecting different items repaint the display live; OK commits, Cancel discards.

---

## Classes to build (new this milestone)

| PHP class (namespace) | Original TV class | Responsibility | Key methods / notes |
|---|---|---|---|
| `Outline\Node` | `TNode` | Linked-node value: text, children (via `$childList`), sibling (via `$next`), `$expanded` flag | Constructor: `Node(string $text, ?Node $childList = null, ?Node $next = null, bool $expanded = true)` |
| `Outline\OutlineViewer` | `TOutlineViewer` | Abstract scrollable tree renderer; extends `Scroller` | Abstract: `getRoot()`, `getText(Node)`, `hasChildren(Node)`, `isExpanded(Node)`, `getChild(Node,int)`, `getNumChildren(Node)`, `adjust(Node,bool)`; concrete: `draw()`, `handleEvent()`, `update()`, `expandAll()`, `forEach()`, `firstThat()`, `getGraph()`, `createGraph()` |
| `Outline\Outline` | `TOutline` | Concrete `OutlineViewer` over a `Node` tree; implements all abstract methods by walking `$root` linked list | `adjust()` sets `Node::$expanded`; `getChild()` walks `$childList`; `getNumChildren()` counts siblings |
| `Color\ColorItem` | `TColorItem` | Named palette entry: display name + palette byte index; linked list via `$next` | `ColorItem(string $name, int $index, ?ColorItem $next = null)` |
| `Color\ColorGroup` | `TColorGroup` | Named group of `ColorItem`s; linked list via `$next`; holds group start `$index` | `ColorGroup(string $name, ?ColorItem $items = null, ?ColorGroup $next = null)` |
| `Color\ColorSelector` | `TColorSelector` | Grid view of the 8 bg or 16 fg colors; emits `cmColorForegroundChanged`/`cmColorBackgroundChanged` broadcasts | `ColorSel` enum (`Background`, `Foreground`); `draw()` renders color swatches; arrow keys + mouse click navigate |
| `Color\MonoSelector` | `TMonoSelector` | `Cluster` subclass; four radio-style buttons (Normal/Highlight/Underline/Inverse); emits the same broadcast commands on `Cluster::press()` | Extends `Dialogs\Cluster`; `mark(int)`, `press(int)`, `newColor()` |
| `Color\ColorDisplay` | `TColorDisplay` | Preview view: renders sample text with current fg+bg attribute | Responds to `cmColorForegroundChanged`/`cmColorBackgroundChanged` broadcasts; `setColor(int $attr)` |
| `Color\ColorGroupList` | `TColorGroupList` | `ListViewer` subclass; scrollable list of group names; broadcasts `cmNewColorItem` on focus change | Extends `Views\ListViewer`; `focusItem(int)` broadcasts; `getText(int, int): string` walks linked list |
| `Color\ColorItemList` | `TColorItemList` | `ListViewer` subclass; scrollable list of item names within the selected group; broadcasts `cmNewColorIndex` on focus | Extends `Views\ListViewer`; responds to `cmNewColorItem` to reload its item pointer |
| `Color\ColorDialog` | `TColorDialog` | Modal `Dialog` assembling all color subviews; owns a working-copy `Palette`; OK commits, Cancel discards | `getData()`/`setData()` copy palette bytes; `handleEvent()` responds to `cmNewColorIndex` to remap the working palette and trigger live `drawView()` |

---

## Builds on (existing)

- `Views\Scroller` — `OutlineViewer` extends it for free horizontal + vertical scroll.
- `Views\ScrollBar` — passed to `OutlineViewer` and `ColorGroupList`/`ColorItemList` constructors.
- `Views\ListViewer` — `ColorGroupList` and `ColorItemList` subclass it; no new list infrastructure needed.
- `Drawing\Palette` — the palette-chain concept from M1; `ColorDialog` clones the app palette into a working copy via `Palette` value semantics.
- `Drawing\DrawBuffer` — `OutlineViewer::draw()` uses `moveStr`/`moveChar` with glyph characters; `ColorSelector::draw()` uses `putAttribute` to paint swatches.
- `Dialogs\Dialog` — `ColorDialog` extends it; inherits `execView()` modality.
- `Dialogs\Cluster` / `Dialogs\CheckBoxes` — `MonoSelector` extends `Cluster` (four buttons, value = mono attribute byte).
- `Dialogs\Label` — used inside `ColorDialog` to label each subview.
- `Dialogs\Button` — OK + Cancel buttons in `ColorDialog`.
- `Events\Cmd` — add color command codes: `cmColorForegroundChanged = 71`, `cmColorBackgroundChanged = 72`, `cmColorSet = 73`, `cmNewColorItem = 74`, `cmNewColorIndex = 75`; and outline command `cmOutlineItemSelected = 301`.

---

## Task outline (build order)

1. **Node value object** (`src/Outline/Node.php`, `tests/Unit/Outline/NodeTest.php`). Verify linked-list construction: `new Node('a', new Node('b'), null)` produces correct `$childList`/`$next` links. No UI needed yet.

2. **`OutlineViewer` abstract class** (`src/Outline/OutlineViewer.php`). Subclass of `Scroller`. Implement `update()` (walk tree, compute row count + max line width, call `setLimit()`), `forEach()`/`firstThat()` (the shared recursive iterator with level/position/lines/flags), `getNode(int $i)`, `expandAll(Node)`, `createGraph()`/`getGraph()` (tree-glyph string builder using `├ └ │ ─` Unicode equivalents), `draw()` (iterate visible rows, paint graph prefix + text with correct palette entry), `focused(int)`, `setState()`, `handleEvent()` (arrows navigate, Enter/Space toggle expand, Ctrl-A expands all, mouse click selects/toggles). Headless: no concrete tree yet — test with a stub subclass.

3. **`Outline` concrete class** (`src/Outline/Outline.php`, `tests/Unit/Outline/OutlineTest.php`). Implements all abstract methods by walking the `Node` linked list. Test: build a three-level tree, snapshot the rendered `Buffer` for normal/collapsed states; verify `adjust()` toggles `Node::$expanded`; verify `getNumChildren()` counts sibling chain length.

4. **Outline demo** (`examples/php/demo/outline-demo.php`). Build a directory-tree `Node` graph of `getcwd()` (2-3 levels deep, static). Boot a minimal `Application`, open a `Window` containing `Outline` + two `ScrollBar`s. Validates that `OutlineViewer` composes with the M2 windowing layer end-to-end. Also add headless snapshot test.

5. **`ColorItem` and `ColorGroup` data model** (`src/Color/ColorItem.php`, `src/Color/ColorGroup.php`, `tests/Unit/Color/ColorItemTest.php`, `tests/Unit/Color/ColorGroupTest.php`). Pure value/linked-list classes; test chain construction and traversal. Add the color command codes to `Events\Cmd`.

6. **`ColorSelector` view** (`src/Color/ColorSelector.php`, `tests/Unit/Color/ColorSelectorTest.php`). Extends `View`. `draw()` paints a grid of 8 bg or 16 fg color cells (each cell drawn with `DrawBuffer::putAttribute()`). Arrow keys and mouse click change `$color`; `colorChanged()` broadcasts `cmColorForegroundChanged` or `cmColorBackgroundChanged`. Snapshot test: confirm a `csForeground` selector renders 16 distinct attribute values.

7. **`MonoSelector` view** (`src/Color/MonoSelector.php`, `tests/Unit/Color/MonoSelectorTest.php`). Extends `Cluster`. Four items (Normal=0x07, Highlight=0x0F, Underline=0x01, Inverse=0x70 on monochrome). `mark(int)` compares `$value` to item attributes. `press(int)` sets `$value` and calls `newColor()` which broadcasts `cmColorSet`. Headless test: pressing each button sets the correct attribute byte.

8. **`ColorDisplay` view** (`src/Color/ColorDisplay.php`, `tests/Unit/Color/ColorDisplayTest.php`). Extends `View`. Renders a fixed text string with the current attribute byte applied to the entire row. Responds to `cmColorForegroundChanged`/`cmColorBackgroundChanged` by updating the fg or bg nibble of `$attr` and calling `drawView()`. Snapshot test: confirm displayed cell attributes match the set color.

9. **`ColorGroupList` and `ColorItemList` views** (`src/Color/ColorGroupList.php`, `src/Color/ColorItemList.php`, `tests/Unit/Color/ColorGroupListTest.php`, `tests/Unit/Color/ColorItemListTest.php`). Both extend `ListViewer`. `ColorGroupList::focusItem()` broadcasts `cmNewColorItem` carrying the focused `ColorGroup`. `ColorItemList::handleEvent()` listens for `cmNewColorItem` and reloads its `$items` pointer from the new group's item list. Test: two groups with three items each; selecting group 2 repopulates the item list.

10. **`ColorDialog` assembly** (`src/Color/ColorDialog.php`, `tests/Unit/Color/ColorDialogTest.php`). Extends `Dialog`. Constructor lays out all subviews (fixed bounds ~56×18). Clones the passed `Palette` into `$pal` (working copy). `handleEvent()` intercepts `cmNewColorIndex`: read the focused item's `$index`, use it to index into `$pal`, split into fg/bg nibbles, broadcast `cmColorSet` to both selectors and `cmColorForegroundChanged`/`cmColorBackgroundChanged` to `ColorDisplay`. OK closes with `cmOk`; caller reads back the edited palette via `getData()`. Headless test: instantiate with a minimal 4-group palette, simulate selecting an item, verify `$pal` byte is unchanged until OK; verify cancel leaves caller's original palette untouched.

11. **Color dialog demo** (`examples/php/demo/color-demo.php`). Open `ColorDialog` from a menu item. On OK, call `Application::setPalette()` (or equivalent broadcast redraw). Demonstrates live palette remap: selecting a new foreground color for "Desktop" immediately repaints the desktop. Headless smoke test confirms the dialog opens and closes without error.

12. **Integration & CI wiring**. Ensure `bin/demo outline` and `bin/demo color` exist as CLI entry points. Add both example files to the headless test suite. Run PHPStan max over all new `src/Outline/` and `src/Color/` files. Update `composer.json` autoload map.

---

## Key design decisions / risks

- **Node tree representation.** The original uses a singly-linked intrusive list (`$next` sibling, `$childList` first child). This is faithful and simple but makes random-access O(n). `OutlineViewer::getNode(int $i)` must walk the full visible tree each call; acceptable for typical tree sizes. Alternatively, `update()` can build a flat positional index array (reset on every `update()`) for O(1) row lookup — preferred approach to avoid repeated tree walks during `draw()`.

- **Tree glyph rendering.** The `lines` bitmask in `forEach()` tracks which ancestor levels still have siblings below the current node. `createGraph()` uses bit `1<<level` to decide whether to emit `│` (has more siblings) or ` ` (last child) for each ancestor column, then `├─` or `└─` for the current node. The original `graphChars` static string encodes the four cases; the PHP port should use a `readonly array` of the Unicode equivalents (`│`, `├`, `└`, `─`).

- **Expand/collapse state.** `Node::$expanded` is the single source of truth in `Outline`. `OutlineViewer` does not cache expanded state — it reads `isExpanded()` on every iteration. After `adjust()` is called, the caller must call `update()` + `drawView()` to reflow scroll limits and repaint; `handleEvent()` does this automatically.

- **Lazy children.** `OutlineViewer` is abstract precisely to support lazy child loading (e.g. a real filesystem tree that reads directories on first expand). `Outline` with `Node` objects is the eager concrete case. A future lazy subclass only needs to override `getChild()`/`getNumChildren()`/`adjust()` to fetch on demand.

- **Palette-editing data flow.** `ColorDialog` keeps a `$pal` working copy (cloned from the argument in the constructor). Subview broadcasts update only the working copy. The caller's original palette is never touched during editing. On OK, `getData()` copies `$pal` back to the caller's buffer; the caller then calls `Application::applyPalette()` (or broadcasts `cmColorSet`) to repaint the whole screen. This mirrors the original faithfully and avoids partial-commit bugs.

- **Working copy vs. live remap.** The original `TColorDialog` edits a local copy and updates the live screen palette only on OK. Matching this semantics is safer and simpler. A "preview" mode (live remap during selection) would require broadcasting a palette-change event to the `Program` on every arrow key — out of scope here but structurally possible.

- **`MonoSelector` extends `Cluster`, not `RadioButtons`.** In the original, `TMonoSelector` subclasses `TCluster` directly, overriding `mark()`/`press()` to map button indices to mono attribute bytes rather than bit flags. The PHP port must do the same; reusing `RadioButtons` would require fighting its value semantics.

- **`ColorSelector` draw buffer.** Each color swatch is a single cell whose fg and bg are both set to the swatch color (or a contrasting pair). Using `DrawBuffer::putAttribute()` directly is the cleanest approach; this requires that M2's `DrawBuffer` exposes a low-level attribute-write path.

---

## Out of scope (later milestones)

- Help system integration (`HelpContext` on the `ColorDialog`; M6).
- Object streaming: `write()`/`read()` for `Outline`, `ColorDialog`, etc. (M6 or a PHP-native serialization layer).
- `TColorIndex` `getIndexes()`/`setIndexes()` bulk save/restore (useful for "save desktop colors" — M6).
- Lazy directory-tree loading via a custom `OutlineViewer` subclass that reads directories on expand (a showcase demo, not core).
- `TTextDevice`/`TTerminal` TTY view (textview.h) — separate feature, not required by M5.
- Message boxes (`MessageBox` helpers beyond what M3 delivered) — already done.
