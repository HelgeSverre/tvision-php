# TurboVision for PHP

A modern PHP 8.5 port of Borland's Turbo Vision text-mode UI framework. The project
currently includes the terminal driver, double-buffered ANSI renderer, view/event
tree, application shell, windows, frames, scrolling primitives, menus/status lines,
and translated tutorial, BIOS, and full calendar examples.

The public namespace is `HelgeSverre\TurboVision`. Classic `T*` names are expressed
through namespaces instead (`TView` becomes `Views\View`, `TWindow` becomes
`Views\Window`, and so on).

## Requirements

- PHP 8.5 or newer
- `mbstring`, `posix`, and `pcntl`
- A POSIX terminal with `stty` for interactive applications

The headless driver and renderers can be used without an interactive TTY.

## Development setup

```bash
composer install
composer test
composer stan
```

Run the translated examples from a real terminal:

```bash
composer demo
composer demo:bios
composer demo:calendar
composer demo:studio
composer demo:html
```

The calendar demo is a complete macOS-inspired month planner with keyboard and mouse
navigation, an event agenda/sidebar, create/edit/delete sheets, recurrence, and atomic
RFC 5545 `.ics` load/save. By default it uses `calendar.ics` in the working directory;
pass a file and timezone explicitly with
`php examples/php/calendar.php path/to/calendar.ics Europe/Oslo`.

Turbo Studio is a full-screen visual interface builder with draggable/resizable
components, a layers pane and property inspector, undo/redo, foreground-only themes,
JSON project persistence, a clean run-preview mode, and standalone PHP generation.
Run `composer demo:studio` or pass project/export paths to `examples/php/studio.php`.

`bin/tv-render` renders an application frame to HTML through the headless driver;
`bin/tv-shot` creates a PNG when Chrome/Chromium is installed.

## Project status

Milestones M1 (foundation) and M2 (windowing) are implemented. M3—deep menus,
dialogs, and controls—is next. See [ROADMAP.md](ROADMAP.md) for the feature matrix,
known limitations, and later editor/help/persistence milestones.

The repository also contains the original C++ examples and an offline upstream
reference snapshot:

- `src/` — PHP library
- `examples/php/` — runnable PHP examples
- `examples/cpp/` — original tutorial/demo sources used as behavioral oracles
- `tests/` — unit, feature, visual, and real-terminal integration coverage
- `docs/references/` — provenance, documentation, and TVision 0.8 source snapshot

## Provenance

This port is based on Sergio Sigala's UNIX port of Turbo Vision 2.0 (TVision 0.8),
itself a port of Borland's public-domain Turbo Vision. See
[docs/references/README.md](docs/references/README.md) and
[docs/references/UPSTREAM-COPYRIGHT.txt](docs/references/UPSTREAM-COPYRIGHT.txt) for
source and licensing details.
