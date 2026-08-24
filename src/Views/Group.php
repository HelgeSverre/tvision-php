<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Exceptions\InputClosedException;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use InvalidArgumentException;

/**
 * A View owning an ordered Z-ordered subview list (faithful to TGroup). Routes events
 * (positional -> subview under the mouse; focused -> pre-processors, current, then
 * post-processors; broadcast -> all), manages focus, and runs the execView modal loop.
 */
class Group extends View
{
    /** @var list<View> Z-order: later entries draw on top. */
    protected array $children = [];

    protected ?View $currentView = null;

    /** Non-zero ends the active modal execute() loop with this command code. */
    protected int $endState = 0;

    /** @var list<Event> Events queued on a root Group that is not a Program. */
    private array $queuedEvents = [];

    /** Nested drawing locks delay a full group redraw until the outer unlock. */
    private int $drawLock = 0;

    private bool $drawPending = false;

    /** The view currently being executed modally by this group, if any. */
    private ?View $executingModal = null;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        // A group is a router: unlike a plain view it needs every event class in
        // order to decide which child, if any, should receive it.
        $this->eventMask = 0xFFFF;
    }

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

        $this->centerIfRequested($view);
        $view->setOwner($this);
        $this->children[] = $view;
        // ofSelectable is an OPTION flag (lives in $options), not a state flag.
        if ($this->currentView === null
            && ($view->options & State::Selectable) !== 0
            && $this->acceptsFocusedEvents($view)
        ) {
            $this->setCurrent($view);
        }
    }

    public function remove(View $view): void
    {
        $index = array_search($view, $this->children, true);
        if ($index === false) {
            return;
        }

        $wasCurrent = $this->currentView === $view;
        $wasVisible = ($view->state & State::Visible) !== 0;
        array_splice($this->children, $index, 1);
        if ($wasCurrent) {
            $view->setState(State::Focused | State::Selected, false);
            $this->currentView = null;
        }
        $view->setOwner(null);

        if ($wasCurrent) {
            $this->focusReplacement($index);
        }

        // A visible child just vacated (or uncovered) screen area. Redraw the
        // group so what remains underneath is painted immediately — otherwise
        // the removed view's pixels linger in modal loops and programmatic
        // removals until some unrelated event forces a redraw.
        if ($wasVisible && $this->screen() !== null) {
            $this->drawView();
        }
    }

    /** @return list<View> */
    public function subviews(): array
    {
        return $this->children;
    }

    /**
     * Iterate child views in insertion order (= z-order bottom to top).
     *
     * @return \ArrayIterator<int, View>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->children);
    }

    /** The child windows (Desktop convenience; empty for non-desktop groups).
     * @return list<Window>
     */
    public function windows(): array
    {
        $windows = [];
        foreach ($this->children as $child) {
            if ($child instanceof Window) {
                $windows[] = $child;
            }
        }

        return $windows;
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

    /** Apply ofCenterX/ofCenterY in owner-local coordinates before ownership changes. */
    private function centerIfRequested(View $view): void
    {
        $bounds = $this->centeredBounds($view);
        if ($bounds === null) {
            return;
        }

        $view->setBounds($bounds);
    }

    /** Recalculate an opted-in child's centered owner-local bounds. */
    private function centeredBounds(View $view): ?Rect
    {
        $options = $view->options;
        if (($options & State::Centered) === 0) {
            return null;
        }

        $bounds = $view->getBounds();
        $x = $bounds->a->x;
        $y = $bounds->a->y;
        if (($options & State::CenterX) !== 0) {
            $x = max(0, intdiv($this->getExtent()->width() - $bounds->width(), 2));
        }
        if (($options & State::CenterY) !== 0) {
            $y = max(0, intdiv($this->getExtent()->height() - $bounds->height(), 2));
        }

        return Rect::of($x, $y, $x + $bounds->width(), $y + $bounds->height());
    }

    /** Keep current selection valid when a selected child is hidden or disabled. */
    public function viewStateChanged(View $view, int $flag, bool $enable): void
    {
        if ($view !== $this->currentView
            || $enable
            || ($flag & (State::Visible | State::Disabled)) === 0
        ) {
            return;
        }

        $index = array_search($view, $this->children, true);
        $this->setCurrent(null);
        if ($index !== false) {
            $this->focusReplacement($index);
        }
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

    /**
     * Active and drag state belong to the whole composite, while focus belongs to
     * its current child. This lets a selected Window activate nested Scrollers and
     * expose their scrollbars without every intermediate container duplicating it.
     */
    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);

        if (($flag & (State::Active | State::Dragging)) !== 0) {
            $this->lock();
            try {
                foreach ($this->children as $child) {
                    $child->setState($flag, $enable);
                }
            } finally {
                $this->unlock();
            }
        }

        if (($flag & State::Focused) !== 0 && $this->currentView !== null) {
            $this->currentView->setState(State::Focused, $enable);
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
                && $v->getState(State::Visible)
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

    /**
     * Put $view ahead of $target in Z order. A null target means front-most.
     * Both views must belong to this group; a target equal to the view is a no-op.
     */
    public function reorderInFrontOf(View $view, ?View $target): void
    {
        $index = array_search($view, $this->children, true);
        if ($index === false) {
            throw new InvalidArgumentException('The reordered view must belong to this group.');
        }
        if ($target === $view) {
            return;
        }
        if ($target !== null && ! in_array($target, $this->children, true)) {
            throw new InvalidArgumentException('The target view must belong to this group.');
        }

        array_splice($this->children, $index, 1);
        if ($target === null) {
            $this->children[] = $view;
        } else {
            $targetIndex = array_search($target, $this->children, true);
            // Later entries are drawn above earlier ones, so insert immediately
            // after the target to put this view in front of it.
            array_splice($this->children, $targetIndex + 1, 0, [$view]);
        }
        $this->drawView();
    }

    /**
     * Return the part of a screen row obscured by siblings drawn after $child.
     * Used by View's compositor so a direct redraw of a rear sibling cannot
     * overwrite an already-drawn front sibling.
     *
     * @return list<array{0:int,1:int}>
     */
    public function higherSiblingIntervals(View $child, int $globalY, int $minX, int $maxX): array
    {
        $index = array_search($child, $this->children, true);
        if ($index === false || $minX >= $maxX) {
            return [];
        }

        $intervals = [];
        for ($i = $index + 1, $count = count($this->children); $i < $count; $i++) {
            $sibling = $this->children[$i];
            array_push($intervals, ...$sibling->occlusionIntervals($globalY, $minX, $maxX));
        }

        return $intervals;
    }

    /**
     * Opaque groups cover their whole extent. Transparent groups contribute only
     * the portions painted by visible descendants, clipped to the group itself.
     *
     * @return list<array{0:int,1:int}>
     */
    public function occlusionIntervals(int $globalY, int $minX, int $maxX): array
    {
        $interval = OcclusionRow::clip($this, $globalY, $minX, $maxX);
        if ($interval === null) {
            return [];
        }
        [$start, $end] = $interval;
        if ($this->isOpaque()) {
            return [$interval];
        }

        $intervals = [];
        foreach ($this->children as $child) {
            array_push($intervals, ...$child->occlusionIntervals($globalY, $start, $end));
        }

        return $intervals;
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
        if ($this->drawLock > 0) {
            $this->drawPending = true;

            return;
        }

        foreach ($this->children as $child) {
            $child->drawView();
        }
    }

    /** Defer drawing until matching unlock() calls finish. Safe for nesting. */
    public function lock(): void
    {
        $this->drawLock++;
    }

    /** Complete a deferred drawing region. Extra unlocks are harmless no-ops. */
    public function unlock(): void
    {
        if ($this->drawLock === 0) {
            return;
        }

        $this->drawLock--;
        if ($this->drawLock === 0 && $this->drawPending) {
            $this->drawPending = false;
            $this->drawView();
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
                $child->changeBounds($this->centeredBounds($child) ?? $child->calcBounds($delta));
            }
        }

        $this->drawView();
    }

    /**
     * Return child data in insertion/Z order. PHP values replace the original
     * byte-record pointer while preserving the same per-child transfer contract.
     */
    public function getData(): mixed
    {
        $data = [];
        foreach ($this->children as $child) {
            if ($child->dataSize() > 0) {
                $data[] = $child->getData();
            }
        }

        return $data;
    }

    public function dataSize(): int
    {
        $size = 0;
        foreach ($this->children as $child) {
            $size += max(0, $child->dataSize());
        }

        return $size;
    }

    public function setData(mixed $data): void
    {
        if (! is_array($data)) {
            return;
        }

        $offset = 0;
        foreach ($this->children as $child) {
            if ($child->dataSize() <= 0) {
                continue;
            }
            if (array_key_exists($offset, $data)) {
                $child->setData($data[$offset]);
            }
            $offset++;
        }
    }

    /** The selected child supplies context unless it explicitly has none. */
    public function getHelpCtx(): int
    {
        $context = $this->currentView?->getHelpCtx() ?? 0;

        return $context !== 0 ? $context : parent::getHelpCtx();
    }

    /** Validate all controls, except focus-release validation targets the current one. */
    public function valid(int $command): bool
    {
        if ($command === Cmd::ReleasedFocus) {
            return $this->currentView === null
                || ($this->currentView->options & State::Validate) === 0
                || $this->currentView->valid($command);
        }

        foreach ($this->children as $child) {
            if (! $child->valid($command)) {
                return false;
            }
        }

        return true;
    }

    /** The focused path owns the terminal cursor, not the group container. */
    public function cursorPosition(): ?Point
    {
        return $this->currentView?->cursorPosition() ?? parent::cursorPosition();
    }

    public function resetCursor(): void
    {
        $this->screen()?->setCursor($this->cursorPosition());
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
                if (! $event->isNothing() && $this->acceptsEvent($child, $event)) {
                    $child->handleEvent($event);
                }
            }

            return;
        }

        // Focused events (keyboard | command) travel through the three Turbo Vision
        // phases. Ordinary non-current children must not observe someone else's key
        // presses or commands merely because they share an owner.
        if (($bit & EventMask::Focused) !== 0) {
            $this->handleFocusedPhase($event, State::PreProcess);

            if (! $event->isNothing()
                && $this->currentView !== null
                && $this->acceptsFocusedEvents($this->currentView)
                && $this->acceptsEvent($this->currentView, $event)
            ) {
                $this->currentView->handleEvent($event);
            }

            $this->handleFocusedPhase($event, State::PostProcess);
        }
    }

    private function handleFocusedPhase(Event $event, int $option): void
    {
        if ($event->isNothing()) {
            return;
        }

        foreach ($this->children as $child) {
            if ($child === $this->currentView
                || ($child->options & $option) === 0
                || ! $this->acceptsFocusedEvents($child)
                || ! $this->acceptsEvent($child, $event)
            ) {
                continue;
            }

            $child->handleEvent($event);
            if ($event->isNothing()) {
                return;
            }
        }
    }

    private function acceptsFocusedEvents(View $view): bool
    {
        return $view->getState(State::Visible) && ! $view->getState(State::Disabled);
    }

    private function acceptsEvent(View $view, Event $event): bool
    {
        return $event->what->inMask($view->eventMask);
    }

    /** Select the closest eligible child at or after a removed child's old index. */
    private function focusReplacement(int $start): void
    {
        $count = count($this->children);
        for ($offset = 0; $offset < $count; $offset++) {
            $candidate = $this->children[($start + $offset) % $count];
            if (($candidate->options & State::Selectable) !== 0
                && $this->acceptsFocusedEvents($candidate)
            ) {
                $this->setCurrent($candidate);

                return;
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
            if ($child->hasMouseCapture() && $this->acceptsEvent($child, $event)) {
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
            if (! $this->acceptsEvent($child, $event)) {
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
        if ($this->getState(State::Modal)) {
            $this->endState = $command;

            return;
        }

        if ($this->executingModal !== null) {
            if ($this->executingModal->valid($command)) {
                $this->endState = $command;
            }

            return;
        }

        if ($this->owner === null) {
            if ($this->valid($command)) {
                $this->endState = $command;
            }

            return;
        }

        parent::endModal($command);
    }

    /**
     * Insert $modal, pump events to it until it ends, then remove it and return the
     * end-state command.
     */
    public function execView(View $modal): int
    {
        $saveOwner = $modal->owner;
        if ($saveOwner !== null && $saveOwner !== $this) {
            throw new InvalidArgumentException('A modal view must be unowned or owned by the executing group.');
        }
        $saveEndState = $this->endState;
        $saveExecutingModal = $this->executingModal;
        $saveCurrent = $this->currentView;
        $this->endState = 0;
        $this->executingModal = $modal;
        $saveModalEndState = $modal instanceof self ? $modal->endState : 0;
        if ($modal instanceof self) {
            $modal->endState = 0;
        }

        if ($saveOwner === null) {
            $this->insert($modal);
        }
        $modal->setState(State::Modal, true);
        // A modal is the active view for its whole execution scope. This drives
        // window/frame activation and lets its focused controls receive events.
        $this->setCurrent($modal);

        try {
            $modal->drawView();
            $modal->present();

            while (true) {
                while ($this->endState === 0 && (! $modal instanceof self || $modal->endState === 0)) {
                    try {
                        $event = $this->pumpEvent();
                    } catch (InputClosedException) {
                        // A closed PTY/stdin is a normal lifecycle end, not a
                        // framework failure. End this modal gracefully, matching
                        // run()'s lifecycle policy.
                        $this->endState = Cmd::Quit;

                        continue;
                    }

                    if ($event === null) {
                        continue;
                    }

                    $modalEnd = $this->handleModalEvent($event);
                    if ($modalEnd !== null) {
                        $this->endState = $modalEnd;
                    } elseif (! $event->isNothing()) {
                        $modal->handleEvent($event);
                    }

                    $modal->drawView();
                    $modal->present();
                }

                if ($modal instanceof self && $modal->endState !== 0) {
                    $result = $modal->endState;
                    // A grouped modal owns its validation pass; a rejected command
                    // remains modal rather than leaking a partial form result.
                    if (! $modal->valid($result)) {
                        $modal->endState = 0;

                        continue;
                    }

                    return $result;
                }

                return $this->endState;
            }
        } finally {
            $modal->setState(State::Modal, false);
            if ($modal instanceof self) {
                $modal->endState = $saveModalEndState;
            }
            if ($saveOwner === null) {
                $this->remove($modal);
            }
            if ($saveCurrent === null) {
                $this->setCurrent(null);
            } elseif ($saveCurrent->owner === $this && in_array($saveCurrent, $this->children, true)) {
                $this->setCurrent($saveCurrent);
            }
            $this->executingModal = $saveExecutingModal;
            $this->endState = $saveEndState;
            $this->drawView();
            $this->present();
        }
    }

    /** Enqueue an event for the modal/main loop; delegates up to the root Program. */
    public function putEvent(Event $event): void
    {
        $owner = $this->owner;
        if ($owner instanceof Group) {
            $owner->putEvent($event);

            return;
        }

        $this->queuedEvents[] = $event;
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

        if ($this->queuedEvents !== []) {
            return array_shift($this->queuedEvents);
        }

        $screen = $this->screen();
        if ($screen === null) {
            return null;
        }

        $events = $screen->pollEvents(20);
        if (count($events) > 1) {
            array_push($this->queuedEvents, ...array_slice($events, 1));
        }

        return $events[0] ?? null;
    }
}
