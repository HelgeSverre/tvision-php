# TurboVision for PHP

**Build rich, mouse-friendly terminal applications with the soul of Borland's
Turbo Vision and the ergonomics of modern PHP.**

TurboVision for PHP is a pure-PHP text-mode UI framework with a real view tree,
double-buffered ANSI rendering, keyboard and mouse events, movable windows, menus,
scrolling, responsive layouts, and a headless renderer for tests and screenshots.

```text
 File  Edit  Window  Help
░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░
░░╭─── 1 ─── Turbo Workbench ───────────────────────────────────────────────╮░░░
░░│                                                                         │▒░░
░░│           ╔══════════════════════════════════════════════════╗          │▒░░
░░│           ║                                                  ║          │▒░░
░░│           ║           _____ _   _ ___ ___  ___               ║          │▒░░
░░│           ║          |_   _| | | | _ \ _ )/ _ \              ║          │▒░░
░░│           ║            | | | |_| |   / _ \ (_) |             ║          │▒░░
░░│           ║            |_|  \___/|_|_\___/\___/              ║          │▒░░
░░│           ║                                                  ║          │▒░░
░░│           ║       T U R B O V I S I O N  / /  P H P          ║          │▒░░
░░│           ║                                                  ║          │▒░░
░░│           ║     Classic interfaces. Modern internals.        ║          │▒░░
░░│           ║                                                  ║          │▒░░
░░│           ║                     [ Enter ]                    ║          │▒░░
░░│           ║                                                  ║          │▒░░
░░│           ╚══════════════════════════════════════════════════╝          │▒░░
░░│                                                                         │▒░░
░░╰─────────────────────────────────────────────────────────────────────────╯▒░░
░░░░▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒░
 F1 Help   F3 Open   F4 New   F5 Zoom   F6 Next              Alt-X Exit
```

The mock screen above is plain Unicode. In a real terminal the framework adds the
classic palette, focus state, shadows, incremental repainting, and live interaction.

## Small classes, complete applications

A TurboVision application is ordinary typed PHP. Compose views, override the parts
you need, and let the framework own terminal setup, layout, repainting, input, and
cleanup:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;

require __DIR__ . '/vendor/autoload.php';

final class Workbench extends Application
{
    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = new Desktop($bounds);
        $window = new Window(Rect::of(10, 3, 62, 16), 'Hello, PHP', 1);

        $window->insert(StaticText::centered(
            Rect::of(2, 2, 48, 8),
            "Turbo Vision lives!\n\n"
            . 'Move, resize, zoom, and close this window.',
        ));

        $desktop->insertWindow($window);

        return $desktop;
    }
}

exit(new Workbench()->run());
```

`StaticText::centered(...)` is shorthand for constructing `StaticText` with
`alignment: TextAlignment::Center`; use `Left` or `Right` when the layout calls for
explicit edge alignment.

Even the smallest application is useful:

```php
final class MyApp extends Application {}

