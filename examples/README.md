# Example Programs

## Runnable PHP examples

- `php/tutorial/Guide01.php` through `Guide10.php` progressively exercise the port.
- `php/bios.php` is a full-screen BIOS setup interface.
- `php/calendar.php` is a macOS-inspired month calendar with an agenda sidebar,
  complete event editing, recurrence, and RFC 5545 `.ics` persistence.
- `php/studio.php` is a visual TUI builder with direct manipulation, an inspector,
  layers, undo/redo, JSON projects, live preview, and runnable PHP export.
- `php/html-render.php` demonstrates the headless HTML renderer.

Run `composer demo:calendar` or `composer demo:studio`; their detailed controls live
in [`php/Calendar/README.md`](php/Calendar/README.md) and
[`php/Studio/README.md`](php/Studio/README.md).

## Original source material

Original C++ example programs from Sergio Sigala's TVision 0.8, preserved verbatim so
we can translate them to `HelgeSverre\TurboVision` and use them as **acceptance
tests** for the port. Two sets:

- [`cpp/tutorial/`](cpp/tutorial/) — small, focused, progressive lessons. `tvguid01`–`tvguid16`
  are Borland's official "Programmer's Guide" walkthrough (each builds on the previous);
  the rest are Sigala's standalone how-tos.
- [`cpp/demo/`](cpp/demo/) — one complete multi-window application (the classic TVDEMO),
  split across several files, exercising most of the framework at once.

Provenance & licensing: see [`../docs/references/README.md`](../docs/references/README.md).
The `tvguid*` files carry Borland's 1991 public-domain notice; the rest are Sigala's.

---

## The canonical "hello world" (`tvguid01.cc`)

The smallest possible TV app — an empty desktop with the default menu bar and status
line — is just an `Application` subclass and `run()`:

```cpp
#define Uses_TApplication
#include <tvision/tv.h>

class TMyApp : public TApplication {
public:
    TMyApp() : TProgInit( &TMyApp::initStatusLine,
                          &TMyApp::initMenuBar,
                          &TMyApp::initDeskTop ) {}
};

int main() {
    TMyApp myApp;
    myApp.run();
    return 0;
}
```

This is the first translation target — getting it working proves the event loop,
screen driver, desktop, default menu bar and status line all stand up. A plausible
PHP shape (for discussion later, **not decided**):

```php
use HelgeSverre\TurboVision\Application;

final class MyApp extends Application {}

(new MyApp())->run();
```

---

## Tutorial — `cpp/tutorial/` (recommended translation order)

The `tvguid` series is explicitly incremental — translate them in order; each adds
exactly one capability, which makes them an ideal **porting curriculum** and
regression suite.

| # | File | Adds / demonstrates | Exercises |
|---|------|--------------------|-----------|
| 01 | `tvguid01.cc` | Empty app: desktop + default menu bar + default status line | Application, event loop |
| 02 | `tvguid02.cc` | Two commands in the status line | StatusLine, StatusItem, commands |
| 03 | `tvguid03.cc` | Commands in **both** menu bar and status line | MenuBar, MenuItem, SubMenu |
| 04 | `tvguid04.cc` | Custom windows, instanced from menu commands | Window subclassing, `cmXxx` dispatch |
| 05 | `tvguid05.cc` | Custom **views** inserted into windows | View subclass, `draw()`, insert |
| 06 | `tvguid06.cc` | Basic text-scrolling window (imperfect draw) | Scroller (naive) |
| 07 | `tvguid07.cc` | Same, improved `draw()` method | Scroller draw correctness |
| 08 | `tvguid08.cc` | Scrolling interior with scroll bars | ScrollBar wiring |
| 09 | `tvguid09.cc` | Multiple panes | Multiple Scrollers in a Group |
| 10 | `tvguid10.cc` | Better resize handling | Grow modes, `changeBounds` |
| 11 | `tvguid11.cc` | Add a dialog box | Dialog, execView |
| 12 | `tvguid12.cc` | Make the dialog **modal** | Modal loop, command result |
| 13 | `tvguid13.cc` | Extra buttons in the dialog | Button broadcast/commands |
| 14 | `tvguid14.cc` | Checkboxes, radio buttons, labels | Cluster, CheckBoxes, RadioButtons, Label |
| 15 | `tvguid15.cc` | Input line in the dialog | InputLine |
| 16 | `tvguid16.cc` | Saving & restoring dialog contents | `setData`/`getData`, data records |

### Standalone how-tos (Sigala's)

| File | Demonstrates | Exercises |
|------|-------------|-----------|
| `background.cc` | Change the desktop background pattern | Background, custom desktop |
| `listbox.cc` | Use a list box | ListBox, Collection, ScrollBar |
| `load.cc` | Create + stream custom views | Streamable, object streaming |
| `nomenus.cc` | A dialog app with no menu bar / status line | Minimal app shell, execView |
| `splash.cc` | Show a dialog box at startup | Startup modal dialog |
| `validator.cc` | Range validators on input lines | InputLine, RangeValidator |
| `tvedit.cc` | A simple but working text editor | Editor, FileEditor, EditWindow, file dialogs |
| `tvlife.cc` | Conway's Game of Life | Custom view, timing, drawing, mouse |
| `basicMakefile` | Build template (not a program) | — (reference only) |

---

## Demo application — `cpp/demo/` (the integration test)

The classic **TVDEMO**: one app that wires together menus, windows, dialogs, the help
system, gadgets, and streamable desktop save/restore. Good as the *final* end-to-end
acceptance target once the individual pieces work.

| File(s) | Role |
|---------|------|
| `tvdemo1.cc`, `tvdemo2.cc`, `tvdemo3.cc` | The `TVDemo` application itself (menu/statusline, window mgmt, event handling, desktop save/restore) split across 3 translation units |
| `tvdemo.h`, `tvcmds.h` | Shared app header and command-code constants |
| `ascii.cc` / `ascii.h` | ASCII-table viewer window (custom view + mouse) |
| `calc.cc` / `calc.h` | A pop-up calculator (input handling, custom view) |
| `calendar.cc` / `calendar.h` | A monthly calendar window (custom drawing) |
| `puzzle.cc` / `puzzle.h` | The 15-puzzle game (custom view, mouse, state) |
| `fileview.cc` / `fileview.h` | A file-viewer window (text view + scrolling) |
| `gadgets.cc` / `gadgets.h` | Status-line **gadgets**: a live clock + heap monitor |
| `mousedlg.cc` / `mousedlg.h` | A "mouse options" dialog (clusters, input lines, validators) |
| `demohelp.h`, `DEMOHELP.H32` | Help-context constants + the **compiled** help file |

> `DEMOHELP.H32` is the binary output of the `tvhc` help compiler from a `.txt`
> source (`source/tvision-0.8/tvhc/DEMOHELP.TXT`). When we port the help system we
> can either port `tvhc` too or define a simpler PHP-native help-source format.

---

## PHP layout

Translated programs live under `examples/php/`; the Guide series mirrors the original
tutorial progression, while larger standalone examples use their own namespace folder
and a small runnable entrypoint. Each translated example also has feature-test coverage.
