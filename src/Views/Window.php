<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Commands\CommandSet;
use HelgeSverre\TurboVision\Commands\CommandTarget;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

/**
 * A framed, movable, resizable, zoomable, closable group (faithful to TWindow). Owns a
 * Frame as its first subview, carries a title + number + wf* flags, resolves color
 * through one of the three window palettes, handles close/zoom and captured mouse drags,
 * and cycles focus with Tab/Shift-Tab. Implements FrameOwner for frame rendering.
 */
class Window extends Group implements FrameOwner
{
    public int $flags = WindowFlags::Default;

    protected int $paletteIndex = WindowPalette::Blue;

    protected Rect $zoomRect;

    protected ?Frame $frame = null;

    private ?Point $dragAnchor = null;

    private ?Rect $dragStartBounds = null;

    private int $dragKind = 0;

    public function __construct(
        Rect $bounds,
        protected string $title = '',
        protected int $number = 0,
    ) {
        parent::__construct($bounds);

        $this->state |= State::Shadow;
        $this->options |= State::Selectable | State::TopSelect;
        $this->growMode = State::GrowAll | State::GrowRel;
        $this->zoomRect = $bounds;

        $this->frame = $this->initFrame($this->getExtent());
        if ($this->frame !== null) {
            $this->insert($this->frame);
        }
    }

    /** Override to supply a custom Frame subclass. */
    protected function initFrame(Rect $extent): ?Frame
    {
        return new Frame($extent);
    }

    // --- FrameOwner ---

    public function frameTitle(): string
    {
        return $this->title;
    }

    public function frameFlags(): int
    {
        return $this->flags;
    }

    public function frameNumber(): int
    {
        return $this->number;
    }

    public function frame(): ?Frame
    {
        return $this->frame;
    }

    public function frameIsZoomed(): bool
    {
        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();

        return $this->bounds->width() === $maxW && $this->bounds->height() === $maxH;
    }

    // --- title, number, palette ---

    public function setPalette(int $index): void
    {
        $index = WindowPalette::normalize($index);
        if ($this->paletteIndex === $index) {
            return;
        }

        $this->paletteIndex = $index;
        $this->drawView();
    }

    public function paletteIndex(): int
    {
        return $this->paletteIndex;
    }

