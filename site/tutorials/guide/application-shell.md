# Build the application shell

The first three steps establish the shell shared by nearly every TurboVision application: the application root, desktop, menu bar, and status line.

## 1. Start with the defaults

An empty subclass is a complete program:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;

require __DIR__ . '/vendor/autoload.php';

final class GuideApp extends Application {}

exit((new GuideApp())->run());
```

`Application` supplies a desktop, File menu, status line, ANSI terminal driver, event loop, and safe terminal cleanup. Override only the parts your application changes.

In reusable examples, protect the entry point so requiring the file from a test does not start an interactive session:

```php
if (GuideApp::runningAsMain(__FILE__)) {
    exit((new GuideApp())->run());
}
```

This is [Guide01.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide01.php).

## 2. Define the status line

`initStatusLine()` receives the one-row bounds already calculated by the application. A `StatusDef` chooses which items are visible for a help-context range; `StatusDef::all()` is the common default.

```php
protected function initStatusLine(Rect $bounds): StatusLine
{
    return new StatusLine($bounds, StatusDef::all(
        new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        new StatusItem('~Alt-F3~ Close', Key::AltF3, Cmd::Close),
    ));
}
```

Each item connects display text, a key, and a command. The `~...~` markers highlight the mnemonic; they are not printed literally. See [Guide02.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide02.php).

## 3. Add menus

Build each top-level menu from a `SubMenu`, then populate it with `MenuItem` objects:

```php
private const int NewWindow = Cmd::FirstSafeUser + 1;

protected function initMenuBar(Rect $bounds): MenuBar
{
    return new MenuBar(
        $bounds,
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('~N~ew', self::NewWindow, Key::F4, 'F4'),
            MenuItem::separator(),
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
        ),
        new SubMenu('~W~indow', Key::AltW)->items(
            new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
            new MenuItem('~Z~oom', Cmd::Zoom, Key::F5, 'F5'),
        ),
    );
}
```

Framework commands live on `Cmd`. Allocate new application commands from `Cmd::FirstSafeUser` upward and keep them in one class or enum-like constants file. `FirstUser` remains for compatibility with historical source, but overlaps old file-dialog values. A menu command does nothing until a view handles it; the next chapter wires `NewWindow` to a window factory.

Run [Guide03.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide03.php), open the menus with F10 or Alt+letter, and trigger the built-in Window commands.

Next: [create windows and scrollable content](./windows-and-scrolling).
