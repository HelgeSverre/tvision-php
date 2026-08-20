<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use InvalidArgumentException;

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
        if ($view === $this) {
            throw new InvalidArgumentException('A group cannot own itself.');
        }
        for ($ancestor = $this->owner; $ancestor !== null; $ancestor = $ancestor->owner) {
            if ($ancestor === $view) {
                throw new InvalidArgumentException('Inserting an ancestor would create an ownership cycle.');
            }
        }
        if ($view->owner !== null || in_array($view, $this->children, true)) {
            throw new InvalidArgumentException('A view must be unowned before it can be inserted.');
        }

        $view->setOwner($this);
        $this->children[] = $view;
        // ofSelectable is an OPTION flag (lives in $options), not a state flag.
        if ($this->currentView === null && ($view->options & State::Selectable) !== 0) {
            $this->setCurrent($view);
        }
    }

    public function remove(View $view): void
    {
        $index = array_search($view, $this->children, true);
        if ($index === false) {
            return;
        }

        array_splice($this->children, $index, 1);
        if ($this->currentView === $view) {
            $view->setState(State::Focused | State::Selected, false);
            $this->currentView = null;
        }
        $view->setOwner(null);
    }

    /** @return list<View> */
    public function subviews(): array
    {
        return $this->children;
    }

    /** Detach every child while preserving the ownership invariants. */
    protected function clearSubviews(): void
    {
        foreach ($this->children as $child) {
            $child->setState(State::Focused | State::Selected, false);
            $child->setOwner(null);
        }
        $this->children = [];
        $this->currentView = null;
    }

    public function current(): ?View
    {
        return $this->currentView;
    }

    public function setCurrent(?View $view): void
    {
        if ($view !== null && ($view->owner !== $this || ! in_array($view, $this->children, true))) {
            throw new InvalidArgumentException('The current view must belong to this group.');
        }
        if ($view === $this->currentView) {
            return;
        }

        if ($this->currentView !== null) {
            $this->currentView->setState(State::Focused | State::Selected, false);
        }
        $this->currentView = $view;
        if ($view !== null) {
            $view->setState(State::Focused | State::Selected, true);
        }
    }

    /** Advance focus to the next selectable subview, wrapping. */
    public function selectNext(): void
    {
        $this->selectRelative(1);
    }

    /** Move focus to the previous selectable subview, wrapping. */
    public function selectPrevious(): void
    {
        $this->selectRelative(-1);
    }

    private function selectRelative(int $direction): void
    {
        $selectable = array_values(array_filter(
            $this->children,
            static fn (View $v): bool => ($v->options & State::Selectable) !== 0
                && ! $v->getState(State::Disabled),
        ));
        if ($selectable === []) {
            return;
        }

        $idx = null;
        foreach ($selectable as $i => $v) {
            if ($v === $this->currentView) {
                $idx = $i;
                break;
            }
        }

        if ($idx === null) {
            $next = $direction > 0 ? $selectable[0] : $selectable[array_key_last($selectable)];
        } else {
            $count = count($selectable);
            $next = $selectable[(($idx + $direction) % $count + $count) % $count];
        }
        $this->setCurrent($next);
    }

    public function focusNext(): void
    {
        $this->selectNext();
    }

    public function focusPrevious(): void
    {
        $this->selectPrevious();
    }

    /** Move an owned view to the top of this group's Z-order. */
    protected function bringToFront(View $view): void
    {
        foreach ($this->children as $i => $child) {
            if ($child !== $view) {
                continue;
            }

            array_splice($this->children, $i, 1);
            $this->children[] = $view;

            return;
        }
    }

    public function hasMouseCapture(): bool
    {
        if (parent::hasMouseCapture()) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->hasMouseCapture()) {
                return true;
            }
        }

        return false;
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

        // An in-progress drag owns all subsequent move/up events, even when the
        // pointer leaves the view's bounds.
        for ($i = count($this->children) - 1; $i >= 0; $i--) {
            $child = $this->children[$i];
            if ($child->hasMouseCapture()) {
                $child->handleEvent($event);

                return;
            }
        }

        $local = $this->makeLocal($mouse->where);

        // Topmost visible, enabled subview whose owner-local bounds contain the point.
        for ($i = count($this->children) - 1; $i >= 0; $i--) {
            $child = $this->children[$i];
            if (! $child->getState(State::Visible) || $child->getState(State::Disabled)) {
                continue;
            }
            if (! $child->getBounds()->contains($local)) {
                continue;
            }

            if ($event->what === EventType::MouseDown
                && ($child->options & State::Selectable) !== 0
                && $child !== $this->currentView
            ) {
                $this->setCurrent($child);
                if (($child->options & State::TopSelect) !== 0) {
                    $this->bringToFront($child);
                }
                if (($child->options & State::FirstClick) === 0) {
                    $this->clearEvent($event);

                    return;
                }
            }

            $child->handleEvent($event);

            return;
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
        if ($saveOwner !== null && $saveOwner !== $this) {
            throw new InvalidArgumentException('A modal view must be unowned or owned by the executing group.');
        }
        $saveEndState = $this->endState;
        $this->endState = 0;

        if ($saveOwner === null) {
            $this->insert($modal);
        }
        $modal->setState(State::Modal, true);

        try {
            $modal->drawView();

            while ($this->endState === 0) {
                $event = $this->pumpEvent();
                if ($event === null) {
                    continue;
                }
                $modal->handleEvent($event);
                $modal->drawView();
            }

            return $this->endState;
        } finally {
            $modal->setState(State::Modal, false);
            if ($saveOwner === null) {
                $this->remove($modal);
            }
            $this->endState = $saveEndState;
        }
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