    public function setTitle(string $title): void
    {
        if ($this->title === $title) {
            return;
        }

        $this->title = $title;
        $this->frame?->drawView();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setNumber(int $number): void
    {
        if ($this->number === $number) {
            return;
        }

        $this->number = $number;
        $this->frame?->drawView();
    }

    public function setFlags(int $flags): void
    {
        if ($this->flags === $flags) {
            return;
        }

        $selected = $this->getState(State::Selected);
        if ($selected) {
            $this->setWindowCommands(false);
        }
        $this->flags = $flags;
        if ($selected) {
            $this->setWindowCommands(true);
        }
        $this->frame?->drawView();
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(WindowPalette::byteFor($this->paletteIndex));
    }

    // --- geometry ---

    /** Faithful min window size 16x6; max is the desktop extent if owned, else unbounded. */
    public function sizeLimits(): array
    {
        $maxW = PHP_INT_MAX;
        $maxH = PHP_INT_MAX;
        if ($this->owner !== null) {
            $ext = $this->owner->getExtent();
            $maxW = $ext->width();
            $maxH = $ext->height();
        }

        return [16, 6, $maxW, $maxH];
    }

    /**
     * Build, insert and return a standard scroll bar on the right (vertical) or bottom
     * (horizontal) edge. Integer flags remain supported for compatibility.
     */
    public function standardScrollBar(
        ScrollBarOrientation|int $options,
        bool $handleKeyboard = false,
    ): ScrollBar {
        if (is_int($options)) {
            $handleKeyboard = $handleKeyboard
                || ($options & ScrollBarPart::HandleKeyboard) !== 0;
            $orientation = ($options & ScrollBarPart::Vertical) !== 0
                ? ScrollBarOrientation::Vertical
                : ScrollBarOrientation::Horizontal;
        } else {
            $orientation = $options;
        }

        $ext = $this->getExtent();

        if ($orientation === ScrollBarOrientation::Vertical) {
            $r = Rect::of($ext->b->x - 1, $ext->a->y + 1, $ext->b->x, $ext->b->y - 1);
        } else {
            $r = Rect::of($ext->a->x + 2, $ext->b->y - 1, $ext->b->x - 2, $ext->b->y);
        }

        $bar = new ScrollBar($r, $orientation);
        $this->insert($bar);
        if ($handleKeyboard) {
            $bar->options |= State::PostProcess;
        }

        return $bar;
    }

    public function handleEvent(Event $event): void
    {
        if ($this->getState(State::Dragging)) {
            if ($event->what === EventType::MouseMove || $event->what === EventType::MouseUp) {
                $mouse = $event->asMouse();
                if ($mouse !== null) {
                    $this->updateMouseDrag($mouse);
                    if ($event->what === EventType::MouseUp) {
                        $this->finishMouseDrag();
                    }
                    $this->clearEvent($event);
                }

                return;
            }

            if ($event->isKey(Key::Esc)) {
                $this->finishMouseDrag(cancel: true);
                $this->clearEvent($event);

                return;
            }
        }

        parent::handleEvent($event);

        if ($event->what === EventType::Command) {
            $msg = $event->asMessage();
            if ($msg !== null) {
                $info = $msg->info;
                $forUs = $info === null || $info === $this;

                switch ($msg->command) {
                    case Cmd::Resize:
                        if (($this->flags & (WindowFlags::Move | WindowFlags::Grow)) !== 0 && $forUs) {
                            // Frame drags run directly in this PHP port. Retain cmResize
                            // as a consumed compatibility command for custom frames.
                            $this->clearEvent($event);
                        }
                        break;
                    case Cmd::Close:
                        if (($this->flags & WindowFlags::Close) !== 0 && $forUs) {
                            $this->clearEvent($event);
                            if ($this->getState(State::Modal)) {
                                // A modal close must unwind through Dialog's cmCancel
                                // path, not detach the executing view out from under
                                // Group::execView().
                                $this->putEvent(Event::command(Cmd::Cancel, $this));
                            } else {
                                $this->close();
                            }
                        }
                        break;
                    case Cmd::Zoom:
                        if (($this->flags & WindowFlags::Zoom) !== 0 && $forUs) {
                            $this->zoom();
                            $this->clearEvent($event);
                        }
                        break;
                }
            }
        } elseif ($event->what === EventType::Broadcast) {
            $message = $event->asMessage();
            if ($message !== null
                && $message->command === Cmd::SelectWindowNum
                && $message->info === $this->number
                && $this->number >= 1
                && $this->number <= 9
                && ($this->options & State::Selectable) !== 0
            ) {
                $this->select();
                $this->clearEvent($event);
            }
        } elseif ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key !== null && $key->keyCode === Key::Tab->value) {
                $this->focusNext();
                $this->clearEvent($event);
            } elseif ($key !== null && $key->keyCode === Key::ShiftTab->value) {
                $this->focusPrevious();
                $this->clearEvent($event);
            }
        }
    }

    /** Begin a captured title-bar move or bottom-right resize gesture. */
    public function beginMouseDrag(MouseEvent $mouse, int $kind): void
    {
        $allowed = ($kind === State::DragMove && ($this->flags & WindowFlags::Move) !== 0)
            || ($kind === State::DragGrow && ($this->flags & WindowFlags::Grow) !== 0);
        if (! $allowed) {
            return;
        }

        $this->dragAnchor = $mouse->where;
        $this->dragStartBounds = $this->bounds;
        $this->dragKind = $kind;
        $this->setState(State::Dragging, true);
    }

    private function updateMouseDrag(MouseEvent $mouse): void
    {
        if ($this->dragAnchor === null || $this->dragStartBounds === null) {
            return;
        }

        $dx = $mouse->where->x - $this->dragAnchor->x;
        $dy = $mouse->where->y - $this->dragAnchor->y;
        $start = $this->dragStartBounds;

        $next = $this->dragKind === State::DragGrow
            ? Rect::of($start->a->x, $start->a->y, $start->b->x + $dx, $start->b->y + $dy)
            : $start->move($dx, $dy);

        $this->resizeTo($next);
    }

    private function finishMouseDrag(bool $cancel = false): void
    {
        if ($cancel && $this->dragStartBounds !== null) {
            $this->changeBounds($this->dragStartBounds);
        }

        $this->dragAnchor = null;
        $this->dragStartBounds = null;
        $this->dragKind = 0;
        $this->setState(State::Dragging, false);
    }

    /** Remove this window only when its controls accept a close validation pass. */
    public function close(): void
    {
        if ($this->valid(Cmd::Close) && $this->owner instanceof Group) {
            $this->owner->remove($this);
        }
    }

    /** Toggle between the saved zoomRect and the maximum (desktop) extent. */
    public function zoom(): void
    {
        // An unowned window has no finite desktop extent. In the C++ framework a
        // window is always owned before a zoom command can reach it; make the PHP
        // convenience API equally safe when called directly in tests or builders.
        if (! $this->owner instanceof Group) {
            return;
        }

        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();

        if ($this->bounds->width() !== $maxW || $this->bounds->height() !== $maxH) {
            $this->zoomRect = $this->bounds;
            $extent = $this->owner->getExtent();
            $originX = $extent->a->x;
            $originY = $extent->a->y;
            $this->changeBounds(Rect::of($originX, $originY, $originX + $maxW, $originY + $maxH));
        } else {
            $this->changeBounds($this->zoomRect);
        }
    }

    /** Move/resize against the owner extent, clamped to size limits (used by drag). */
    public function resizeTo(Rect $newBounds): void
    {
        $limits = $this->owner?->getExtent() ?? $this->getBounds();
        [$minW, $minH, $maxW, $maxH] = $this->sizeLimits();
        $this->dragView($newBounds, $limits, new Point($minW, $minH), new Point($maxW, $maxH));
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & State::Selected) !== 0) {
            parent::setState(State::Active, $enable);
            $this->frame?->setState(State::Active, $enable);
            $this->setWindowCommands($enable);
        }
        if (($flag & State::Dragging) !== 0) {
            $this->frame?->setState(State::Dragging, $enable);
        }
    }

    /** Commands enabled only while this window is the selected desktop window. */
    private function setWindowCommands(bool $enable): void
    {
        $target = $this->commandTarget();
        if ($target === null) {
            return;
        }

        $commands = CommandSet::of(Cmd::Next, Cmd::Prev);
        if (($this->flags & (WindowFlags::Move | WindowFlags::Grow)) !== 0) {
            $commands = $commands->with(Cmd::Resize);
        }
        if (($this->flags & WindowFlags::Close) !== 0) {
            $commands = $commands->with(Cmd::Close);
        }
        if (($this->flags & WindowFlags::Zoom) !== 0) {
            $commands = $commands->with(Cmd::Zoom);
        }

        if ($enable) {
            $commands->enableOn($target);
        } else {
            $commands->disableOn($target);
        }
    }

    private function commandTarget(): ?CommandTarget
    {
        for ($view = $this->owner; $view !== null; $view = $view->owner) {
            if ($view instanceof CommandTarget) {
                return $view;
            }
        }

        return null;
    }
}
