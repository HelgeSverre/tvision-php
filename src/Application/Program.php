<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Application;

use HelgeSverre\TurboVision\Commands\CommandSet;
use HelgeSverre\TurboVision\Commands\CommandTarget;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Exceptions\InputClosedException;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

/**
 * The application root (faithful to TProgram). A Group that owns the Screen, Desktop,
 * MenuBar and StatusLine, runs the event loop, and dispatches commands. Mutable.
 */
class Program extends Group implements CommandTarget
{
    protected Screen $screenObj;

    protected ?Desktop $desktop = null;

    protected ?MenuBar $menuBar = null;

    protected ?StatusLine $statusLine = null;

    /** @var list<Event> FIFO of putEvent()-queued events. */
    protected array $pending = [];

    /** @var array<int,bool> command code => enabled (absent => enabled). */
    protected array $disabledCommands = [];

    protected bool $dirty = true;

    private PaletteMode $paletteMode = PaletteMode::Color;

    /**
     * An application-supplied root palette. When present it takes precedence over
     * PaletteMode until cleared, which lets a ColorDialog commit its exact working
     * palette without losing the user's preferred built-in fallback mode.
     */
    private ?Palette $customPalette = null;

    private int $lastHelpContext = -1;

    private bool $screenActive = false;

    public function __construct()
    {
        $this->screenObj = $this->initScreen();
        parent::__construct(Rect::of(0, 0, 0, 0));
    }

    // --- factory hooks (overridden by Application / user) ---

    protected function initScreen(): Screen
    {
        // Application overrides this to provide a real or injected Screen.
        throw new \LogicException('Program::initScreen() must be overridden.');
    }

