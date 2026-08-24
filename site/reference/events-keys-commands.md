# Events, keys, and commands

Input, framework notifications, and application actions are represented by a mutable `Event`. A handler consumes an event with `clear()`; routing stops as soon as `what` becomes `EventType::Nothing`.

```php
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;

public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if ($event->what === EventType::Command && $event->isCommand(Cmd::Save)) {
        $this->saveDocument();
        $event->clear();
    }
}
```

## `Event` and payloads

| Factory | `what` | Payload |
| --- | --- | --- |
| `Event::nothing()` | `Nothing` | none |
| `Event::keyDown(KeyDownEvent $key)` | `KeyDown` | `KeyDownEvent` |
| `Event::key(Key $key, int $modifiers = 0)` | `KeyDown` | Special-key `KeyDownEvent` |
| `Event::mouse(EventType $what, MouseEvent $mouse)` | A mouse type | `MouseEvent` |
| `Event::command(int $command, mixed $info = null)` | `Command` | `MessageEvent` |
| `Event::broadcast(int $command, mixed $info = null)` | `Broadcast` | `MessageEvent` |

`asKey(): ?KeyDownEvent`, `asMouse(): ?MouseEvent`, and `asMessage(): ?MessageEvent` safely narrow the current payload. `isKey(Key $key)` checks a `KeyDown` event's legacy key code. `isCommand(int $command)` matches either a command or broadcast. `clear()` sets `what` to `Nothing` and removes the payload.

Payloads are immutable:

| Payload | Fields |
| --- | --- |
| `KeyDownEvent` | `int $keyCode`, `string $char = ''`, `int $modifiers = 0`. `$char` is the printable grapheme or `''` for special keys. |
| `MouseEvent` | `Point $where`, `int $buttons = 0`, `bool $doubleClick = false`, `int $wheel = 0`. `where` is root-relative. |
| `MessageEvent` | `int $command`, `mixed $info = null`. Framework broadcasts commonly use the source view as `info`. |

## Event kinds and masks

`EventType` identifies one event; its numeric values are mask bits. `EventMask` groups bits for a view's public `eventMask` property.

| `EventType` | Meaning | `EventMask` membership |
| --- | --- | --- |
| `Nothing` | Consumed or empty event | none |
| `MouseDown`, `MouseUp`, `MouseMove`, `MouseAuto` | Pointer press/release/motion/repeat | `Mouse` and `Positional` |
| `KeyDown` | Key input | `Keyboard` and `Focused` |
| `Command` | Directed application or control action | `Command`, `Message`, and `Focused` |
| `Broadcast` | Notification for interested descendants | `Broadcast` and `Message` |

`EventMask::Mouse` is all four mouse types. `Positional` is an alias of `Mouse`; `Focused` is `Keyboard | Command`; `Message` includes command and broadcast bits. Set masks with bitwise OR:

```php
$view->eventMask = EventMask::Keyboard | EventMask::Command | EventMask::Broadcast;
```

A plain `View` defaults to `MouseDown | Keyboard | Command`. `Group` accepts all event bits and routes them. A view does not receive an event class unless its mask includes `EventType::$value`.

## Routing order

### Positional events

`Group` dispatches mouse events to the topmost visible, enabled child under the pointer. Child bounds are compared in the owner's local coordinates. A child that has `State::Dragging` captures later move/up events even when the pointer leaves its bounds. On a mouse-down, a selectable child becomes current; unless it has `State::FirstClick`, that focus-changing click is consumed before the child handles it.

### Keyboard and commands

Keyboard and `Command` events use the active branch:

1. Visible, enabled children with `State::PreProcess` receive the event in insertion order.
2. The current visible, enabled child receives it.
3. Visible, enabled children with `State::PostProcess` receive it in insertion order.

Any handler may clear the event. Non-current children do not receive a focused event unless they are a pre- or post-processor.

### Broadcasts

Broadcasts visit children whose masks include `EventMask::Broadcast`, in insertion order, until a handler clears the event. Framework broadcasts are used for focus transitions, controls, scroll bars, and list/outline selection.

<DocCapture
  src="/captures/reference/core-menu-event.png"
  alt="A File pull-down menu opened over a TurboVision desktop window"
  caption="After `Alt-F` reaches the menu bar preprocessor, the menu consumes the key and opens its pull-down while the desktop remains visible beneath the overlay."
/>

## Keys and modifiers

`Key` is an `int`-backed enum for non-printable and historical modified keys. Printable input is carried in `KeyDownEvent::$char`; compare it with strings rather than attempting to construct a `Key` value.

```php
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;

$key = $event->asKey();
if ($key?->is(Key::F1)) {
    // Help
}
if ($key?->char === 's' && ($key->modifiers & KeyModifier::Ctrl) !== 0) {
    // Ctrl+S where the terminal reports a printable character plus modifier
}
```

| `Key` group | Members |
| --- | --- |
| Control characters | `None`, `CtrlA` through `CtrlZ` |
| Editing and basic input | `Esc`, `Enter`, `Tab`, `ShiftTab`, `Backspace`, `CtrlBackspace`, `CtrlEnter`, `Insert`, `Delete` |
| Navigation | `Up`, `Down`, `Left`, `Right`, `Home`, `End`, `PageUp`, `PageDown`, `GrayMinus`, `GrayPlus` |
| Function keys | `F1` through `F12`, `ShiftF1` through `ShiftF10`, `CtrlF1` through `CtrlF10`, `AltF1` through `AltF10` |
| Modified navigation | `CtrlInsert`, `ShiftInsert`, `CtrlDelete`, `ShiftDelete`, `CtrlLeft`, `CtrlRight`, `CtrlHome`, `CtrlEnd`, `CtrlPageUp`, `CtrlPageDown`, `CtrlPrintScreen` |
| Menu/window hotkeys | `AltA` through `AltZ`, `Alt0` through `Alt9`, `AltSpace`, `AltBackspace`, `AltMinus`, `AltEqual` |