exit(new MyApp()->run());
```

That gives you a responsive desktop, File menu, status line, event loop, terminal
restoration, and `Alt-X`/Ctrl-C exit handling.

## What you can build today

- **Windowed TUIs** with focus, overlapping views, shadows, move/resize/zoom/close,
  tiled/cascaded desktops, scroll bars, scrollers, and list viewers.
- **Keyboard and mouse interfaces** with hotkeys, commands, captured drags,
  double-clicks, wheel input, modifier-aware keys, and terminal-resize reflow.
- **Deep application chrome** with nested pull-down menus, context-sensitive status
  hints, command sets, palette modes, and keyboard-selectable windows.
- **Reusable application surfaces** including modal dialogs, buttons, labels,
  check/radio clusters, validated text fields with history, standard message boxes,
  file/directory pickers, editors, outline views, colour configuration, and compiled
  context help.
- **Text and resources** through bounded terminal text devices plus explicit,
  allow-listed PHP-native serialization, named declarative view trees, and string lists.
- **Testable terminal software** through the headless driver, screen buffer, HTML
  renderer, and deterministic frame snapshots.
- **Modern Unicode displays** with grapheme-aware drawing, box characters, the 16
  CGA colors, ANSI diff rendering, and synchronized terminal updates.

The repository includes six larger applications built entirely on the framework:

| Demo | What it shows | Run it |
|---|---|---|
| **Ultra Super Kitchen Sink** | The comprehensive framework tour: feature navigator, deep menus and context menu, dialogs and every control/validator, editors, file pickers, canvas, outline, terminal stream, themes, palette editor, contextual help, safe persistence, declarative resources, tiling, and cascading | `composer demo:kitchensink` |
| **Turbo Workbench** | The default interactive showcase: real pull-down menus, movable windows, dashboard actions, list and scroller views, palette cycling, status shortcuts, confirmations, and modal help surfaces | `composer demo` |
| **Turbo Studio** | A visual TUI builder with drag/resize, inspector, layers, persistent themes, undo/redo, safe save prompts, preview, JSON projects, and themed PHP export | `composer demo:studio` |
| **Calendar** | A polished month planner with mouse navigation, event sheets, recurrence, and metadata-preserving RFC 5545 `.ics` round-tripping | `composer demo:calendar` |
| **BIOS** | A Phoenix-era setup utility with the iconic cyan/blue/gray palette, tabbed settings, editing, contextual help, and keyboard legends | `composer demo:bios` |
| **OpenCode UI study** | A source-guided, model-free recreation of OpenCode's centered home, session transcript, prompt composer, model picker, permission, working, and error states | `composer demo:opencode` |

The repository also ships the full `tvguid01`–`tvguid16` tutorial set and focused
ports of historic acceptance examples for backgrounds, list boxes, validation, text
devices, life, resource loading, and the editor. They are deliberately small,
runnable reference applications rather than a claim that every screen of the original
`TVDEMO` has been recreated.

Turbo Studio is self-hosting: arrange panels, labels, buttons, inputs, lists,
checkboxes, progress bars, and text areas in the designer, then press **F9** to inspect
the generated PHP or **E** to export a standalone application. The selected theme is
part of the project and follows the design into preview and generated code. Dirty
projects get Save / Discard / Cancel confirmation before New, Open, or Quit, and path
alias checks prevent a JSON project and PHP export from overwriting the same file.

The Calendar is equally careful with real data. It preserves provider-specific
calendar properties, attendees, alarms, extra categories, and other unmodeled event
metadata when a loaded file is saved again. Timed events retain second precision, and
all-day recurrence limits remain floating RFC 5545 `DATE` values instead of shifting
across time zones.

The OpenCode UI study is intentionally offline: it does not call a model, run a
command, or modify a file. Press F3 to cycle its screens, F2 for the model picker,
Tab to switch the mock agent, and type into the composer to exercise input and
submission states. The displayed Ctrl-P command palette and Ctrl-T variant picker are
fully interactive, and `/quit` exits cleanly.

Turbo Workbench is the quickest tour of the framework itself. Press `F10` or an
Alt-letter menu hotkey to open a pull-down, use the dashboard buttons, select task
rows with Enter, inspect the independently scrollable activity window, cycle window
palettes with `F8`, and exercise confirmation/help modals. Windows can be moved,
resized, zoomed, closed, and cycled with the mouse or status-line shortcuts.

For the exhaustive tour, start the Kitchen Sink. Its right-hand Feature Navigator
opens every reusable subsystem as a real lab; use Enter, Space, or a double-click,
and right-click the landing Dashboard for the standalone `MenuPopup`. The demo
preserves live windows across undersized-terminal fallbacks and exposes complete
find, replace, save, discard, and contextual-help workflows. See the [coverage
matrix](examples/php/KitchenSink/FEATURES.md) for the exact location of each
framework feature.

## Built for real terminals

Terminal protocols are messy; the framework keeps that mess behind the driver and
event APIs:

- Conservative capability detection enables synchronized updates and the Kitty
  keyboard protocol only where supported, with safe fallbacks through tmux, screen,
  unknown terminals, and custom drivers.
- Kitty CSI-u and legacy xterm sequences retain Shift, Alt, Ctrl, Super, Hyper, Meta,
  Caps Lock, and Num Lock modifier bits.
- OSC, DCS, SOS, PM, APC, cursor-position reports, key releases, and unsupported
  protocol replies are consumed as terminal traffic—never leaked into the app as
  accidental keystrokes.
- Fragmented UTF-8 and escape sequences survive quiet reads long enough to finish,
  while bounded inter-fragment timeouts keep a broken terminal reply from swallowing
  the next real key.
- Resize handling uses `SIGWINCH` when available and automatically falls back to size
  polling. Screen allocation, reads, writes, and shutdown paths are guarded so a bad
  terminal cannot leave the process in raw mode or request an unbounded buffer.

Capabilities can also be forced for unusual terminal setups:

```bash
TVISION_SYNC_UPDATE=1 TVISION_KITTY_KEYBOARD=1 php your-app.php
```

## Install

TurboVision is available as a Composer package and requires PHP 8.5 with
`ext-mbstring`:

```bash
composer require helgesverre/turbovision
```

## Documentation

Start by [building an application](site/tutorials/first-application.md), follow the
[guide](site/tutorials/guide/), browse the [cookbook](site/cookbook/), or use the
[component catalog](site/reference/component-catalog.md) to find a control. There
are focused recipes for application structure, events, menus, dialogs, editors,
testing, help, and persistence.

The Markdown and custom VitePress site live together under [`site/`](site/). To
run the documentation locally:

```bash
cd site
npm install
npm run dev
```

## Try it from source

TurboVision currently targets PHP 8.5 on POSIX terminals.

```bash
git clone https://github.com/HelgeSverre/tvision-php.git
cd tvision-php
composer install

