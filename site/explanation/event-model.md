# The event and command model

TurboVision turns terminal input into mutable `Event` objects and routes them through the retained view tree. Views may handle a physical event directly, turn it into an application command, or observe a broadcast from another control.

```text
terminal input
    ↓
key, mouse, command, or broadcast Event
    ↓
Program preprocessing and built-in commands
    ↓
view-tree routing
    ↓
custom view or application handler
```

The same command can come from a menu item, a status-line shortcut, a button, or a custom keyboard handler. Handle the command once at the application boundary instead of duplicating the action for each input source.

## Events have a type and a payload

`Event` is a mutable tagged value. Its `what` property holds an `EventType`; its payload is one of the values below.

| Event type | Payload | Typical source |
| --- | --- | --- |
| `KeyDown` | `KeyDownEvent` | terminal keyboard input |
| `MouseDown`, `MouseUp`, `MouseMove`, `MouseAuto` | `MouseEvent` | pointer and wheel input |
| `Command` | `MessageEvent` | menu, shortcut, button, or queued work |
| `Broadcast` | `MessageEvent` | a control notifying interested views |

Use the narrowing helpers rather than assuming a payload shape:

```php
if ($event->what === EventType::KeyDown) {
    $key = $event->asKey();
}

if ($event->isCommand(self::Refresh)) {
    $message = $event->asMessage();
}
```

`asKey()`, `asMouse()`, and `asMessage()` return `null` for the wrong event kind. `Event::isKey()` and `Event::isCommand()` are compact checks for the common cases.

## Consumption is explicit

A handler owns an event by calling `clear()` (or the view helper `clearEvent()`). Clearing changes `what` to `Nothing` and drops the payload. Groups stop routing a cleared event.

```php
public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if ($event->isCommand(self::Refresh)) {
        $this->reload();
        $this->clearEvent($event);
    }
}
```

Calling `parent::handleEvent()` first lets a base class or contained controls process their built-in behavior. After that call, always inspect the event's current state; it may already be `Nothing` or may have been transformed into a command.

Do not clear an event merely because a view noticed it. Clear it only when that view has completed the action and later handlers must not see it. An unconsumed event continues through the active routing phase.

## Three routes through a group

A `Group` selects a route from the event type. Its children receive only event classes included in their `eventMask`.

### Pointer events use hit testing

Mouse events are routed to the topmost visible, enabled child whose bounds contain the pointer. Groups walk children from the newest insertion toward the oldest, so the child drawn on top receives the event.

```text
topmost child under pointer → its nested group/control → handler
```

On a mouse-down, a selectable target becomes the group's current view. A target with `State::TopSelect` is also brought to the front. If it lacks `State::FirstClick`, that first selecting click is consumed; with `FirstClick`, it is delivered to the target after selection.

While a view has `State::Dragging`, it has mouse capture. Subsequent mouse move and up events go to that view even after the pointer leaves its bounds. This is the mechanism to use for title-bar drags, resize handles, sliders, and selection drags.

Mouse coordinates are root-relative. Convert them in the receiving view before comparing them to local geometry:

```php
if ($event->what === EventType::MouseDown) {
    $mouse = $event->asMouse();
    if ($mouse !== null) {
        $point = $this->makeLocal($mouse->where);
        if ($point->x === 1 && $point->y === 0) {
            $this->toggle();
            $this->clearEvent($event);
        }
    }
}
```

### Keys and commands follow focus

Keyboard and command events travel down the focused branch. At every group the order is:

```text
eligible PreProcess children → current child → eligible PostProcess children
```

An ordinary sibling does not receive another control's keystrokes. `State::PreProcess` and `State::PostProcess` are opt-in options for views such as buttons that need to observe a key or command around the current child. The event stops at the first handler that clears it.

This means a leaf text control can receive typing while an enclosing window, desktop, and program each still have a chance to handle commands appropriate to their level. A group nested on the focused branch repeats the same three phases for its own children.

### Broadcasts fan out to listeners

