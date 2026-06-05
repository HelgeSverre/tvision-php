<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Application;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Group;

/**
 * The application root (faithful to TProgram). A Group that owns the Screen, Desktop,
 * MenuBar and StatusLine, runs the event loop, and dispatches commands. Mutable.
 */
class Program extends Group
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
     * The application root palette (faithful to TProgram::getPalette / cpAppColor).
     * Every view's remap palette resolves into this table, where logical indices
     * finally become real attribute bytes. Without it the chain dead-ends at 0x07.
     */
    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(Palettes::COLOR);
    }

    public function putEvent(Event $event): void
    {
        $this->pending[] = $event;
    }

    public function pumpEvent(): ?Event
    {
        $event = $this->getEvent();

        return $event->isNothing() ? null : $event;
    }

    // --- lifecycle ---

    /** Boot the screen and build the child views without entering the loop (for tests). */
    public function bootForTest(): void
    {
        $this->screenObj->init();
        $this->layout();
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

    public function run(): int
    {
        $this->screenObj->init();
        try {
            $this->layout();
            $this->redraw();

            while ($this->endState === 0) {
                if ($this->screenObj->wasResized()) {
                    $this->layout();
                    $this->dirty = true;
                }

                $event = $this->getEvent();
                if (! $event->isNothing()) {
                    $this->handleEvent($event);
                    $this->dirty = true;
                }

                if ($this->dirty) {
                    $this->redraw();
                }
            }
        } finally {
            $this->screenObj->shutdown();
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
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $this->setBounds(Rect::of(0, 0, $cols, $rows));

        // Reset children, then rebuild in Z-order: desktop, menu bar, status line.
        $this->children = [];
        $this->currentView = null;

        $deskRect = Rect::of(0, 1, $cols, $rows - 1);
        $this->desktop = $this->initDeskTop($deskRect);
        if ($this->desktop !== null) {
            $this->insert($this->desktop);
        }

        $menuRect = Rect::of(0, 0, $cols, 1);
        $this->menuBar = $this->initMenuBar($menuRect);
        if ($this->menuBar !== null) {
            $this->insert($this->menuBar);
        }

        $statusRect = Rect::of(0, $rows - 1, $cols, $rows);
        $this->statusLine = $this->initStatusLine($statusRect);
        if ($this->statusLine !== null) {
            $this->insert($this->statusLine);
        }
    }

    protected function redraw(): void
    {
        $this->screenObj->clear();
        $this->draw();
        $this->screenObj->flush();
        $this->dirty = false;
    }

    // --- event sourcing ---

    /**
     * The next event: a queued event first, else the next decoded screen event. A
     * keyboard event is preprocessed by the status line (key->command rewrite) and the
     * menu bar (hotkey consume) before being returned.
     */
    public function getEvent(): Event
    {
        if ($this->pending !== []) {
            return array_shift($this->pending);
        }

        $events = $this->screenObj->pollEvents(20);
        if ($events === []) {
            return Event::nothing();
        }

        $event = $events[0];
        // Re-queue any extra events decoded in the same poll.
        for ($i = 1, $n = count($events); $i < $n; $i++) {
            $this->pending[] = $events[$i];
        }

        $this->preprocess($event);

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

        // Command dispatch handled at the program level.
        if ($event->what === EventType::Command) {
            $message = $event->asMessage();
            if ($message !== null) {
                if ($message->command === Cmd::Quit) {
                    $this->endModal(Cmd::Quit);
                    $this->clearEvent($event);

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
        unset($this->disabledCommands[$command]);
    }

    public function disableCommand(int $command): void
    {
        $this->disabledCommands[$command] = true;
    }

    public function commandEnabled(int $command): bool
    {
        return ! isset($this->disabledCommands[$command]);
    }
}
