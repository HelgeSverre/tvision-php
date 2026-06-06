<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * A View owning an ordered Z-ordered subview list (faithful to TGroup). Routes events
 * (positional -> subview under the mouse; focused -> current then any handler;
 * broadcast -> all), manages focus, and runs the execView modal sub-loop.
 */
class Group extends View
{
    /** @var list<View> Z-order: later entries draw on top. */
    protected array $children = [];

    protected ?View $currentView = null;

    /** Non-zero ends the active modal execute() loop with this command code. */
    protected int $endState = 0;

    public function insert(View $view): void
    {
        $view->setOwner($this);
        $this->children[] = $view;
        if ($this->currentView === null && $view->getState(State::Selectable)) {
            $this->currentView = $view;
        }
    }

    public function remove(View $view): void
    {
        $this->children = array_values(array_filter(
            $this->children,
            static fn (View $v): bool => $v !== $view,
        ));
        if ($this->currentView === $view) {
            $this->currentView = null;
        }
        $view->setOwner(null);
    }

    /** @return list<View> */
    public function subviews(): array
    {
        return $this->children;
    }

    public function current(): ?View
    {
        return $this->currentView;
    }

    public function setCurrent(?View $view): void
    {
        if ($this->currentView !== null) {
            $this->currentView->setState(State::Focused, false);
        }
        $this->currentView = $view;
        if ($view !== null) {
            $view->setState(State::Focused, true);
        }
    }

    /** Advance focus to the next selectable subview, wrapping. */
    public function selectNext(): void
    {
        $selectable = array_values(array_filter(
            $this->children,
            static fn (View $v): bool => $v->getState(State::Selectable),
        ));
        if ($selectable === []) {
            return;
        }

        $idx = 0;
        foreach ($selectable as $i => $v) {
            if ($v === $this->currentView) {
                $idx = $i;
                break;
            }
        }
        $next = $selectable[($idx + 1) % count($selectable)];
        $this->setCurrent($next);
    }

    public function focusNext(): void
    {
        $this->selectNext();
    }

    public function draw(): void
    {
        foreach ($this->children as $child) {
            $child->drawView();
        }
    }

    /**
     * Resize the group and reflow every subview by its growMode (faithful to
     * TGroup::changeBounds, which calls each child's calcBounds with the size delta).
     */
    public function changeBounds(Rect $bounds): void
    {
        $delta = new Point(
            $bounds->width() - $this->bounds->width(),
            $bounds->height() - $this->bounds->height(),
        );

        $this->setBounds($bounds);

        if ($delta->x !== 0 || $delta->y !== 0) {
            foreach ($this->children as $child) {
                $child->changeBounds($child->calcBounds($delta));
            }
        }

        $this->drawView();
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isNothing()) {
            return;
        }

        $bit = $event->what->value;

        if (($bit & EventMask::Positional) !== 0) {
            $this->handlePositional($event);

            return;
        }

        if (($bit & EventMask::Broadcast) !== 0) {
            foreach ($this->children as $child) {
                if (! $event->isNothing()) {
                    $child->handleEvent($event);
                }
            }

            return;
        }

        // Focused events (keyboard | command): current first, then any subview.
        if (($bit & EventMask::Focused) !== 0) {
            $this->currentView?->handleEvent($event);
            if ($event->isNothing()) {
                return;
            }
            foreach ($this->children as $child) {
                if ($child === $this->currentView) {
                    continue;
                }
                $child->handleEvent($event);
                if ($event->isNothing()) {
                    return;
                }
            }
        }
    }

    private function handlePositional(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        // Topmost (last inserted) subview whose bounds contains the point.
        for ($i = count($this->children) - 1; $i >= 0; $i--) {
            $child = $this->children[$i];
            if ($child->getBounds()->contains($mouse->where)) {
                $child->handleEvent($event);

                return;
            }
        }
    }

    // --- modality ---

    /** End the current modal execute() loop with $command. */
    public function endModal(int $command): void
    {
        $this->endState = $command;
    }

    /**
     * Insert $modal, mark it modal, pump events to it until it ends modal, then
     * remove it and return the end-state command. The keystone for M3 dialogs.
     */
    public function execView(View $modal): int
    {
        $saveOwner = $modal->owner;
        $saveEndState = $this->endState;
        $this->endState = 0;

        if ($saveOwner === null) {
            $this->insert($modal);
        }
        $modal->setState(State::Modal, true);

        $modal->drawView();

        while ($this->endState === 0) {
            $event = $this->pumpEvent();
            if ($event === null) {
                continue;
            }
            $modal->handleEvent($event);
            $modal->drawView();
        }

        $result = $this->endState;
        $modal->setState(State::Modal, false);
        if ($saveOwner === null) {
            $this->remove($modal);
        }
        $this->endState = $saveEndState;

        return $result;
    }

    /** Enqueue an event for the modal/main loop; delegates up to the root Program. */
    public function putEvent(Event $event): void
    {
        $owner = $this->owner;
        if ($owner instanceof Group) {
            $owner->putEvent($event);
        }
    }

    /**
     * Fetch the next event for a modal loop. Walks up to the owner if available;
     * otherwise polls the Screen directly (covers a Group that IS the root, e.g.,
     * RootGroup in tests or Program which overrides this entirely).
     * Returns null on an idle tick (no events ready).
     */
    public function pumpEvent(): ?Event
    {
        if ($this->owner !== null) {
            return $this->owner->pumpEvent();
        }

        $screen = $this->screen();
        if ($screen === null) {
            return null;
        }

        $events = $screen->pollEvents(0);

        return $events[0] ?? null;
    }
}
