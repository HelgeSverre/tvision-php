# Drawing and terminal ownership

An interactive terminal is shared process state. Raw input, the alternate screen,
mouse reporting, cursor visibility, and optional keyboard protocols all outlive an
individual paint operation. TurboVision keeps that state at the driver boundary so
views can concentrate on a stable grid of cells.

```text
View tree
    │ draw()
    ▼
Screen back buffer ── diff against front buffer ──► ANSI frame ──► Driver ──► terminal
    │                                                        ▲
    └── HTML renderer / test inspection                     │ input bytes
                                                             │
                                                      EscapeDecoder
```

## A frame is a desired screen, not a sequence of prints

`Screen` owns two `Drawing\Buffer` instances with the current terminal dimensions:

- The **back buffer** is the desired frame. Every view writes into it through
  `writeStr()`, `writeChar()`, `writeBuf()`, or `writeLine()`.
- The **front buffer** is the frame that was successfully sent to the terminal.

At redraw time, the program clears the back buffer, draws its retained view tree,
sets the cursor requested by the focused view, and flushes the screen. `DiffPresenter`
compares the two buffers row by row. It moves the terminal cursor to the start of each
changed run, emits a style when the attribute changes, and writes only the changed
characters. Once the driver accepts the frame, the back buffer becomes the next front
buffer snapshot.

This is why a view should paint its complete current extent whenever `draw()` runs.
It does not need to calculate its own dirty rectangles or optimize terminal output.
The screen layer performs the final comparison after all overlapping views have had a
chance to contribute.

Calling `flush()` again without changing cells produces no character output. Cursor
state is handled separately: a frame can still move, show, or hide the hardware cursor
even when its cells are unchanged.

## Views compose into one screen

Views use local coordinates. Before a drawing helper reaches the back buffer, the
framework calculates the view's absolute origin and intersects its rectangle with every
ancestor and with the screen. A child can therefore draw naturally within its own
bounds; output outside the view, its parents, or the terminal is discarded.

Groups draw children in insertion order, so later siblings are visually above earlier
ones. The write path also accounts for sibling occlusion. A lower view cannot repaint
over an exposed piece of a sibling that sits above it. This keeps windows, frames,
controls, and shadows coherent even though each component draws independently.

For a custom view, use a `DrawBuffer` when a row has mixed text and attributes, then
write it through the view. Resolve colors with `getColor()` or `mapColor()` instead of
hard-coding terminal SGR sequences; the palette chain can then recolor the same view
with the rest of the application.

```php
use HelgeSverre\TurboVision\Drawing\DrawBuffer;

public function draw(): void
{
    $row = new DrawBuffer($this->getExtent()->width());
    $row->moveStr(0, ' Ready ', $this->getColor(1));
    $this->writeLine(0, 0, $this->getExtent()->width(), 1, $row);
}
```

The [view tree explanation](./view-tree) covers ownership, overlap, and focus. The
[views reference](/reference/views-and-controls) lists the drawing helpers and core
view types.

<DocCapture
  src="/captures/explanation/rendering-frame.png"
  alt="A complete TurboVision terminal frame with menu bar, patterned desktop, active window, window frame, text, and status line."
  caption="The visible terminal is one composed screen buffer, not a sequence of independent prints."
/>

## Text, cells, and attributes

A `Cell` holds one single-column grapheme and a packed drawing attribute.
`TerminalText` splits non-ASCII strings into grapheme clusters, so a combining
sequence with a one-column base stays together instead of being sliced into UTF-8
bytes. Graphemes with any other display width, control characters, malformed input,
and emoji-presentation glyphs are replaced with `?` to preserve the cell grid.

Attributes retain the classic 16-color CGA/VGA palette model. `Attribute` translates
the packed foreground, background, and intensity values to ANSI SGR sequences. The
HTML renderer uses the same canonical palette, making a headless frame useful for
visual inspection as well as terminal output.

## Resizes redraw from a clean screen model

On each input poll, `Screen` asks the driver whether the terminal has been resized.
When it has, the screen obtains the new dimensions, creates new back and front buffers,
and marks the front buffer invalid. The application reflows its desktop and marks
itself dirty. The next redraw therefore produces a complete frame for the new terminal
size instead of trying to preserve pixels with coordinates from the old one.

The buffer has a maximum cell count, so an implausible terminal size is rejected before
it can cause an unbounded allocation. A pending cursor outside the resized screen is
cleared, and the next flush reconciles the terminal cursor state.

## What the driver owns

`Drivers\Driver` is the sole I/O boundary. `AnsiDriver` checks that input and output
are TTYs, records the existing terminal settings, switches input to raw no-echo mode,
enters the alternate screen, hides the cursor, and enables mouse reporting. Its
shutdown path reverses those actions and restores the saved terminal settings.

Driver initialization is paired with cleanup at several levels. `Screen::init()`
shuts a driver down if setup fails partway through; `AnsiDriver::shutdown()` is
idempotent; and the real driver registers signal and process-shutdown cleanup after it
has claimed the terminal. If a terminal write fails, the screen does not commit the
frame or cursor as presented, so a later flush can retry without assuming the terminal
received bytes it did not receive.

The driver also owns raw input reads and reports size changes. `EscapeDecoder` turns
those bytes into framework events, retaining a bounded incomplete suffix between
polls. It understands supported xterm-style keys, SGR mouse input, and the Kitty
keyboard protocol, while consuming terminal replies and unsupported control strings
instead of exposing them as application keystrokes.

## Optional terminal protocols

`TerminalCapabilities` enables two optional features conservatively:

- **Synchronized updates** wrap a non-empty frame in DEC mode 2026 markers when the
  terminal is known to support it. Supporting terminals present the completed frame at
  once; other terminals receive ordinary ANSI output.
- **Kitty keyboard mode** is enabled only for compatible terminal environments and is
  removed during shutdown. It lets the decoder preserve richer key and modifier data.

Unknown terminals, `tmux`, and `screen` take the conservative path by default. For a
known unusual environment, `TVISION_SYNC_UPDATE` and `TVISION_KITTY_KEYBOARD` accept
`1`/`0` (and the equivalent boolean words) to override detection. Enable an override
only when every terminal in the connection path supports that protocol.

## Headless frames use the same rendering path

`HeadlessDriver` replaces the I/O boundary only. It provides a terminal size, queues
input bytes, latches resizes, and captures output, while `Screen`, the view tree,
clipping, palettes, drawing buffers, and diff presenter remain the production
implementations. This makes it possible to exercise an application without claiming a
TTY and inspect the resulting buffer deterministically.

`HtmlRenderer` turns that buffer into a self-contained monospace HTML document, and
`tv-shot` captures the HTML with Chrome or Chromium. For test setup and capture
commands, see [render and capture applications](/cookbook/render-and-capture) and the
[driver reference](/reference/drivers-and-tools).
