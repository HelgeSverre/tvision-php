# Add windows and scrolling

Steps 04–10 turn a command into a window, replace the window interior with an application view, then make that content scroll and resize correctly.

## Open a window from a command

Let the normal event chain run first, handle the commands owned by your application, and clear a handled event:

```php
public function handleEvent(Event $event): void
{
    parent::handleEvent($event);

    if ($event->isCommand(self::NewWindow)) {
        $window = new Window(Rect::of(6, 3, 58, 18), 'Document', 1);
        $this->insertWindow($window);
        $this->clearEvent($event);
    }
}
```

`Program::insertWindow()` is the application-level path: it checks focus movement and view validity before inserting into the desktop. `Desktop::insertWindow()` is useful when you already own the desktop directly, as the compact source examples do.

[Guide04.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide04.php) also introduces a `makeWindow()` factory. Keep that seam: later examples change window composition without duplicating command handling.

## Give the window an application view

Subclass `View` when a standard control does not represent your content. Paint the complete visible extent in local coordinates:

```php
final class DocumentView extends View
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
    }

    public function draw(): void
    {
        $row = new DrawBuffer($this->bounds->width());
        $row->moveChar(0, ' ', $this->mapColor(1), $this->bounds->width());
        $row->moveStr(0, 'Hello from a custom view', $this->mapColor(1));
        $this->writeLine(0, 0, $this->bounds->width(), 1, $row);
    }
}
```

Blank each row before writing shorter text. That prevents stale cells after content or window size changes—the deliberate difference between [Guide06.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide06.php) and [Guide07.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide07.php).

Insert children using window-local bounds. `getExtent()` returns the full local rectangle; `getClipRect()->grow(-1, -1)` is a convenient interior rectangle when composing a framed window.

## Turn the view into a viewport

Extend `Scroller` when the logical content is larger than its on-screen rectangle:

```php
final class DocumentScroller extends Scroller
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, ScrollBar $h, ScrollBar $v, private array $lines)
    {
        parent::__construct($bounds, $h, $v);
        $this->setLimit(80, count($lines));
    }

    public function draw(): void
    {
        for ($screenY = 0; $screenY < $this->bounds->height(); $screenY++) {
            $text = $this->lines[$this->delta->y + $screenY] ?? '';
            $visible = mb_substr($text, $this->delta->x, $this->bounds->width());
            $row = new DrawBuffer($this->bounds->width());
            $row->moveChar(0, ' ', $this->mapColor(1), $this->bounds->width());
            $row->moveStr(0, $visible, $this->mapColor(1));
            $this->writeLine(0, $screenY, $this->bounds->width(), 1, $row);
        }
    }
}
```

`limit` is the logical content size; `delta` is the current scroll offset. Create edge bars with `Window::standardScrollBar()` and pass them to the scroller. Set `handleKeyboard: true` when the bar should process navigation keys after the focused content.

[Guide08.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide08.php) shows one viewport. [Guide09.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide09.php) composes two panes with independent bars.

## Make resizing intentional

`growMode` declares which child edges follow an owner resize. A right-hand pane commonly uses `State::GrowHiX | State::GrowHiY`; a left pane may grow vertically while retaining its width.

If shrinking would make the composition unusable, override the window's limits:

```php
public function sizeLimits(): SizeLimits
{
    $limits = parent::sizeLimits();

    return new SizeLimits(
        minWidth: max($limits->minWidth, 34),
        minHeight: max($limits->minHeight, 10),
        maxWidth: $limits->maxWidth,
        maxHeight: $limits->maxHeight,
    );
}
```

[Guide10.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide10.php) derives its minimum from the left pane instead of hard-coding it.

Next: [collect and commit data with dialogs](./dialogs-and-data).
