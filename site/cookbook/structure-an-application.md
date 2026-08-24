# Structure a larger application

Single-file examples are excellent for learning, but a growing application becomes easier to change when the application root coordinates features instead of constructing every control itself.

This is a practical starting layout, not a framework requirement:

```text
app/
├── ConsoleApp.php
├── Command.php
├── State/
│   └── Workspace.php
├── Window/
│   ├── DashboardWindow.php
│   └── ActivityWindow.php
├── Dialog/
│   └── PreferencesDialog.php
└── View/
    └── DashboardView.php
bin/
└── console-app
tests/
├── ConsoleAppTest.php
└── PreferencesDialogTest.php
```

Add the application's namespace to Composer and run `composer dump-autoload`:

```json
{
  "autoload": {
    "psr-4": {
      "Acme\\Console\\": "app/"
    }
  }
}
```

## Keep the root responsible for coordination

Your `Application` subclass should usually own five jobs:

- build the menu bar and status line;
- translate application commands into actions;
- create or request top-level windows and dialogs;
- coordinate shared application state;
- provide application-wide help, palette, and shutdown behavior.

Keep drawing and control-specific input in child views. Keep filesystem, network, and domain work in ordinary PHP services that do not extend `View`.

```php
namespace Acme\Console;

final class ConsoleApp extends Application
{
    public function __construct(
        private readonly Workspace $workspace,
        ?Screen $screen = null,
    ) {
        parent::__construct($screen);
    }

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = new Desktop($bounds);
        $desktop->insertWindow(new DashboardWindow($this->workspace));

        return $desktop;
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->isCommand(Command::Preferences)) {
            $this->editPreferences();
            $this->clearEvent($event);
        }
    }
}
```

Calling `parent::handleEvent()` first preserves built-in close, zoom, help, quit, and window commands. Clear an application command once it has been consumed so it does not continue through the tree.

## Give commands one home

Menus, status items, buttons, and views communicate through integer commands. Avoid unrelated numeric literals spread across classes:

```php
namespace Acme\Console;

use HelgeSverre\TurboVision\Events\Cmd;

final class Command
{
    public const int Preferences = Cmd::FirstSafeUser + 1;
    public const int Refresh = Cmd::FirstSafeUser + 2;
    public const int OpenActivity = Cmd::FirstSafeUser + 3;

    private function __construct() {}
}
```

Use built-in `Cmd` values for framework behavior and application values for domain actions. The same `Command::Refresh` can be emitted by a menu, status shortcut, dialog button, or custom view.

## Let windows compose their interiors

A top-level window should establish its own controls and layout rules. This keeps the root independent of window-local coordinates:

```php
final class DashboardWindow extends Window
{
    public function __construct(Workspace $workspace)
    {
        parent::__construct(Rect::of(4, 2, 66, 20), 'Dashboard', 1);

        $content = new DashboardView(
            $this->getExtent()->grow(-1, -1),
            $workspace,
        );
        $content->growMode = State::GrowHiX | State::GrowHiY;
        $this->insert($content);
    }
}
```

Use a factory method when creation needs application services, numbering, dynamic bounds, or tests. Use `Program::insertWindow()` for the final insertion so focus validation remains centralized.

## Make dialogs translate data

Build a dialog in its own class or factory, but do not let it mutate application state as controls change. Preload a value object or transfer array, execute the dialog, and commit only an accepted result:

```php
private function editPreferences(): void
{
    $dialog = PreferencesDialog::build();
    $dialog->setData($this->workspace->preferences()->toTransferData());

    if ($this->executeDialog($dialog) !== Cmd::Ok) {
        return;
    }

    $this->workspace->replacePreferences(
        Preferences::fromTransferData($dialog->getData()),
    );
}
```

This makes Cancel a real rollback boundary. It also gives validation and persistence separate places to live.

## Route events at the narrowest useful owner

Put behavior where the necessary context already exists:

| Behavior | Natural owner |
| --- | --- |
| Quit, open global window, application preferences | `Application` subclass |
| Close, zoom, save this document | `Window` subclass |
| Keyboard/mouse interaction for one surface | Custom `View` |
| Accept, cancel, validate fields | `Dialog` and its controls |
| Business rule, storage, remote call | Plain PHP service |

Views can broadcast a command instead of reaching into the application. The root or owning window then decides what that command means.

## Test through the screen boundary

Inject a headless `Screen` into the application constructor. A useful test pyramid is:

1. test state and services as ordinary PHP;
2. test a dialog's `setData()`/`getData()` and validation directly;
3. boot the application headlessly, queue commands, and inspect windows or the back buffer;
4. keep a small number of whole-frame snapshots for visual regressions.

The application APIs `bootForTest()`, `putEvent()`, `drawAndFlushForTest()`, `desktopForTest()`, and `backRowsForTest()` exist for this boundary. See [Test without a terminal](/tutorials/headless-testing) for a complete setup.

For a large real-world composition, browse the repository's [`WorkbenchApp`](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/Workbench/WorkbenchApp.php). For the incremental version, start with the [guide](/tutorials/guide/).
