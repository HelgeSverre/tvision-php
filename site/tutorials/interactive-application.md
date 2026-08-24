# Add commands and a dialog

Continue with `hello.php` from the first tutorial. You will add an **About** action that opens from the Help menu or with `F1`. Both entry points produce the same command event, so the application handles the action in one place.

## Add the imports and command

Add these imports below the existing `use` statements:

```php
use HelgeSverre\TurboVision\Dialogs\MessageBox;
use HelgeSverre\TurboVision\Dialogs\MsgBoxFlag;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
```

Inside `HelloApp`, declare a named command value:

```php
public const int About = Cmd::FirstSafeUser;
```

Use `Cmd::FirstSafeUser` for new application actions. It is `200`, which avoids command values reserved by the framework and the standard file dialogs. The public visibility lets the headless test in the next tutorial dispatch the command directly.

## Replace the menu bar

Add this method to `HelloApp`:

```php
protected function initMenuBar(Rect $bounds): MenuBar
{
    return new MenuBar($bounds, new SubMenu('~H~elp', Key::AltH)->items(
        new MenuItem('~A~bout', self::About, Key::F1, 'About this application'),
    ));
}
```

Tildes mark menu mnemonics: `Alt-H` opens the Help menu and `A` chooses About. `F1` is an alternate shortcut. The final string is the status hint shown while the item is selected.

This replaces the inherited File menu. If you want both menus, pass a `SubMenu` for File and another for Help to `new MenuBar(...)`; the menu-building pattern is covered in [build menus and status lines](/cookbook/menus-and-status-lines).

## Handle the command

Add this event handler to `HelloApp`:

```php
public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if ($event->isCommand(self::About)) {
        MessageBox::show(
            $this,
            'Built with TurboVision for PHP.',
            MsgBoxFlag::Information | MsgBoxFlag::OkButton,
        );

        $event->clear();
    }
}
```

`parent::handleEvent()` preserves inherited commands and routes the event through the existing view tree. The menu item and `F1` shortcut both arrive as `EventType::Command` with the integer in `self::About`.

`MessageBox::show()` constructs and runs a modal dialog over its host. Passing `$this` makes the application the modal host. The return value is the dialog's end command, which is useful for confirmations with `MsgBoxFlag::YesNoCancel` or `MsgBoxFlag::OkCancel`; this informational dialog only needs an OK button.

Calling `$event->clear()` marks the command as handled. Do this after your action so another view does not interpret the same event.

## Try it

Run the application again:

```bash
php hello.php
```

Open **Help → About** with `Alt-H`, then `A`, or press `F1`. Press `Enter` to accept the default OK button, or select it with the mouse. Press `Alt-X` to leave the application.

<DocCapture
  src="/captures/tutorials/interactive-application.png"
  alt="The Hello, PHP terminal application with a Help menu bar item and a centered Information dialog reading Built with TurboVision for PHP, with an OK button."
  caption="The information dialog appears over the application window and gives the default OK button focus."
/>

If `F1` is claimed by your terminal emulator or multiplexer, use the Help menu instead. The menu mnemonic is handled by the application once the terminal delivers the keys.

## Complete `HelloApp`

At this point, the class contains the desktop method from the first tutorial plus the following members:

```php
public const int About = Cmd::FirstSafeUser;

protected function initMenuBar(Rect $bounds): MenuBar
{
    return new MenuBar($bounds, new SubMenu('~H~elp', Key::AltH)->items(
        new MenuItem('~A~bout', self::About, Key::F1, 'About this application'),
    ));
}

public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if ($event->isCommand(self::About)) {
        MessageBox::show(
            $this,
            'Built with TurboVision for PHP.',
            MsgBoxFlag::Information | MsgBoxFlag::OkButton,
        );
        $event->clear();
    }
}
```

For command ranges, event construction, and propagation rules, see [events, keys, and commands](/reference/events-keys-commands). Continue to [test without a terminal](./headless-testing) to render this application in memory.
