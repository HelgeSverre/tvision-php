<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * The backdrop Group (faithful to TDeskTop). Occupies the area between the menu bar
 * and status line, owns a Background filling its extent, and hosts application windows.
 */
class Desktop extends Group
{
    /** Arrange tiles by rows by default; set true to prefer vertical columns. */
    public bool $tileColumnsFirst = false;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->insert($this->initBackground());
    }

    protected function initBackground(): Background
    {
        $extent = $this->getExtent();

        return new Background($extent);
    }

    /** Insert a window and select it (focus + frame active), faithful to TDeskTop. */
    public function insertWindow(Window $window): void
    {
        $this->insert($window);
        $this->selectWindow($window);
    }

    /** Make $window the current, selected view; deselect the previous current. */
    public function selectWindow(View $window): void
    {
        $this->setCurrent($window);
        if (($window->options & State::TopSelect) !== 0) {
            $this->bringToFront($window);
        }
    }

    public function remove(View $view): void
    {
        $wasCurrent = $this->current() === $view;
        parent::remove($view);

        if ($wasCurrent) {
            $next = $this->topmostWindow();
            if ($next !== null) {
                $this->selectWindow($next);
            }
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Command) {
            $msg = $event->asMessage();
            if ($msg !== null && ($msg->command === Cmd::Next || $msg->command === Cmd::Prev)) {
                $this->clearEvent($event);
                if ($this->valid(Cmd::ReleasedFocus)) {
                    $this->cycleWindow($msg->command === Cmd::Next ? 1 : -1);
                }

                return;
            }
        }

        parent::handleEvent($event);
    }

    /**
     * Redisplay visible, tileable children in overlapping cascade order.
     *
     * The back-most child is offset the furthest, matching TDeskTop::cascade.
     */
    public function cascade(Rect $bounds): void
    {
        $tileable = $this->tileableViews();
        $count = count($tileable);
        if ($count === 0) {
            return;
        }

        $placements = [];
        foreach ($tileable as $index => $view) {
            $offset = $count - $index - 1;
            $placement = Rect::of(
                $bounds->a->x + $offset,
                $bounds->a->y + $offset,
                $bounds->b->x,
                $bounds->b->y,
            );
            if (! $this->fitsMinimumSize($view, $placement)) {
                $this->tileError();

                return;
            }
            $placements[] = [$view, $placement];
        }
        $this->applyLayout($placements);
    }

    /** Arrange visible, tileable children across $bounds, without gaps or overlap. */
    public function tile(Rect $bounds): void
    {
        $tileable = $this->tileableViews();
        $count = count($tileable);
        if ($count === 0) {
            return;
        }

        [$columns, $rows] = $this->mostEqualDivisors($count, ! $this->tileColumnsFirst);
        if (intdiv($bounds->width(), $columns) === 0 || intdiv($bounds->height(), $rows) === 0) {
            $this->tileError();

            return;
        }

        $placements = [];
        foreach ($tileable as $index => $view) {
            // TDeskTop walks views back-to-front while tileNum counts down.
            $position = $count - $index - 1;
            $placement = $this->tileRect($position, $bounds, $columns, $rows);
            if (! $this->fitsMinimumSize($view, $placement)) {
                $this->tileError();

                return;
            }
            $placements[] = [$view, $placement];
        }
        $this->applyLayout($placements);
    }

    /** Override to surface an arrangement which cannot fit the requested bounds. */
    protected function tileError(): void {}

    private function fitsMinimumSize(View $view, Rect $bounds): bool
    {
        $limits = $view->sizeLimits();

        return $bounds->width() >= $limits->minWidth && $bounds->height() >= $limits->minHeight;
    }

    /** @param list<array{0: View, 1: Rect}> $placements */
    private function applyLayout(array $placements): void
    {
        $this->lock();
        try {
            foreach ($placements as [$view, $bounds]) {
                $view->locate($bounds);
            }
        } finally {
            $this->unlock();
        }
    }

    /** Cycle the current window to the next selectable window (wrapping). */
    private function cycleWindow(int $direction): void
    {
        $windows = array_values(array_filter(
            $this->subviews(),
            static fn (View $v): bool => $v instanceof Window
                && ($v->options & State::Selectable) !== 0
                && $v->getState(State::Visible)
                && ! $v->getState(State::Disabled),
        ));
        if (count($windows) < 2) {
            return;
        }

        $idx = null;
        foreach ($windows as $i => $w) {
            if ($w === $this->current()) {
                $idx = $i;
                break;
            }
        }
        $count = count($windows);
        $next = $idx === null
            ? ($direction > 0 ? $windows[0] : $windows[array_key_last($windows)])
            : $windows[(($idx + $direction) % $count + $count) % $count];
        $this->selectWindow($next);
    }

    private function topmostWindow(): ?Window
    {
        $subs = $this->subviews();
        for ($i = count($subs) - 1; $i >= 0; $i--) {
            if ($subs[$i] instanceof Window) {
                return $subs[$i];
            }
        }

        return null;
    }

    /** @return list<View> */
    private function tileableViews(): array
    {
        return array_values(array_filter(
            $this->subviews(),
            static fn (View $view): bool => ($view->options & State::Tileable) !== 0
                && $view->getState(State::Visible),
        ));
    }

    /** @return array{0: int, 1: int} [columns, rows] */
    private function mostEqualDivisors(int $count, bool $favorRows): array
    {
        $smaller = 1;
        for ($candidate = (int) floor(sqrt($count)); $candidate >= 1; $candidate--) {
            if ($count % $candidate === 0) {
                $smaller = $candidate;
                break;
            }
        }
        $larger = intdiv($count, $smaller);

        return $favorRows ? [$smaller, $larger] : [$larger, $smaller];
    }

    private function tileRect(int $position, Rect $bounds, int $columns, int $rows): Rect
    {
        $x = intdiv($position, $rows);
        $y = $position % $rows;

        return Rect::of(
            $this->divider($bounds->a->x, $bounds->b->x, $columns, $x),
            $this->divider($bounds->a->y, $bounds->b->y, $rows, $y),
            $this->divider($bounds->a->x, $bounds->b->x, $columns, $x + 1),
            $this->divider($bounds->a->y, $bounds->b->y, $rows, $y + 1),
        );
    }

    private function divider(int $low, int $high, int $count, int $position): int
    {
        return $low + intdiv(($high - $low) * $position, $count);
    }
}
