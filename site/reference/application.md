# Application and program

`Application` is the standard root class for a TurboVision program. It extends `Program`, which extends the root `Group`; it owns the screen, application layout, event loop, command state, and root palette.

```php
use HelgeSverre\TurboVision\Application\Application;

final class MyApp extends Application {}

exit((new MyApp())->run());
```

## `Application`

### Constructor

```php
public function __construct(?Screen $screenOverride = null)
```

When `screenOverride` is omitted, `Application` constructs `new Screen(new AnsiDriver())`. Pass a `Screen` to run against a different `Driver`, including `HeadlessDriver` in tests.

```php
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

$app = new MyApp(new Screen(new HeadlessDriver(80, 25)));
```

### Default layout

After screen initialization, `Application` builds these regions in z-order:

| Region | Default implementation |
| --- | --- |
| Desktop | An empty `Desktop` |
| Menu bar | A `File` menu containing `Exit` (`Cmd::Quit`, `Alt-X`) |
| Status line | An `Alt-X Exit` status item that sends `Cmd::Quit` |

The menu occupies the first row when at least one row is available. The status line occupies the last row when there is room after the menu. The desktop receives all rows between those bands. On a one-row screen, the menu receives that row and the status line has a zero-height rectangle.

<DocCapture
  src="/captures/reference/core-application-shell.png"
  alt="A TurboVision application with a menu bar, centered desktop window, and status line"
  caption="The default application shell reserves the first row for its menu and the last for its status line; the desktop fills the space between them."
/>

### Entry-point guard

```php
public static function runningAsMain(string $file): bool
```

Returns `false` when `argv` is unavailable or its first value is not a string. Otherwise it compares `realpath($_SERVER['argv'][0])` with `realpath($file)`. With normal script paths, this is `true` only when the process entry file is the same file. Use it to keep a file runnable without starting an application when another script includes it:

```php
if (MyApp::runningAsMain(__FILE__)) {
    exit((new MyApp())->run());
}
```

### Layout hooks

Override these protected methods to change the standard application chrome. Each hook receives the rectangle reserved for that region. Returning `null` omits the corresponding view.

| Hook | Return type | `Application` default |
| --- | --- | --- |
| `initScreen(): Screen` | `Screen` | Injected screen, or `Screen(new AnsiDriver())` |
| `initDeskTop(Rect $bounds): ?Desktop` | `Desktop` or `null` | Empty desktop |
| `initMenuBar(Rect $bounds): ?MenuBar` | `MenuBar` or `null` | File / Exit menu |
| `initStatusLine(Rect $bounds): ?StatusLine` | `StatusLine` or `null` | Alt-X Exit status line |

`Program` is intended for specialized roots that supply their own screen. Its default `initScreen()` throws `LogicException`; a `Program` subclass must override it before calling the parent constructor completes.

```php
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Views\Desktop;

final class PlainApp extends Application
{
    protected function initDeskTop(Rect $bounds): ?Desktop
    {
        return new Desktop($bounds);
    }

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }
}
```

## Lifecycle

### `run()`

```php
public function run(): int
```

`run()` resets the program end state, initializes the screen, constructs the root view tree, performs an idle tick and initial redraw, then processes events until the program ends. It always returns `0` after a normal program end; inspect `ended()` to determine whether the root modal state has ended.

An `InputClosedException` from terminal input is treated as a normal quit. Other driver exceptions and failures from layout, drawing, or event handling propagate to the caller after screen shutdown has been attempted. Screen shutdown runs in `finally`, including when initialization or layout fails.

The program ends when it handles `Cmd::Quit` or `Ctrl-C`. The default status line and default File menu both create `Cmd::Quit` through `Alt-X`.

### Startup and deterministic rendering helpers

| Method | Signature | Effect |
| --- | --- | --- |
| `bootForTest()` | `public function bootForTest(): void` | Initializes the screen and builds the view tree without entering the event loop |
| `drawAndFlushForTest()` | `public function drawAndFlushForTest(): void` | Draws the current tree and flushes one frame |
| `backRowsForTest()` | `public function backRowsForTest(): array` | Returns the current screen back-buffer rows as `list<string>` |
| `desktopForTest()` | `public function desktopForTest(): ?Desktop` | Returns the live desktop after layout |
| `pumpResizeForTest()` | `public function pumpResizeForTest(): void` | Polls once and applies a pending resize |

`bootForTest()` shuts the screen down again if screen initialization or layout throws. These methods are useful with an injected headless screen and do not start the blocking loop.

### Suspend and resume

```php
public function suspend(): void
public function resume(): void
```

`suspend()` shuts down an active screen and has no effect when it is already inactive. `resume()` initializes an inactive screen, reflows the existing view tree to the current terminal size, and marks it for redraw. It does not reconstruct the desktop, menu bar, or status line.

## Screen and layout API