    protected function initDeskTop(Rect $bounds): ?Desktop
    {
        return new Desktop($bounds);
    }

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }

    // --- root overrides so views reach the Screen and queue events ---

    public function screen(): ?Screen
    {
        return $this->screenObj;
    }

    /**
     * The application root palette. Every view's remap palette resolves into this
     * table, where logical indices finally become real attribute bytes. An explicit
     * setPalette() override wins; otherwise the selected PaletteMode supplies the
     * built-in table (modern dark by default).
     */
    public function getPalette(): ?Palette
    {
        return $this->customPalette ?? Palette::fromBytes(Palettes::for($this->paletteMode));
    }

    public function paletteMode(): PaletteMode
    {
        return $this->paletteMode;
    }

    public function setPaletteMode(PaletteMode $mode): void
    {
        if ($this->paletteMode === $mode) {
            return;
        }

        $this->paletteMode = $mode;
        $this->dirty = true;
    }

    /**
     * Return the explicit root-palette override, if one is active.
     *
     * Built-in PaletteMode remains selected in the background and becomes active
     * again as soon as setPalette(null) clears this override.
     */
    public function customPalette(): ?Palette
    {
        return $this->customPalette;
    }

    /**
     * Set or clear an explicit root-palette override and schedule a redraw.
     *
     * Passing null restores the selected PaletteMode. Palette values are immutable,
     * so retaining the supplied instance is safe and lets callers inspect identity
     * after a ColorDialog commits it.
     */
    public function setPalette(?Palette $palette): void
    {
        if ($this->customPalette === $palette) {
            return;
        }

        $this->customPalette = $palette;
        $this->dirty = true;
    }

    public function putEvent(Event $event): void
    {
        $this->pending[] = $event;
    }

    /**
     * Dequeue one event for a modal loop, reflowing/idling/presenting as needed.
     * Does not dispatch: the caller dispatches the returned event (null means
     * "nothing to do this tick").
     */
    public function pumpEvent(): ?Event
    {
        $event = $this->getEvent();

        $resized = $this->reflowIfResized();

        if ($event->isNothing()) {
            $this->idle();
        }
        if ($resized) {
            $this->present();
        }

        return $event->isNothing() ? null : $event;
    }

    /**
     * Keep application-level lifecycle commands alive inside blocking modal loops.
     * Handles Ctrl-C and Quit/Help; returns Cmd::Quit when the enclosing modal
     * loop should end, null to continue. The handled event is cleared.
     */
    public function handleModalEvent(Event $event): ?int
    {
        $isCtrlC = $event->what === EventType::KeyDown
            && $event->asKey()?->keyCode === Key::CtrlC->value;
        $command = $event->what === EventType::Command
            ? $event->asMessage()?->command
            : null;
        if (! $isCtrlC && ! in_array($command, [Cmd::Quit, Cmd::Help], true)) {
            return null;
        }

        $this->handleEvent($event);

        return $this->ended() ? Cmd::Quit : null;
    }

    /**
     * Run a modal view over the desktop and return its end-state command. Kept
     * View-typed so controls and future dialog subclasses do not need to inherit a
     * framework-specific dialog base. A closed input stream ends the modal with
     * Cmd::Quit, matching run()'s lifecycle policy.
     */
    public function executeDialog(View $view): int
    {
        return ($this->desktop ?? $this)->execView($view);
    }

    /** Hook for applications with a HelpFile; return null when no help is available. */
    protected function createHelpView(int $context): ?View
    {
        return null;
    }

    /** Whether the current desktop focus is allowed to move away. */
    public function canMoveFocus(): bool
    {
        return $this->desktop?->valid(Cmd::ReleasedFocus) ?? true;
    }

    /** Validate a newly-created view before the application takes ownership. */
    public function validView(?View $view): ?View
    {
        return $view !== null && $view->valid(Cmd::Valid) ? $view : null;
    }

    /** Validate, insert, and select a window; invalid windows are left unowned. */
    public function insertWindow(Window $window): ?Window
    {
        if ($this->desktop === null || ! $this->canMoveFocus() || $this->validView($window) === null) {
            return null;
        }

        $this->desktop->insertWindow($window);
        $this->dirty = true;

        return $window;
    }

    /**
     * Temporarily restore the terminal for a child process or shell handoff.
     * Resume redraws the existing view tree rather than reconstructing it.
     */
    public function suspend(): void
    {
        if (! $this->screenActive) {
            return;
        }

        $this->screenObj->shutdown();
        $this->screenActive = false;
    }

    public function resume(): void
    {
        if ($this->screenActive) {
            return;
        }

        $this->screenObj->init();
        $this->screenActive = true;
        $this->reflowDesktop();
        $this->dirty = true;
    }

    // --- lifecycle ---

    /** Test accessor: the live desktop (post-layout). */
    public function desktopForTest(): ?Desktop
    {
        return $this->desktop;
    }

    /**
     * Reflow on terminal resize: resize buffers (already done by Screen during poll),
     * recompute root bounds, and changeBounds the desktop so its windows reflow by
     * growMode (rather than discarding them as the bare layout() rebuild does).
     */
    public function reflowDesktop(): void
    {
        $this->setBounds(Rect::of(0, 0, $this->screenObj->cols(), $this->screenObj->rows()));

        ['desk' => $desk, 'menu' => $menu, 'status' => $status] = $this->bands();

        $this->desktop?->changeBounds($desk);
        $this->menuBar?->changeBounds($menu);
        $this->statusLine?->changeBounds($status);
    }

    /** Test helper: trigger one resize cycle as the run() loop would. */
    public function pumpResizeForTest(): void
    {
        // Force the Screen to observe the driver's new size.
        $this->screenObj->pollEvents(0);
        $this->reflowIfResized();
    }

    /** Boot the screen and build the child views without entering the loop (for tests). */
    public function bootForTest(): void
    {
        try {
            $this->screenObj->init();
            $this->screenActive = true;
            $this->layout();
        } catch (\Throwable $exception) {
            $this->screenObj->shutdown();
            $this->screenActive = false;

            throw $exception;
        }
    }

    /** Test helper: draw the tree and flush once (mirrors run()'s redraw). */
    public function drawAndFlushForTest(): void
    {
        $this->redraw();
    }

    /**
     * Test helper: the current back-buffer rows.
     *
     * @return list<string>
     */
    public function backRowsForTest(): array
    {
        return $this->screenObj->back()->rows();
    }

    /**
     * Run the event loop until Quit. Always returns 0; the termination reason is
     * available afterwards via ended() / endModalState(). A closed input stream is
     * a normal lifecycle end; other driver failures propagate after terminal restore.
     */
    public function run(): int
    {
        try {
            $this->endState = 0;
            $this->screenObj->init();
            $this->screenActive = true;
            $this->layout();
            $this->idle();
            $this->redraw();

            try {
                while ($this->endState === 0) {
                    $event = $this->getEvent();

                    // pollEvents() is where Screen observes SIGWINCH. Reflow before
                    // dispatching the event returned by that same poll so mouse hit
                    // testing and view geometry never use the previous terminal size.
                    $this->reflowIfResized();

                    if (! $event->isNothing()) {
                        $this->handleEvent($event);
                        $this->dirty = true;
                    } else {
                        $this->idle();
                    }

                    if ($this->dirty) {
                        $this->redraw();
                    }
                }
            } catch (InputClosedException) {
                // A closed PTY/stdin is a normal lifecycle end, not a framework
                // failure. Other driver errors remain visible to the caller.
                $this->endState = Cmd::Quit;
            }
        } finally {
            $this->screenObj->shutdown();
            $this->screenActive = false;
        }

        return 0;
    }

    public function ended(): bool
    {
        return $this->endState !== 0;
    }

    /** (Re)build the bounds + child views from the current screen size. */
    protected function layout(): void
    {
        $this->setBounds(Rect::of(0, 0, $this->screenObj->cols(), $this->screenObj->rows()));

        // Reset children, then rebuild in Z-order: desktop, menu bar, status line.
        $this->clearSubviews();

        ['desk' => $deskRect, 'menu' => $menuRect, 'status' => $statusRect] = $this->bands();

        $this->desktop = $this->initDeskTop($deskRect);
        if ($this->desktop !== null) {
            $this->insert($this->desktop);
            $this->setCurrent($this->desktop);
        }

        $this->menuBar = $this->initMenuBar($menuRect);
        if ($this->menuBar !== null) {
            $this->insert($this->menuBar);
        }

        $this->statusLine = $this->initStatusLine($statusRect);
        if ($this->statusLine !== null) {
            $this->insert($this->statusLine);
        }
        $this->lastHelpContext = -1;
    }

    /**
     * The three horizontal application bands for the current screen size: desktop,
     * menu bar, and status line rectangles (in that top-to-bottom order). Shared by
     * layout() and reflowDesktop() so boot and resize never drift apart.
     *
     * @return array{desk: Rect, menu: Rect, status: Rect}
     */
    private function bands(): array
    {
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $menuBottom = min(1, $rows);
        $statusTop = max($menuBottom, $rows - 1);

        return [
            'desk' => Rect::of(0, $menuBottom, $cols, $statusTop),
            'menu' => Rect::of(0, 0, $cols, $menuBottom),
            'status' => Rect::of(0, $statusTop, $cols, $rows),
        ];
    }

    protected function redraw(): void
    {
        $this->screenObj->clear();
        $this->draw();
        $this->screenObj->setCursor($this->cursorPosition());
        $this->screenObj->flush();
        $this->dirty = false;
    }

    /** Consume Screen's resize latch exactly once and reflow the live tree. */
    private function reflowIfResized(): bool
    {
        if (! $this->screenObj->wasResized()) {
            return false;
        }

        $this->reflowDesktop();
        $this->dirty = true;

        return true;
    }

    /**
     * Idle ticks refresh status context. Applications may override this for timers
     * or background work; call parent::idle() to retain context-sensitive status.
     */
    public function idle(): void
    {
        $helpContext = $this->getHelpCtx();
        if ($helpContext === $this->lastHelpContext) {
            return;
        }

        $this->lastHelpContext = $helpContext;
        if ($this->statusLine !== null) {
            $this->statusLine->setHelpContext($helpContext);
        }
        $this->dirty = true;
    }

    // --- event sourcing ---

    /**
     * The next event: a queued event first, else the next decoded screen event. A
     * keyboard event is preprocessed by the status line (key->command rewrite) and the
     * menu bar (hotkey consume) before being returned — including events that were
     * decoded in an earlier batch and held in the pending queue.
     */
    public function getEvent(): Event
    {
        if ($this->pending === []) {
            foreach ($this->screenObj->pollEvents(20) as $polled) {
                $this->pending[] = $polled;
            }
        }

        $event = array_shift($this->pending) ?? Event::nothing();
        if (! $event->isNothing()) {
            $this->preprocess($event);
        }

        return $event;
    }

    /** Let the status line and menu bar transform/consume the event first. */
    protected function preprocess(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $this->statusLine?->handleEvent($event); // may rewrite into a Command
        }
        if ($event->what === EventType::KeyDown) {
            $this->menuBar?->handleEvent($event);    // may consume a hotkey
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isNothing()) {
            return;
        }

        // Ctrl-C is a universal quit escape hatch. In raw mode the terminal does not
        // raise SIGINT, so Ctrl-C arrives as the keystroke Key::CtrlC; treat it as quit so
        // the app is always escapable (the terminal is restored on shutdown).
        if ($event->what === EventType::KeyDown && $event->asKey()?->keyCode === Key::CtrlC->value) {
            $this->endModal(Cmd::Quit);
            $this->clearEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown) {
            $windowNumber = $this->altWindowNumber($event);
            if ($windowNumber !== null) {
                if ($this->canMoveFocus() && $this->selectWindowNumber($windowNumber)) {
                    $this->clearEvent($event);
                }

                return;
            }
        }

        // Command dispatch handled at the program level.
        if ($event->what === EventType::Command) {
            $message = $event->asMessage();
            if ($message !== null) {
                if ($message->command === Cmd::Quit) {
                    $this->endModal(Cmd::Quit);
                    $this->clearEvent($event);

                    return;
                }
                if ($message->command === Cmd::Help) {
                    $help = $this->createHelpView($this->getHelpCtx());
                    if ($help !== null) {
                        $this->executeDialog($help);
                        $this->clearEvent($event);
                    }

                    return;
                }
            }
        }

        // Route the rest down the view tree (desktop, menu bar, status line).
        parent::handleEvent($event);
    }

    // --- command set ---

    public function enableCommand(int $command): void
    {
        if (! isset($this->disabledCommands[$command])) {
            return;
        }

        unset($this->disabledCommands[$command]);
        $this->broadcastCommandSetChanged();
        $this->dirty = true;
    }

    public function disableCommand(int $command): void
    {
        if (isset($this->disabledCommands[$command])) {
            return;
        }

        $this->disabledCommands[$command] = true;
        $this->broadcastCommandSetChanged();
        $this->dirty = true;
    }

    public function commandEnabled(int $command): bool
    {
        return ! isset($this->disabledCommands[$command]);
    }

    public function enableCommands(CommandSet $commands): void
    {
        $commands->enableOn($this);
    }

    public function disableCommands(CommandSet $commands): void
    {
        $commands->disableOn($this);
    }

    private function broadcastCommandSetChanged(): void
    {
        parent::handleEvent(Event::broadcast(Cmd::CommandSetChanged));
    }

    private function altWindowNumber(Event $event): ?int
    {
        return match ($event->asKey()?->keyCode) {
            Key::Alt1->value => 1,
            Key::Alt2->value => 2,
            Key::Alt3->value => 3,
            Key::Alt4->value => 4,
            Key::Alt5->value => 5,
            Key::Alt6->value => 6,
            Key::Alt7->value => 7,
            Key::Alt8->value => 8,
            Key::Alt9->value => 9,
            default => null,
        };
    }

    private function selectWindowNumber(int $number): bool
    {
        if ($this->desktop === null) {
            return false;
        }

        $selection = Event::broadcast(Cmd::SelectWindowNum, $number);
        $this->desktop->handleEvent($selection);
        if ($selection->isNothing()) {
            return true;
        }

        // Existing Window subclasses need not opt into a special broadcast just
        // to retain Alt-number selection. Native windows are selected directly.
        foreach ($this->desktop->subviews() as $view) {
            if ($view instanceof Window && $view->frameNumber() === $number) {
                $this->desktop->selectWindow($view);

                return true;
            }
        }

        return false;
    }
}