`KeyModifier` values are bit flags:

| Flag | Meaning |
| --- | --- |
| `None` | No modifiers. |
| `Shift`, `Alt`, `Ctrl` | Primary keyboard modifiers. |
| `Super`, `Hyper`, `Meta` | Extended terminal-reported modifiers. |
| `CapsLock`, `NumLock` | Lock-state metadata. |
| `Known` | OR of every recognized flag. |

Use `($modifiers & KeyModifier::Alt) !== 0`; flags may be combined. Modifier bits follow CSI-u/Kitty-style terminal metadata. `Key` values remain the framework's Turbo Vision-compatible key identities, so a terminal may report the same physical shortcut as a `Key::CtrlX` code, a printable character with `Ctrl`, or both depending on protocol and terminal configuration. Handle the representations accepted by the control or shortcut you implement.

## Command conventions

Commands are plain integers. Define application names as integer constants starting at `Cmd::FirstSafeUser` (`200`):

```php
final class AppCmd
{
    public const int ExportReport = Cmd::FirstSafeUser;
    public const int RefreshReport = Cmd::FirstSafeUser + 1;
}
```

`Cmd::FirstUser` is the historical `100` marker but is not safe for new names: `102` and `103` are file-dialog broadcasts. The ranges below are reserved by the framework and controls.

| Commands | Values | Use |
| --- | ---: | --- |
| Modal/window core | `Valid`, `Quit`, `Error`, `Menu`, `Close`, `Zoom`, `Resize`, `Next`, `Prev`, `Help`, `Ok`, `Cancel`, `Yes`, `No`, `Default` | `0`–`14` | Standard application, modal, and window actions. |
| Editing/layout | `Cut`, `Copy`, `Paste`, `Undo`, `Clear`, `Tile`, `Cascade`, `New`, `Open`, `Save`, `SaveAs`, `SaveAll`, `ChDir`, `DosShell`, `CloseAll` | `20`–`37` | Standard editor, file, and desktop commands. |
| Program internals | `SysRepaint`, `SysResize`, `SysWakeup` | `38`–`40` | Internal program lifecycle actions. |
| Framework broadcasts | `ReceivedFocus`, `ReleasedFocus`, `CommandSetChanged`, `ScrollBarChanged`, `ScrollBarClicked`, `SelectWindowNum`, `ListItemSelected` | `50`–`56` | Notifications; inspect `MessageEvent::$info` for the source. |
| Dialog broadcasts | `RecordHistory`, `GrabDefault`, `ReleaseDefault` | `60`–`62` | History and default-button coordination. |
| Color controls | `ColorForegroundChanged`, `ColorBackgroundChanged`, `ColorSet`, `NewColorItem`, `NewColorIndex`, `SaveColorIndex` | `71`–`76` | Color dialog and selector actions. |
| Editor search | `Find`, `Replace`, `SearchAgain` | `82`–`84` | Editor operations. |
| File-dialog notifications | `FileFocused`, `FileDoubleClicked` | `102`, `103` | Broadcasts from file controls. `FileCommand::Focused` and `::DoubleClicked` are aliases. |
| Application commands | `FirstSafeUser` and above | `200+` | User-defined commands; avoid other reserved values below. |
| Outline selection | `OutlineItemSelected` | `301` | Broadcast from `OutlineViewer`; `OutlineViewer::ItemSelected` is an alias. |
| Internal editor actions | `CharLeft` through `UpdateTitle` | `500`–`523` | Consumed by editor controls; do not assign application actions here. |
| File-dialog modal results/actions | `FileOpen`, `FileReplace`, `FileClear`, `FileInit`, `ChangeDir`, `Revert`, `DirSelection` | `1001`–`1007` | `FileCommand` contains matching aliases. |

### Command sources and recipients

* `Button` queues `Event::command($command, $button)` on its owner unless it has `ButtonFlag::Broadcast`, in which case it broadcasts the same command.
* `Dialog` translates Escape into `Cmd::Cancel` and Enter into a `Cmd::Default` broadcast. A default button consumes that broadcast and queues or broadcasts its own command.
* `ScrollBar` broadcasts `ScrollBarClicked` on interaction and `ScrollBarChanged` only when the value changes.
* `ListViewer::selectItem()` broadcasts `ListItemSelected`; `OutlineViewer::selected()` broadcasts `OutlineItemSelected`.
* `View::setState()` broadcasts `ReceivedFocus` and `ReleasedFocus` from the owner with the focus-changing view as payload.

## Modal completion and validation

`Group::execView($modal)` runs a modal view and returns the command passed to `endModal()`. `Window` and `Dialog` route standard close actions into that mechanism. `valid($command)` decides whether a non-cancel completion proceeds. `Dialog` validates all children for `Ok`, `Yes`, and `No`; `Cancel` bypasses validation. Input controls with validators focus themselves when validation fails.

## Event-handling rules

* Call `parent::handleEvent($event)` first when subclassing a control or group unless the override intentionally replaces its behavior.
* Read the typed payload only after checking the event category; `asKey()`, `asMouse()`, and `asMessage()` may return `null`.
* Clear only events that the current view has handled. A cleared event cannot reach later routing phases or ancestors.
* Use broadcasts for notifications that multiple descendants may observe. Use commands for directed application/control actions.
* Queue follow-up actions with `putEvent()` when a handler must change the event stream rather than process an action immediately.