| Method | Signature | Contract |
| --- | --- | --- |
| `screen()` | `public function screen(): ?Screen` | Returns the program screen |
| `reflowDesktop()` | `public function reflowDesktop(): void` | Sets root bounds from screen dimensions and changes the bounds of the existing desktop, menu bar, and status line |
| `validView()` | `public function validView(?View $view): ?View` | Returns the same view only when it is non-null and accepts `Cmd::Valid`; otherwise returns `null` |
| `canMoveFocus()` | `public function canMoveFocus(): bool` | Asks the desktop to accept `Cmd::ReleasedFocus`; returns `true` when no desktop exists |
| `insertWindow()` | `public function insertWindow(Window $window): ?Window` | Validates focus movement and the window, then inserts and selects it on the desktop; returns `null` when there is no desktop or validation fails |
| `executeDialog()` | `public function executeDialog(View $view): int` | Executes the view modally through the desktop when present, otherwise through the program; returns the modal end command. A view owned by a different group causes `InvalidArgumentException`. |

Resize handling occurs before dispatching an event returned by the same screen poll. `reflowDesktop()` preserves the existing child regions and asks them to change bounds; `layout()` is the protected operation that clears and rebuilds the root children.

## Events and modal operation

### Event source

| Method | Signature | Contract |
| --- | --- | --- |
| `putEvent()` | `public function putEvent(Event $event): void` | Appends an event to the program FIFO |
| `getEvent()` | `public function getEvent(): Event` | Returns a queued event first; otherwise polls the screen with a 20 ms timeout and returns the next decoded event, or `Event::nothing()` |
| `pumpEvent()` | `public function pumpEvent(): ?Event` | Gets one event for a modal loop, processes resize/idle work, and returns `null` when there is no event to dispatch |
| `handleEvent()` | `public function handleEvent(Event $event): void` | Handles application-level shortcuts and commands, then routes remaining events through the root view tree |
| `handleModalEvent()` | `public function handleModalEvent(Event $event): ?int` | Keeps `Ctrl-C`, `Cmd::Quit`, and `Cmd::Help` active in a modal loop; returns `Cmd::Quit` when the program has ended, otherwise `null` |
| `idle()` | `public function idle(): void` | Updates the status line's help context when it changes and marks the program dirty |

Before `getEvent()` returns a keyboard event, the status line receives it first and may rewrite it as a command. The menu bar then receives remaining key-down events and may consume a hotkey. This preprocessing applies to events read from the screen and to events waiting in the program FIFO.

`handleEvent()` consumes `Ctrl-C` as `Cmd::Quit`. It also handles `Cmd::Quit`, opens the view returned by `createHelpView()` for `Cmd::Help`, and recognizes `Alt-1` through `Alt-9` to select a desktop window by frame number. Override `createHelpView(int $context): ?View` to provide application help; its default return value is `null`.

When overriding `handleEvent()` or `idle()`, call the parent method unless the inherited processing is intentionally replaced.

## Commands

Commands are enabled by default unless explicitly disabled.

| Method | Signature | Effect |
| --- | --- | --- |
| `enableCommand()` | `public function enableCommand(int $command): void` | Enables one command, broadcasts `Cmd::CommandSetChanged`, and schedules a redraw when its state changed |
| `disableCommand()` | `public function disableCommand(int $command): void` | Disables one command, broadcasts `Cmd::CommandSetChanged`, and schedules a redraw when its state changed |
| `commandEnabled()` | `public function commandEnabled(int $command): bool` | Returns whether the command is enabled |
| `enableCommands()` | `public function enableCommands(CommandSet $commands): void` | Enables the codes contained in a `CommandSet` on this program |
| `disableCommands()` | `public function disableCommands(CommandSet $commands): void` | Disables the codes contained in a `CommandSet` on this program |

Changing command availability broadcasts through the view tree, allowing menus, status items, and controls to update their enabled state.

## Root palette

`Program` supplies the root palette against which view palette remaps resolve.

| Method | Signature | Contract |
| --- | --- | --- |
| `getPalette()` | `public function getPalette(): ?Palette` | Returns the explicit palette if set; otherwise a palette for the selected mode |
| `paletteMode()` | `public function paletteMode(): PaletteMode` | Returns the selected built-in mode |
| `setPaletteMode()` | `public function setPaletteMode(PaletteMode $mode): void` | Selects a built-in mode and schedules a redraw when the mode changes |
| `customPalette()` | `public function customPalette(): ?Palette` | Returns the explicit override, if any |
| `setPalette()` | `public function setPalette(?Palette $palette): void` | Sets an explicit root palette or clears it with `null`; schedules a redraw when the effective value changes |

`PaletteMode` values are `Color`, `ClassicColor`, `BlackWhite`, and `Monochrome`. The default is `PaletteMode::Color`. An explicit palette takes precedence over the selected mode; clearing it restores the currently selected built-in mode.
