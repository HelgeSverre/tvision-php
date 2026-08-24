# Geometry, drawing, and palettes

## Coordinates and rectangles

`Point` and `Rect` are immutable value objects in `HelgeSverre\TurboVision\Geometry`. Screen and view coordinates are integer cell positions.

```php
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;

$origin = new Point();                 // (0, 0)
$bounds = Rect::of(2, 1, 42, 14);      // 40 columns × 13 rows
$same = Rect::fromSize(2, 1, 40, 13);
```

`Rect::$a` is the inclusive top-left corner; `Rect::$b` is the exclusive bottom-right corner. A rectangle contains `x` in `[a.x, b.x)` and `y` in `[a.y, b.y)`. Width is `b.x - a.x`; height is `b.y - a.y`. A zero or negative extent is empty.

### `Point`

```php
new Point(int $x = 0, int $y = 0)
```

| Method | Result |
| --- | --- |
| `add(Point $other)` / `subtract(Point $other)` | Component-wise arithmetic. |
| `scale(int $factor)` / `negate()` | Multiplies both components / reflects through the origin. |
| `Point::min(Point $a, Point $b)` / `Point::max(...)` | Component-wise minimum / maximum. |
| `clampTo(Rect $container)` | Confines each component to a non-empty rectangle's valid half-open range. For an empty or inverted axis, that component resolves to the rectangle's minimum edge. |
| `equals(Point $other)` | Exact value comparison. |

`add()`, `subtract()`, and `scale()` saturate at `PHP_INT_MIN` or `PHP_INT_MAX`; they never promote coordinates to floats. `(string) new Point(3, 4)` is `"(3, 4)"`.

### `Rect`

```php
new Rect(Point $a, Point $b)
Rect::of(int $ax, int $ay, int $bx, int $by)
Rect::fromSize(int $x, int $y, int $width, int $height)
```

`fromSize()` treats negative width or height as zero. `size()` returns a `Point` holding the width and height.

| Method | Result |
| --- | --- |
| `width()` / `height()` / `isEmpty()` | Extent and emptiness. |
| `contains(Point $point)` / `containsRect(Rect $other)` | Half-open point containment / complete rectangle containment. |
| `intersect(Rect $other)` / `intersects(Rect $other)` | Shared rectangle / whether that shared region is non-empty. |
| `union(Rect $other)` | Smallest rectangle covering both inputs. |
| `move(int $dx, int $dy)` | Translates both corners. |
| `grow(int $dx, int $dy)` | Moves `a` outward by negative deltas and `b` outward by positive deltas. |
| `inset(int $dx, int $dy)` | Shrinks each edge for positive deltas; equivalent to `grow(-$dx, -$dy)`. |
| `centeredIn(Rect $container)` | Keeps this size and centers it within the container. Empty dimensions are treated as zero. |
| `clampInto(Rect $container)` | Keeps as much of this size as fits and moves it fully inside the container. An empty container produces an empty rectangle at its `a` point. |
| `equals(Rect $other)` | Exact corner comparison. |

`move()`, `grow()`, extent calculation, and `fromSize()` use saturating integer arithmetic. `(string) Rect::of(0, 0, 20, 10)` is `"[(0, 0) - (20, 10)]"`.

## Cells and screen buffers

### `Cell`

```php
new Cell(string $char = ' ', int $attr = 0x07)
Cell::of(string $char, Attribute $attribute)
Cell::blank()
Cell::sentinel()
```

A `Cell` has immutable `char` and `attr` properties. The default is a space using attribute `0x07` (light gray on black). `with(string $char, int $attr)` returns a replacement cell, and `equals(Cell $other)` compares both properties.

`Cell::of()` encodes an `Attribute` as a rendered cell value, retaining bright background colors. `Cell::sentinel()` is an internal front-buffer marker (`"\0"`, `-1`); application code should use `Cell::blank()` for a blank cell.

Each cell holds exactly one display-column grapheme. Invalid, control, zero-width, double-width, emoji-presentation, or multi-grapheme values become `?` when a normal `Cell` is constructed.

### `Buffer`

```php
new Buffer(int $width, int $height, ?Cell $fill = null)
```

`Buffer` is a mutable row-major grid of immutable cells. Dimensions must be non-negative and must not exceed `Buffer::MAX_CELLS` (1,000,000 cells). The default fill is `new Cell()`.

| Method | Behavior |
| --- | --- |
| `at(int $x, int $y): Cell` | Returns the cell, or a default blank when outside the buffer. |
| `put(int $x, int $y, Cell $cell): void` | Writes one cell; out-of-bounds writes are ignored. |
| `fill(Rect $rect, Cell $cell): void` | Fills the intersection of the requested rectangle and buffer bounds. |
| `row(int $y): list<Cell>` | Returns one row; an out-of-bounds row is blank-padded to the buffer width. |
| `cells(): array` | Returns the row-major cell array. PHP copy-on-write keeps the buffer isolated from caller changes. |
| `rows(): list<string>` | Returns the character string for each row. |
| `copy(): Buffer` | Returns an isolated copy-on-write snapshot. |

`Buffer` implements `Countable` and `IteratorAggregate`: `count($buffer)` is the row count, and iteration yields rows from top to bottom keyed by `y`.

### `DrawBuffer`

```php
new DrawBuffer(int $width, int $fillAttr = 0x07)
```

`DrawBuffer` is a mutable one-row paint helper. Its width must be between zero and `Buffer::MAX_CELLS`. It starts blank with `fillAttr`; `clear(int $attr = 0x07)` resets every column to spaces using that attribute.

