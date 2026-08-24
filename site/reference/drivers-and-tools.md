# Drivers, rendering, and tools

## Driver contract

`HelgeSverre\TurboVision\Drivers\Driver` is the terminal I/O boundary used by `Screen`:

```php
interface Driver
{
    public function init(): void;
    public function shutdown(): void;
    /** @return array{0: int, 1: int} [columns, rows] */
    public function size(): array;
    public function write(string $bytes): void;
    public function pollInput(int $timeoutMs): string;
    public function resized(): bool;
}
```

`init()` and `shutdown()` are idempotent. `shutdown()` must restore every state change made by `init()`, including after a partial initialization failure. `size()` returns `[columns, rows]`. `resized()` is a boolean latch: it returns `true` once when one or more resize notifications are pending, then clears the notification. Multiple changes before that read are coalesced.

`pollInput()` returns raw bytes available before its non-negative timeout expires, or an empty string when no input arrived. It throws `InvalidArgumentException` for a negative timeout, `InputClosedException` when input has closed, and `DriverException` for other driver failures.

### `AnsiDriver`

`AnsiDriver` is the interactive POSIX-terminal implementation. `AnsiDriver::forStdio()` uses the process standard streams:

```php
use HelgeSverre\TurboVision\Drivers\AnsiDriver;

$driver = AnsiDriver::forStdio();
```

On initialization it verifies that standard input and output are TTYs, saves `stty` settings, enters raw no-echo mode, makes input non-blocking, enters the alternate screen, hides the cursor, and enables SGR mouse reporting. It restores the saved settings, mouse mode, cursor, and normal screen on shutdown.

`AnsiDriver::forStdio(trackMouseMotion: true)` additionally enables terminal any-motion tracking. The default tracks button/motion reports only while a button is held.

With `pcntl`, the driver handles `SIGWINCH` and restores the terminal before exiting on `SIGINT`, `SIGTERM`, or `SIGHUP`. Without it, `resized()` compares the terminal size at most once every 250 ms. Invalid or unavailable `stty size` output falls back to `80 × 24`.

`DriverException` reports a non-TTY, unavailable `stty`, failed reads, or failed writes. `InputClosedException` is the specialized terminal-EOF case.

### `HeadlessDriver`

`HeadlessDriver` is an in-memory driver for tests, snapshots, and non-interactive rendering:

```php
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;

$driver = new HeadlessDriver(cols: 100, rows: 30);
$driver->feedInput("\e[A");
$bytes = $driver->pollInput(0);

$driver->resizeTo(120, 40);
assert($driver->resized());

$ansi = $driver->takeOutput();
```

| Method | Behavior |
| --- | --- |
| `feedInput(string $bytes)` | Appends raw bytes for the next `pollInput()` call. |
| `output()` | Returns captured writes without clearing them. |
| `takeOutput()` | Returns captured writes and clears the capture. |
| `resizeTo(int $cols, int $rows)` | Changes the size and latches a resize notification. |
| `isInitialised()` | Reports whether `init()` has been called without a later `shutdown()`. |

Dimensions must be non-negative. The headless driver defaults to `80 × 24` and advertises synchronized-update support unless constructed with different `TerminalCapabilities`.

### Optional capabilities

Drivers can implement `ProvidesTerminalCapabilities` to supply a `TerminalCapabilities` value to `Screen`. The two capabilities are:

| Property | Effect |
| --- | --- |
| `synchronizedUpdates` | Wrap each non-empty frame in DEC mode 2026 begin/end sequences. |
| `kittyKeyboard` | Enable Kitty's disambiguated keyboard protocol while the ANSI driver is active. |

`TerminalCapabilities::detectProcess()` reads `TERM`, `TERM_PROGRAM`, and `KITTY_WINDOW_ID`. Kitty, Ghostty, and Contour are detected conservatively; `screen` and `tmux` use the safe disabled defaults. Set either override to `1`, `true`, `yes`, or `on` to force it, or `0`, `false`, `no`, or `off` to disable it:

```bash
TVISION_SYNC_UPDATE=1 TVISION_KITTY_KEYBOARD=1 php your-app.php
```

| Variable | Controls |
| --- | --- |
| `TVISION_SYNC_UPDATE` | DEC synchronized updates. |
| `TVISION_KITTY_KEYBOARD` | Kitty keyboard protocol. |

## Input decoding

`EscapeDecoder` turns raw driver bytes into `DecodeResult` values. A result contains `events` plus a `remainder` for an incomplete UTF-8 or control sequence; pass that remainder into the next decode call, or let `Screen::pollEvents()` manage it.

The decoder recognizes printable UTF-8 text, control keys, legacy xterm and rxvt navigation/function-key sequences, SGR mouse reports, and Kitty CSI-u keyboard input. It preserves modifier bits for Shift, Alt, Ctrl, Super, Hyper, Meta, Caps Lock, and Num Lock. The legacy `Key` identities are retained where an exact historical modified key exists.

Mouse coordinates are converted from the terminal's one-based coordinates to zero-based `Point` values. Button down/up, button motion, wheel movement, and repeated clicks at the same point within 0.5 seconds are emitted as mouse events. OSC, DCS, SOS, PM, APC, cursor-position replies, key releases, malformed sequences, and unsupported protocol replies are consumed without becoming application key events.

`Screen` retains incomplete input across polls. A run of pending Escape bytes becomes Escape key events after 40 ms of quiet; other incomplete sequences expire after 250 ms. The retained input buffer is capped at 4096 bytes.