composer demo:kitchensink
composer demo
composer demo:studio
composer demo:calendar
composer demo:bios
composer demo:opencode
```

Interactive applications require `mbstring` and a terminal with `stty`. `pcntl`
improves resize/signal handling and `posix` supplies a legacy TTY-detection fallback,
but both are optional. The headless driver and renderers do not require an interactive
TTY.

To render any compatible application without taking over the terminal:

```bash
php bin/tv-render examples/php/tutorial/Guide05.php frame.html
php bin/tv-shot examples/php/tutorial/Guide05.php frame.png
```

`tv-shot` uses Chrome or Chromium when available.

## Designed like Turbo Vision, written like PHP

The public namespace is `HelgeSverre\TurboVision`. Classic `T*` types become clear
namespaced classes: `TView` is `Views\View`, `TWindow` is `Views\Window`, and
`TApplication` is `Application\Application`.

The architecture keeps the original ideas that still shine—a view tree, palette
chains, command events, modal execution, draw buffers, and deterministic geometry—
behind typed events, enums, immutable rectangles, constructor promotion, and an
injectable driver boundary.

The framework now includes the reusable dialog, editor, file, help, colour, outline,
text-device, and resource layers needed for traditional full-screen applications.
Its resource support intentionally uses an explicit PHP-native, allow-listed format
rather than claiming compatibility with Turbo Vision's binary object streams. See
[ROADMAP.md](ROADMAP.md) for the remaining boundary: the complete historic `TVDEMO`
application is still a stretch goal.

## Development

```bash
composer test   # Pest: unit, feature, visual, and real-terminal coverage
composer test:tia # Pest 5 impact analysis with a safe full-suite fallback
composer stan   # PHPStan at maximum level
composer fuzz   # Seeded protocol, I/O, lifecycle, rendering, and view invariants
composer bench  # Grapheme, drawing, diff, HTML, and screen microbenchmarks
```

## Confidence without a terminal

The terminal boundary is injectable, so applications can be booted, resized, fed
keyboard and mouse bytes, rendered, and inspected entirely in memory. The same
headless path powers feature tests, deterministic HTML/PNG captures, and regression
tests for split escape sequences and terminal protocol replies.

For the less predictable edges, the seeded fuzz runner attacks ten independent
suites—input decoding, screen polling, drawing, view-tree resizing, driver lifecycle,
real stream I/O, widget arithmetic, coordinate arithmetic, signal-interrupted waits,
and live PTY resize storms—with arbitrary bytes, malformed Unicode, integer extremes,
pathological geometry, random event chunking, injected failures, EOF, and SIGWINCH.
Every failure reports its seed for exact replay. A dependency-free benchmark harness
tracks the hot paths without folding a benchmarking framework into the library.

Pest test-impact analysis is deliberately opt-in. Its first covered run records a
baseline and later runs select tests affected by the current changes. The wrapper
uses TIA only when PCOV or Xdebug coverage is active; otherwise it runs the complete
suite, while `composer test` always remains the canonical full verification command.

### Repository map

- [`src/`](src/) — framework source
- [`examples/php/`](examples/php/) — runnable PHP applications and tutorials
- [`examples/cpp/`](examples/cpp/) — original examples used as behavioral oracles
- [`tests/`](tests/) — headless, visual, and real-terminal coverage
- [`docs/references/`](docs/references/) — upstream documentation and source snapshot

## Provenance

This port is based on Sergio Sigala's UNIX port of Turbo Vision 2.0 (TVision 0.8),
itself a port of Borland's public-domain Turbo Vision. See the
[reference notes](docs/references/README.md) and
[`NOTICE`](NOTICE) for licensing and attribution details.
