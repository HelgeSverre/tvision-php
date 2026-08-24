# Build a complete application

Grow a TurboVision application in small, runnable steps. Begin with the framework defaults, then add application chrome, commands, custom windows, responsive scrolling views, dialogs, controls, and committed form data.

You can run any step from a source checkout:

```bash
composer install
php examples/php/tutorial/Guide01.php
```

Replace `Guide01` with any step through `Guide16`. Each program has a matching test under `tests/Feature/`, so it is also safe to read as tested framework code.

## What you will build

| Steps | Result | Continue with |
| --- | --- | --- |
| 01–03 | An application shell with a desktop, status shortcuts, and menus | [Build the application shell](./application-shell) |
| 04–10 | Movable windows containing custom-drawn, scrollable, responsive panes | [Add windows and scrolling](./windows-and-scrolling) |
| 11–16 | Modeless and modal dialogs, buttons, choices, input, and committed form data | [Add dialogs and data](./dialogs-and-data) |

## Source examples for each step

| Step | Addition | Main API |
| --- | --- | --- |
| 01 | Smallest complete application | `Application::run()` |
| 02 | Status-line shortcuts | `initStatusLine()` |
| 03 | File and Window menus | `initMenuBar()` |
| 04 | A command that creates windows | `handleEvent()`, `Desktop::insertWindow()` |
| 05 | A custom view inside each window | `View::draw()`, `Window::insert()` |
| 06 | Lines loaded from a file | `writeStr()` |
| 07 | Complete row painting | `DrawBuffer`, `writeLine()` |
| 08 | One scrollable viewport | `Scroller::setLimit()`, `scrollTo()` |
| 09 | Independently scrolling split panes | `ScrollBar`, `growMode` |
| 10 | A safe minimum window size | `Window::sizeLimits()` |
| 11 | A modeless dialog | `Desktop::insertWindow()` |
| 12 | A modal dialog | `Group::execView()` |
| 13 | Default and cancel buttons | `Button`, `Cmd::Ok`, `Cmd::Cancel` |
| 14 | Check boxes and radio buttons | `CheckBoxes`, `RadioButtons`, `Label` |
| 15 | Editable text | `InputLine` |
| 16 | Preload and commit a form | `Dialog::setData()`, `getData()` |

## Where this guide comes from

The sequence is adapted from Turbo Vision's original C++ tutorial, but the code and recommendations follow this PHP implementation: typed classes, Composer autoloading, collision-free application commands, deterministic window placement, Unicode-aware drawing, and headless tests.

The original material remains in [`examples/cpp/tutorial`](https://github.com/HelgeSverre/tvision-php/tree/main/examples/cpp/tutorial), with its original notices. The runnable PHP adaptations are in [`examples/php/tutorial`](https://github.com/HelgeSverre/tvision-php/tree/main/examples/php/tutorial).

After the guide, use [Structure a larger application](/cookbook/structure-an-application) to turn the single-file teaching style into a maintainable project layout.
