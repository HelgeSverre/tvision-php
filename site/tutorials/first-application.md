# Build your first application

In this tutorial, you will create a terminal application with a movable, resizable window. `Application` owns terminal setup, drawing, input, and cleanup; your class supplies the desktop contents.

## Create `hello.php`

Create this file in the directory where you installed the package:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;

require __DIR__ . '/vendor/autoload.php';

final class HelloApp extends Application
{
    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = new Desktop($bounds);
        $window = new Window(Rect::of(8, 3, 58, 15), 'Hello, PHP', 1);

        $window->insert(StaticText::centered(
            Rect::of(2, 2, 48, 8),
            "TurboVision for PHP lives!\n\n"
            . 'Move, resize, zoom, and close this window.',
        ));

        $desktop->insertWindow($window);

        return $desktop;
    }
}

if (HelloApp::runningAsMain(__FILE__)) {
    exit((new HelloApp())->run());
}
```

The `runningAsMain()` guard makes the file safe to load from a test. It starts the interactive loop only when PHP executes `hello.php` directly.

## Run the application

```bash
php hello.php
```

You should see a desktop with a File menu, an `Alt-X Exit` status hint, and one window titled **Hello, PHP**. The defaults inherited from `Application` provide the menu, status line, event loop, ANSI terminal driver, and terminal restoration.

<DocCapture
  src="/captures/tutorials/first-application.png"
  alt="An 80 by 25 TurboVision for PHP terminal application with a File menu, Hello, PHP window, centered welcome text, and Alt-X Exit status hint."
  caption="The first application after its initial draw."
/>

Try the following:

- Drag the window title bar to move the window when your terminal reports mouse input.
- Drag the bottom-right resize grip to resize the window.
- Use the frame controls to zoom, restore, or close the window.
- Press `Alt-X` to exit. The terminal should return to its normal state.

If the window does not fit, enlarge the terminal and run the program again. The example is designed for a normal 80-column terminal.

## Read the view tree

The code creates this ownership tree:

```text
HelloApp
├── Desktop
│   └── Window "Hello, PHP"
│       ├── Frame
│       └── StaticText
├── MenuBar
└── StatusLine
```

`Application` creates the menu bar and status line unless you override their factory methods. Your `initDeskTop()` override receives the remaining area and returns the desktop that owns the window.

`Window` adds its own frame during construction. Insert content such as `StaticText` into the window, then insert the window into the desktop. The framework draws the tree in the correct order and routes events through it.

## Work with rectangles

`Rect::of(left, top, right, bottom)` uses an exclusive right and bottom edge. This means:

```php
Rect::of(8, 3, 58, 15)->width();  // 50
Rect::of(8, 3, 58, 15)->height(); // 12
```

The window rectangle is in desktop coordinates. The text rectangle is local to the window, so `Rect::of(2, 2, 48, 8)` starts two cells from the window's local origin. Keeping parent and child coordinates separate makes layouts predictable when a window moves or is resized.

`StaticText::centered()` centers every rendered line and wraps text to its own width. Use `new StaticText(...)` for left-aligned text or `StaticText::rightAligned(...)` when you need right alignment.

## Next step

The application can now display a window but has no application-specific actions. Continue to [add commands and a dialog](./interactive-application).

For a compact list of application factory hooks and lifecycle methods, see [Application lifecycle](/reference/application). The [cookbook](/cookbook/) covers focused view-composition and dialog recipes.
