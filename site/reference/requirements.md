# Requirements and support

## Package requirements

| Requirement | Version or condition |
| --- | --- |
| PHP | 8.5 or later |
| Composer | Required to install the package and its autoloader |
| `ext-mbstring` | Required |

Install the published package with Composer:

```bash
composer require helgesverre/turbovision
```

Application classes are under the `HelgeSverre\TurboVision` namespace. The package is a library: require Composer's autoloader before constructing an application.

```php
require __DIR__ . '/vendor/autoload.php';
```

## Interactive ANSI applications

`Application` creates an ANSI-backed screen unless a `Screen` is injected. Running that default screen requires all of the following:

| Requirement | Used for |
| --- | --- |
| A POSIX-style terminal session | `stty`-based raw-mode setup and terminal-size queries |
| `STDIN` and `STDOUT` connected to TTY streams | Interactive input and ANSI output |
| The `stty` executable on `PATH` | Saving, entering, and restoring terminal mode |
| PHP's `shell_exec()` available | Invoking `stty` |

The ANSI driver rejects redirected streams, pipes, files, and non-TTY streams before it changes terminal state. It throws `HelgeSverre\TurboVision\Exceptions\DriverException` when the streams are not terminals or `stty` cannot be used.

The driver enters the alternate screen, uses raw input with echo disabled, hides the cursor, and enables mouse reporting while the program runs. On normal shutdown, a handled input close, or an exception escaping the event loop, the program calls driver shutdown to restore the terminal state. Direct signal handling for resize and termination is available when `ext-pcntl` is installed; without it, resize detection falls back to periodic size polling.

### Capability overrides

Capability detection can be overridden for the process that starts the application:

| Variable | Accepted values | Effect |
| --- | --- | --- |
| `TVISION_SYNC_UPDATE` | `1`/`true`/`yes`/`on`, `0`/`false`/`no`/`off` | Enables or disables synchronized terminal updates |
| `TVISION_KITTY_KEYBOARD` | `1`/`true`/`yes`/`on`, `0`/`false`/`no`/`off` | Enables or disables Kitty keyboard protocol handling |

Set an override only when the complete terminal path supports the selected protocol. For example:

```bash
TVISION_SYNC_UPDATE=0 php bin/console.php
```

## Optional extensions

| Extension | Use |
| --- | --- |
| `ext-pcntl` | `SIGWINCH` resize notification and signal-aware terminal restoration |
| `ext-posix` | TTY detection fallback when `stream_isatty()` is unavailable |
| `ext-pcov` | Pest test-impact analysis and coverage support in development |
| `ext-xdebug` | Alternative development coverage driver |

`ext-pcntl` and `ext-posix` are Composer suggestions, not package requirements. The framework operates without them where PHP provides the required stream APIs; signal-driven resize handling is not available without `ext-pcntl`.

## Headless and rendered applications

`HeadlessDriver` is a no-I/O implementation of the `Driver` contract. It does not require a TTY, `stty`, `pcntl`, or `posix`; it accepts scripted input, captures ANSI output, and reports a configurable terminal size. Use it through `Screen` when testing an application:

```php
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

$driver = new HeadlessDriver(80, 25);
$app = new class(new Screen($driver)) extends Application {};

$app->bootForTest();
$app->drawAndFlushForTest();
```

`HeadlessDriver` accepts non-negative dimensions. Its constructor defaults to 80 columns by 24 rows and throws `InvalidArgumentException` for a negative width or height.

The repository's HTML renderer can render compatible applications without taking over the terminal. PNG capture through `bin/tv-shot` additionally requires Chrome or Chromium.

## Terminal support

The bundled ANSI driver decodes terminal input and emits ANSI output itself; it does not require `ncurses`. It supports keyboard input, SGR mouse input, terminal resizing, Unicode rendering, alternate-screen operation, and diff-based output. Terminal capability detection is conservative for tmux, screen, and unknown terminal identifiers.

Terminal behavior ultimately depends on the emulator and any multiplexer between it and PHP. A terminal that cannot provide the required raw-mode or ANSI features cannot run the interactive driver; use a headless screen for automated tests and non-interactive rendering.
