<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * The backdrop Group (faithful to TDeskTop). Occupies the area between the menu bar
 * and the status line, and owns a Background filling its extent. Hosts windows in M2.
 */
class Desktop extends Group
{
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
                $this->cycleWindow($msg->command === Cmd::Next ? 1 : -1);
                $this->clearEvent($event);

                return;
            }
        }

        parent::handleEvent($event);
    }

    /** Cycle the current window to the next selectable window (wrapping). */
    private function cycleWindow(int $direction): void
    {
        $windows = array_values(array_filter(
            $this->subviews(),
            static fn (View $v): bool => $v instanceof Window,
        ));
        if (count($windows) < 2) {
            return;
        }

        $idx = 0;
        foreach ($windows as $i => $w) {
            if ($w === $this->current()) {
                $idx = $i;
                break;
            }
        }
        $count = count($windows);
        $next = $windows[(($idx + $direction) % $count + $count) % $count];
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
}