Broadcasts are for notifications rather than one-off actions. A group offers the event to its children in insertion order, and nested groups continue the fan-out. A listener must opt into `EventMask::Broadcast`:

```php
use HelgeSverre\TurboVision\Events\EventMask;

$this->eventMask |= EventMask::Broadcast;
```

A broadcast is still mutable: if a listener clears it, later listeners do not receive it. Leave a notification uncleared when several controls should react.

Built-in examples include `Cmd::ReceivedFocus`, `Cmd::ReleasedFocus`, `Cmd::CommandSetChanged`, and `Cmd::ScrollBarChanged`. A button listens for command-set changes so it can redraw as disabled or enabled; a scroller listens for a scrollbar change so it can update its scroll position.

## Commands express application actions

Commands are integer identifiers carried by `MessageEvent`. Define custom commands at `Cmd::FirstSafeUser` or above:

```php
use HelgeSverre\TurboVision\Events\Cmd;

final class ReportApp extends Application
{
    private const int Refresh = Cmd::FirstSafeUser;
    private const int Export = Cmd::FirstSafeUser + 1;
}
```

The older `Cmd::FirstUser` range contains framework and file-dialog values, so it is not a safe allocation point for new applications.

Menus, status-line items, and buttons enqueue commands rather than invoking application methods directly. `Program` processes queued events before polling the terminal again, which keeps nested controls independent of the application class that owns the action.

At the application boundary, route the built-in behavior first and then handle your command if it remains:

```php
public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if (! $event->isCommand(self::Refresh)) {
        return;
    }

    $this->reloadReport();
    $this->clearEvent($event);
}
```

To schedule a command from an application or group, use `putEvent()`:

```php
$this->putEvent(Event::command(self::Refresh));
```

A leaf `View` does not own the event queue. A custom leaf can ask its owning group to queue work:

```php
if ($this->owner instanceof Group) {
    $this->owner->putEvent(Event::command(self::Refresh, $this));
}
```

The optional `info` value carries the source or relevant data without adding global state. For example, a custom list can queue `Event::command(self::OpenItem, $selectedItem)`.

## Input preprocessing happens before normal routing

`Program` gets queued events first, then decoded terminal input. Before it sends a keyboard event through the view tree, it gives the status line a chance to rewrite matching shortcuts as commands and gives the menu bar a chance to consume menu hotkeys.

The program then handles universal lifecycle behavior such as Ctrl-C and built-in `Cmd::Quit`/`Cmd::Help`. Remaining events enter normal group routing. This order is why an application command handler normally calls `parent::handleEvent()` before inspecting a command: it preserves framework shortcuts, menu behavior, and the focused control's built-in handling.

## Command availability is shared UI state

The program owns the enabled-command set. A view can ask `commandEnabled($command)` through its owner chain; menu and status-line items use the same state when presenting and activating actions.

```php
$this->disableCommand(self::Export);
// ...after the report has loaded...
$this->enableCommand(self::Export);
```

Changing availability broadcasts `Cmd::CommandSetChanged`, allowing interested controls to redraw. Check availability in application code as well when an action has side effects: a command can also be posted programmatically, without passing through a menu or button.

## A reliable handler pattern

For a custom interactive view, keep the cases distinct and consume only the cases it handles:

```php
public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if ($event->isKey(Key::Enter)) {
        $this->activate();
        $this->clearEvent($event);

        return;
    }

    if ($event->what === EventType::MouseDown) {
        $mouse = $event->asMouse();
        if ($mouse !== null && $this->hitAction($this->makeLocal($mouse->where))) {
            $this->activate();
            $this->clearEvent($event);
        }
    }
}
```

Give the view the masks and options it needs. A plain `View` already accepts mouse-down, keyboard, and command events; add `EventMask::Broadcast` only when it listens for broadcasts, and add `State::Selectable` when it must become the focused target.

When an event seems to disappear, follow the route rather than adding another global handler: check its type, the receiving view's `eventMask`, the current focus branch or hit-tested bounds, and whether an earlier handler cleared or rewrote it.
