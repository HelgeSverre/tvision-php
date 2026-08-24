# Handle events and commands

Use commands for actions that can originate from several controls: a menu item, status-line hint, button, keyboard shortcut, or custom view. An `Event` carries the command through the view tree; the handler that completes it must clear the event.

## Define application commands

Commands are integer identifiers. Keep application values at or above `Cmd::FirstSafeUser`; `Cmd::FirstUser` (`100`) is retained for compatibility, but part of that range is used by framework dialogs.

```php
use HelgeSverre\TurboVision\Events\Cmd;

final class ReportCommand
{
    public const int Refresh = Cmd::FirstSafeUser;
    public const int Export = Cmd::FirstSafeUser + 1;
}
```

Keep the identifiers in one class when several views use them. Reuse the built-in `Cmd` constants for standard actions such as `Cmd::Open`, `Cmd::Save`, `Cmd::Close`, and `Cmd::Quit`.

## Handle a command in the application

Call the inherited handler first so `Cmd::Quit`, `Cmd::Help`, and child routing continue to work. Then claim only the commands owned by the application.

```php
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;

final class ReportApp extends Application
{
    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->what !== EventType::Command) {
            return;
        }

        $command = $event->asMessage()?->command;
        match ($command) {
            ReportCommand::Refresh => $this->refreshReport(),
            ReportCommand::Export => $this->exportReport(),
            default => null,
        };

        if (in_array($command, [ReportCommand::Refresh, ReportCommand::Export], true)) {
            $event->clear();
        }
    }
}
```

`$event->asMessage()` supplies both the integer command and optional `info` value. Inspect the payload before clearing it when a sender includes context:

```php
if ($event->isCommand(ReportCommand::Export)) {
    $format = $event->asMessage()?->info; // for example, 'csv'
    $this->exportReport(is_string($format) ? $format : 'txt');
    $event->clear();
}
```

Calling `clear()` changes the event to `Nothing`, preventing later handlers from acting on it. Do not clear an unknown command: a focused child or post-processing view may own it.

## Queue a command from a view

Use `putEvent()` to let the normal event loop deliver an action. A child view can queue through its owner; an application queues directly on the program.

```php
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Views\Group;

if ($this->owner instanceof Group) {
    $this->owner->putEvent(Event::command(ReportCommand::Refresh));
}
```

Pass a sender or small value as the second argument when the receiver needs it:

```php
if ($this->owner instanceof Group) {
    $this->owner->putEvent(Event::command(ReportCommand::Export, 'csv'));
}
```

Use a command for a single action. Use `Event::broadcast()` for a notification that multiple interested views may observe:

```php
$this->owner?->handleEvent(Event::broadcast(ReportCommand::Refresh, $this));
```

Broadcasts are delivered to eligible descendants. They are still mutable, so a receiver that clears a broadcast stops the remaining delivery.

## Turn a key into a command in a custom view

`View` accepts keyboard and command events by default. Add mouse support only if the view handles positional mouse events. This example turns Enter into the same refresh command used by a menu item.

```php
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

final class RefreshView extends View
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->options |= State::Selectable;
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->isKey(Key::Enter)) {
            if ($this->owner instanceof Group) {
                $this->owner->putEvent(Event::command(ReportCommand::Refresh, $this));
            }
            $event->clear();
        }
    }
}
```

`State::Selectable` allows the group to focus this view, which is required for its keyboard events to arrive. For character and modifier details, use `$event->asKey()`. For pointer position and button data, use `$event->asMouse()`. Each returns `null` for a different event payload.

## Enable and disable actions

The program command set controls menu items, status-line hints, and buttons that use the same command. Change it whenever the action becomes unavailable:

```php
use HelgeSverre\TurboVision\Commands\CommandSet;

$this->disableCommand(ReportCommand::Export);
$this->enableCommand(ReportCommand::Export);

$this->disableCommands(CommandSet::of(
    ReportCommand::Refresh,
    ReportCommand::Export,
));
```

Changing the set broadcasts `Cmd::CommandSetChanged`, so menu/status controls redraw with their disabled state. Guard the operation in the application handler as well when it has side effects:

```php
if ($event->isCommand(ReportCommand::Export) && $this->commandEnabled(ReportCommand::Export)) {
    $this->exportReport();
    $event->clear();
}
```

<DocCapture
  src="/captures/how-to/ui-events.png"
  alt="Report application with the Report menu open; Refresh is available while Export is disabled, and the disabled Export status hint is absent"
  caption="One command state governs every command-aware control. Here Export is unavailable in both the pull-down and status line."
/>

## Verify the flow

1. Trigger the action from each advertised entry point: shortcut, menu, status line, button, or custom view.
2. Confirm the handler runs once and the event is cleared.
3. Disable the command and confirm it no longer dispatches from menu/status/button controls.
4. Re-enable it and confirm the same entry points work again.