## Screen and rendering

`Terminal\Screen` owns the driver, input decoder, ANSI encoder, two drawing buffers, and cursor state:

```php
use HelgeSverre\TurboVision\Terminal\Screen;

$screen = new Screen($driver);
$screen->init();

$screen->clear();
// Draw into $screen->back().
$screen->flush();

$events = $screen->pollEvents(50);
if ($screen->wasResized()) {
    // Recalculate view bounds and redraw.
}

$screen->shutdown();
```

| Method | Behavior |
| --- | --- |
| `back()` | Returns the current back `Drawing\Buffer` for drawing. |
| `clear()` | Replaces the back buffer with blank cells. |
| `flush()` | Diffs the presented front buffer against the back buffer and writes the resulting frame. |
| `pollEvents(int $timeoutMs)` | Processes a pending resize, reads and decodes input, and returns events. |
| `wasResized()` | Returns and clears the screen-level resize latch. |
| `setCursor(?Point $cursor)` | Requests a zero-based hardware cursor position, or hides it with `null`. |
| `size()`, `cols()`, `rows()` | Return the active buffer dimensions. |

`flush()` does nothing when neither cells nor cursor state changed. A resize reallocates both buffers, invalidates the front state, and causes the next flush to repaint every cell. A requested cursor outside the current buffer is treated as hidden.

### ANSI presentation

`AnsiEncoder` creates ANSI/VT byte sequences. Its coordinate methods are zero-based, while emitted cursor-position sequences use the terminal's one-based positions. It can emit cursor movement, CGA/VGA-style SGR attributes, alternate-screen setup, cursor visibility, mouse mode, synchronized updates, and Kitty keyboard mode.

`DiffPresenter::present(Buffer $front, Buffer $back, AnsiEncoder $encoder): string` is pure. Buffers must have equal dimensions. It scans rows top to bottom, groups adjacent changed cells into runs, moves the cursor once per run, and changes SGR style only when the cell attribute changes within that run.

### HTML rendering

`HtmlRenderer` produces a self-contained HTML document from a `Drawing\Buffer`:

```php
use HelgeSverre\TurboVision\Rendering\HtmlRenderer;

$html = (new HtmlRenderer())->render($screen->back());
file_put_contents('frame.html', $html);
```

Cells sharing an attribute are grouped into a span. Foreground and explicit background colors use the canonical 16-color CGA/VGA palette. By default, black backgrounds are transparent so the browser canvas remains visible. Pass `useDefaultBackgroundForBlack: false` to render literal black backgrounds.

<DocCapture
  src="/captures/reference/special-html-headless.png"
  alt="A headless Turbo Vision application frame rendered as an HTML capture"
  caption="HtmlRenderer preserves the cells, frame, and palette state from a deterministic headless buffer."
/>

## Command-line tools

Run these commands from a source checkout after `composer install`.

### `tv-render`

```text
php bin/tv-render <example.php> <out.html> [cols] [rows]
```

Loads an example file, finds the newly declared `Application` subclass, boots it with a `HeadlessDriver`, draws one frame, and writes the result through `HtmlRenderer`. Columns and rows default to `80` and `25`. The command exits with status `2` for missing arguments, a missing example, or no application subclass.

### `tv-shot`

```text
php bin/tv-shot <example.php> <out.png> [cols] [rows]
```

Runs `tv-render` and captures its HTML result with headless Google Chrome or Chromium. It exits with status `3` when neither browser executable is available.

### `tvhc`

```text
php bin/tvhc <source.txt> <output.tvhelp> [contexts.php]
```

Compiles context-help source with `HelpCompiler`. The optional third argument supplies a PHP context map. Invalid argument counts exit with status `64`; an unreadable source file exits with status `66`.

### `tv-fuzz`

```text
php bin/tv-fuzz [iterations] [seed]
php bin/tv-fuzz [--iterations N] [--seed N]
```

Runs seeded fuzz suites for decoding, screen polling and lifecycle, driver I/O, drawing, views, arithmetic, and available signal/PTY paths. The default is 2000 iterations with a random positive seed. Supply the reported seed to replay a failure. `--help` prints usage; invalid options or values exit with status `2`.

### `tv-test-impact`

```text
php bin/tv-test-impact [pest arguments...]
```

Runs Pest impact analysis with `--parallel --tia --filtered` when enabled PCOV or Xdebug coverage is available. Otherwise it runs the full Pest suite. It exits with status `2` when Pest is not installed.

### `render-demo`

```text
php bin/render-demo
```

Starts a small interactive `AnsiDriver`/`Screen` demonstration. Press `q` or Escape to exit. It requires a usable interactive terminal.

## Composer scripts

| Script | Command |
| --- | --- |
| `composer test` | Run Pest. |
| `composer test:tia` | Run `tv-test-impact`. |
| `composer stan` | Run PHPStan. |
| `composer fuzz` | Run `tv-fuzz`. |
| `composer bench` | Run core benchmarks. |
| `composer demo` | Run the default workbench demo. |
| `composer demo:kitchensink` | Run the Kitchen Sink demo. |
| `composer demo:workbench` | Run the workbench demo. |
| `composer demo:bios` | Run the BIOS demo. |
| `composer demo:opencode` | Run the OpenCode UI study. |
| `composer demo:calendar` | Run the calendar demo. |
| `composer demo:studio` | Run the studio demo. |
| `composer demo:html` | Run the HTML-rendering demo. |