| Method | Behavior |
| --- | --- |
| `moveChar(int $x, string $char, int $attr, int $count)` | Repeats one cell glyph. The operation is clipped to visible columns; non-positive counts do nothing. |
| `moveStr(int $x, string $text, int $attr)` | Writes grapheme-by-grapheme, clipping each visible column. |
| `moveCStr(int $x, string $text, int $normalAttr, int $highlightAttr): int` | Writes text and toggles attributes at each `~`; markers are not written. Returns the logical column after the last non-marker grapheme, including graphemes clipped from the row. |
| `putAttribute(int $x, int $attr)` | Recolors a visible cell without changing its glyph. |
| `putCell(int $x, Cell $cell)` / `cellAt(int $x): Cell` | Writes a visible cell / returns it, or a blank when outside the row. |
| `moveBuffer(int $destX, DrawBuffer $source, int $srcX, int $count)` | Copies cells. Destination writes are clipped; a source coordinate outside its row supplies a blank cell. |
| `cells(): array` / `(string) $buffer` | Returns cells / returns their characters with attributes ignored. |

All `DrawBuffer` write coordinates are zero-based. Negative starts discard the off-row portion; writes beyond the right edge are ignored. `moveChar()` limits its count to visible columns, and string-writing cursor increments use saturating integer arithmetic.

## Unicode text

`TerminalText` maps Unicode to the framework's one-cell-per-column model.

| Method | Behavior |
| --- | --- |
| `graphemes(string $text): list<string>` | Segments text into Unicode extended grapheme clusters. Printable ASCII uses a direct byte fast path. Invalid UTF-8 returns `['?']`. |
| `length(string $text): int` | Counts grapheme clusters, not bytes or display width. |
| `slice(string $text, int $offset, ?int $length = null): string` | Slices by grapheme cluster. Negative offsets follow PHP array/string slicing behavior. |
| `cellGlyph(string $value): string` | Returns one safe display-column glyph or `?`. |
| `isPrintableAscii(string $text): bool` | Tests whether every byte is in the printable ASCII range. |

Combining sequences that occupy one column, such as `e` plus an acute accent, stay together in one cell. CJK wide glyphs, emoji, variation-selector presentation forms, controls, format characters, malformed text, and values containing more than one grapheme are rendered as `?` in a cell.

## Colors and attributes

`Drawing\Color` is the 16-value CGA palette indexed from `0` to `15`:

| Indexes | Colors |
| --- | --- |
| `0–7` | `Black`, `Blue`, `Green`, `Cyan`, `Red`, `Magenta`, `Brown`, `LightGray` |
| `8–15` | `DarkGray`, `LightBlue`, `LightGreen`, `LightCyan`, `LightRed`, `LightMagenta`, `Yellow`, `White` |

`Color::isBright()` is true for indexes `8–15`.

```php
use HelgeSverre\TurboVision\Drawing\{Attribute, Color};

$attribute = new Attribute(Color::White, Color::Blue);
assert($attribute->toByte() === 0x1F);
```

```php
new Attribute(
    Color $fg = Color::LightGray,
    Color $bg = Color::Black,
    bool $blink = false,
)
```

| Method | Behavior |
| --- | --- |
| `toByte(): int` | Packs the classic attribute byte: foreground bits `0–3`, low three background bits `4–6`, blink bit `7`. |
| `fromByte(int $byte): Attribute` | Unpacks the classic format. Bright backgrounds cannot be represented in this format. |
| `toCellValue(): int` / `fromCellValue(int $value)` | Packs or unpacks rendered cell attributes, including bright backgrounds and blink. |
| `toSgr(bool $useDefaultBackgroundForBlack = true): string` | Returns a reset-plus-SGR ANSI sequence. Black background is omitted by default; pass `false` to emit ANSI black explicitly. |

ANSI output uses bright SGR codes for colors `8–15` and maps CGA's RGB bit ordering to ANSI's BGR ordering. For example, `Color::Blue` emits ANSI blue (`34`), `Color::Red` emits ANSI red (`31`), `Color::Cyan` emits ANSI cyan (`36`), and `Color::Brown` emits ANSI yellow (`33`).

## Palettes

`Palette` maps one-based logical palette indices to packed attribute values:

```php
use HelgeSverre\TurboVision\Drawing\Palette;

$palette = Palette::fromBytes("\x71\x07\x1F");
assert($palette->get(1) === 0x71);
```

```php
new Palette(array<int, int> $entries)
Palette::fromBytes(string $bytes)
```

`fromBytes()` maps its first byte to index `1`, its second byte to `2`, and so on. `get(int $index)` returns `0x07` for index `0` or any missing index. `size()` returns the number of entries.

View palettes are remapping tables. `View::mapColor(int $index)` looks up an index in the current view's palette, then asks the owner to resolve that returned value; the chain ends at the root `Program` palette, which yields the packed attribute byte. Index `0` and an unresolved chain always produce `0x07`. `View::getColor(int $color)` maps the low and high bytes independently and returns `(high << 8) | low`.

`Application\PaletteMode` selects the built-in root table:

| Mode | String value | `Palettes` table |
| --- | --- | --- |
| `Color` | `color` | `COLOR` / `MODERN_DARK` (default) |
| `ClassicColor` | `classic-color` | `CLASSIC_COLOR` |
| `BlackWhite` | `black-white` | `BLACK_WHITE` |
| `Monochrome` | `monochrome` | `MONOCHROME` |

Use `Palettes::for(PaletteMode $mode)` to retrieve the raw one-based root palette bytes. `Program::setPaletteMode()` changes the selected built-in table; `Program::setPalette(?Palette $palette)` installs or clears an explicit root palette override.

<DocCapture
  src="/captures/reference/special-palettes.png"
  alt="A ClassicColor Turbo Vision desktop with overlapping blue and cyan windows"
  caption="The root palette resolves the final colors after each window's logical palette mapping."
/>
