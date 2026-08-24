# From Turbo Vision to PHP

TurboVision for PHP is a clean PHP reimplementation of a classic character-mode
application framework. It keeps the interaction model that makes desktop-style TUIs
useful—views, windows, menus, commands, focus, palettes, and modal dialogs—while
presenting it through typed modern PHP and a terminal driver boundary.

| Classic name | PHP name |
| --- | --- |
| `TApplication` | `Application\Application` |
| `TProgram` | `Application\Program` |
| `TView` | `Views\View` |
| `TGroup` | `Views\Group` |
| `TWindow` | `Views\Window` |
| `TDialog` | `Dialogs\Dialog` |

The public namespace is `HelgeSverre\TurboVision`; classes use descriptive names
instead of the historic `T` prefix. A small application remains familiar in shape:
subclass `Application`, compose a desktop from views, and call `run()`.

```php
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Window;

final class MyApp extends Application
{
    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = new Desktop($bounds);
        $desktop->insertWindow(new Window(
            Rect::of(8, 3, 60, 16),
            'Notes',
            1,
        ));

        return $desktop;
    }
}

exit((new MyApp())->run());
```

## The model that carries across

The framework is retained-mode. A `Group` owns inserted child views; those views keep
their bounds, state, palette lookup, focus, and interaction state between events.
Windows and dialogs are specialized groups, and the application root owns the desktop,
menu bar, and status line. This turns a terminal program into a compact window system
rather than a collection of prompts printed in sequence.

Several long-lived Turbo Vision concepts remain central:

- **Local geometry and view ownership.** Child rectangles are relative to their
  owner. Insertion order provides z-order, while clipping prevents a child from
  drawing outside the visible portion of its ancestry.
- **Commands as application intent.** Menus, status-line items, buttons, and keys can
  all dispatch the same integer command. An application handles the action once.
- **Cooperative events.** An event travels through the relevant part of the tree. A
  handler clears an event it has consumed, which stops later propagation.
- **Palette chains.** Views request logical colors. Their palettes resolve through the
  owner chain into terminal attributes, allowing a complete surface to be recolored
  without changing its drawing logic.
- **Modal execution.** A dialog can run in a nested event loop and return a closing
  command, while the desktop beneath it stays intact in the retained tree.
- **Cell-based drawing.** Views paint a desired screen buffer; the terminal presenter
  sends only the cells that differ from the previous frame.

Read [the retained view tree](./view-tree) and [the event and command model](./event-model)
for the mechanics behind those ideas.

## PHP-facing API choices

The port uses ordinary PHP language features rather than reproducing C++ conventions.
Geometry is represented by value objects such as `Rect` and `Point`; constructors use
typed parameters; enums and named constants represent fixed choices; and namespaces
make related APIs discoverable through autoloading.

Application extension points are explicit methods. Override `initMenuBar()`,
`initDeskTop()`, and `initStatusLine()` to compose the standard root layout; override
`handleEvent()` to add commands; and override `draw()` to make a custom view. The
framework controls the event loop and rendering lifecycle, while application classes
own their state and domain work.

The terminal is not a global dependency. `Screen` accepts a `Driver`, so production
applications use `AnsiDriver` while tests can supply `HeadlessDriver`. The same view
and rendering code can then be exercised with scripted input and a captured frame.

## Familiar behavior, explicit boundaries

Some historical interfaces are deliberately not compatibility targets:

| Area | PHP framework behavior |
| --- | --- |
| Terminal I/O | ANSI and raw-terminal behavior is isolated behind `Driver`; optional protocols are capability-gated. |
| Persistence | An explicit, allow-listed JSON graph codec replaces native serialization; it neither reads historical Turbo Vision binary streams nor calls PHP `unserialize()`. |
| Text | Drawing uses UTF-8 grapheme-aware cells rather than DOS code pages. |
| Errors | Driver, resource, and input failures use exceptions at their boundaries. |
| Testing | A headless driver, buffer inspection, HTML rendering, and image capture are available without an interactive terminal. |

These boundaries let the classic interaction model operate safely in contemporary
terminal environments. They also keep an application's test suite independent of its
developer's terminal emulator.

## Tutorial lineage and runnable examples

The repository includes `Guide01` through `Guide16` under `examples/php/tutorial/`.
They follow the progression of Borland's Programmer's Guide examples: the early guides
build the basic application shell and commands; later guides add windows, custom views,
scrolling, resize behavior, dialogs, controls, validation, and dialog data.

The examples are useful small programs to run, read, and test alongside the written
tutorials. Larger applications such as Turbo Workbench, Kitchen Sink, Turbo Studio,
Calendar, BIOS, and the OpenCode UI study show the same primitives in more complete
surfaces. Start with [your first application](/tutorials/first-application) for a
guided build, or browse the [application reference](/reference/application) when
adapting an existing PHP program.

## Provenance

The PHP implementation is MIT-licensed. Its primary behavioral reference is TVision
0.8, Sergio Sigala's UNIX port of Turbo Vision 2.0, which in turn derives from
Borland's Turbo Vision. The repository preserves the original reference material and
examples with their own notices; the PHP source is a separate implementation. See the
repository's `NOTICE` file for attribution and licensing details.
