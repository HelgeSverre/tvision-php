# Build menus and status lines

Create application chrome by overriding `initMenuBar()` and `initStatusLine()`. The program reserves one terminal row for each factory that returns a view.

## Add a menu bar

`~` marks a mnemonic character. Give each top-level `SubMenu` an Alt shortcut and give command items an optional direct shortcut.

```php
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;

protected function initMenuBar(Rect $bounds): ?MenuBar
{
    return new MenuBar(
        $bounds,
        new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('~O~pen…', Cmd::Open, Key::F3, 'Open a file'),
            new MenuItem('~S~ave', Cmd::Save, Key::CtrlS, 'Save changes'),
            MenuItem::separator(),
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit'),
        ),
        new SubMenu('~W~indow', Key::AltW)->items(
            new MenuItem('~T~ile', Cmd::Tile),
            new MenuItem('~C~ascade', Cmd::Cascade),
        ),
    );
}
```

An item shortcut is active without opening its menu. The top-level `SubMenu` shortcut opens that pull-down. A separator has no command and is created with `MenuItem::separator()`.

## Add a nested menu

Pass a `SubMenu` to another submenu’s `items()` method to create a pull-right menu.

```php
new SubMenu('~V~iew', Key::AltV)->items(
    new SubMenu('~T~heme')->items(
        new MenuItem('~D~ark', AppCommand::ThemeDark),
        new MenuItem('~C~lassic', AppCommand::ThemeClassic),
    ),
    new MenuItem('~R~efresh', AppCommand::Refresh, Key::F5),
);
```

The menu bar handles keyboard navigation, mnemonics, Escape, arrows, Enter, and mouse selection. It queues the selected command; handle that command in the application or owning view.

<DocCapture
  src="/captures/how-to/ui-menus.png"
  alt="Terminal application with the View pull-down open and its Theme pull-right menu visible"
  caption="A top-level mnemonic opens the pull-down; selecting Theme and moving right reveals its nested menu."
/>

## Add a status line

Each `StatusItem` provides visible text, a key, and the command to dispatch. The key is converted into a command before normal view-tree routing.

```php
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;

protected function initStatusLine(Rect $bounds): ?StatusLine
{
    return new StatusLine($bounds, StatusDef::all(
        new StatusItem('~F1~ Help', Key::F1, Cmd::Help),
        new StatusItem('~F3~ Open', Key::F3, Cmd::Open),
        new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
    ));
}
```

Status items are clickable as well as key bindings. Their text may be empty when the key binding should work without consuming visual space.

## Change hints with focus

Use separate `StatusDef` ranges for view help contexts. Set `helpCtx` on the focusable view; the program updates the status line when the active context changes.

```php
private const int HelpEditor = 101;

protected function initStatusLine(Rect $bounds): ?StatusLine
{
    return new StatusLine(
        $bounds,
        new StatusDef(self::HelpEditor, self::HelpEditor)->items(
            new StatusItem('~F3~ Save', Key::F3, Cmd::Save),
            new StatusItem('~Esc~ Close', Key::Esc, Cmd::Close),
        ),
        StatusDef::all(
            new StatusItem('~F1~ Help', Key::F1, Cmd::Help),
        ),
    );
}

// After constructing the editor view:
$editor->helpCtx = self::HelpEditor;
```

Place narrower ranges before broad fallbacks such as `StatusDef::all()`: the first matching definition is selected.

## Reflect command availability

Menu and status entries consult the application command set. Disabling a command removes it from the active status-line layout and prevents its menu dispatch.

```php
$this->disableCommand(Cmd::Save);
// ... document becomes dirty ...
$this->enableCommand(Cmd::Save);
```

Use the same command identifier in every entry point. The controls redraw automatically after the change.

## Run a context menu

For a menu that is not part of the application bar, create a `MenuPopup` and execute it in a `Group`. It returns the selected command or `Cmd::Cancel` after Escape or an outside click.

```php
use HelgeSverre\TurboVision\Menus\Menu;
use HelgeSverre\TurboVision\Menus\MenuPopup;

$menu = new Menu([
    new MenuItem('~R~efresh', AppCommand::Refresh),
    new MenuItem('~D~elete', AppCommand::Delete),
]);
$popup = new MenuPopup(Rect::of(4, 3, 20, 7), $menu);
$command = $this->executeDialog($popup);
```

Use bounds that fit the host’s current extent; `MenuBox::boundsFor()` is available when positioning a popup from a pointer location.

## Remove inherited chrome

Return `null` from `initMenuBar()` or `initStatusLine()` when that row is not wanted. The desktop then expands into the released row. Leave `initDeskTop()` in place unless the root layout is intentionally custom.

## Verify the chrome

1. Open each top-level menu with its Alt shortcut and activate a mnemonic item.
2. Trigger every item shortcut with menus closed.
3. Tab or click into a view with a dedicated `helpCtx` and confirm its status definition appears.
4. Disable one command and confirm it cannot be selected from either menu or status line.
