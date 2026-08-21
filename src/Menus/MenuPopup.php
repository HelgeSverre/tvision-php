<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;

/**
 * A reusable context menu. Insert it and run `Group::execView()` to obtain the
 * selected command; its command is also queued on the owner for normal event-loop
 * handling. Unlike the old MenuBar-only overlay, this is an actual MenuBox.
 */
final class MenuPopup extends MenuBox
{
    private ?self $child = null;

    public function __construct(
        Rect $bounds,
        Menu $menu,
        private readonly ?self $parentPopup = null,
    ) {
        parent::__construct($bounds, $menu);
        $this->onActivate(function (MenuItem $item): void {
            if ($item->subMenu !== null) {
                $this->openSubMenu($item);

                return;
            }
            $this->dispatch($item);
        });
    }

    public function child(): ?self
    {
        return $this->child;
    }

    public function handleEvent(Event $event): void
    {
        if ($this->child !== null) {
            if ($event->what === EventType::KeyDown
                && ($event->asKey()?->is(Key::Esc) || $event->asKey()?->is(Key::Left))) {
                $this->closeChild();
                $this->clearEvent($event);

                return;
            }
            $mouse = $event->asMouse();
            if ($mouse === null || $this->child->mouseInView($mouse->where)) {
                $this->child->handleEvent($event);

                return;
            }
            $this->closeChild();
        }

        if ($event->what === EventType::KeyDown
            && ($event->asKey()?->is(Key::Esc) || $event->asKey()?->is(Key::Left))) {
            if ($this->parentPopup !== null) {
                $this->parentPopup->closeChild();
                $this->clearEvent($event);

                return;
            }
            if ($this->owner instanceof Group) {
                $this->owner->endModal(Cmd::Cancel);
            }
            $this->clearEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown && $event->asKey()?->is(Key::Right)) {
            $item = $this->selectedItem();
            if ($item?->subMenu !== null) {
                $this->activate($this->selectedIndex());
                $this->clearEvent($event);

                return;
            }
        }

        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse !== null && ! $this->mouseInView($mouse->where)) {
                if ($this->parentPopup === null && $this->owner instanceof Group) {
                    $this->owner->endModal(Cmd::Cancel);
                    $this->clearEvent($event);
                }

                return;
            }
        }

        parent::handleEvent($event);
    }

    private function openSubMenu(MenuItem $item): void
    {
        if ($item->subMenu === null || $this->owner === null) {
            return;
        }
        $owner = $this->owner;
        if (! $owner instanceof Group) {
            return;
        }
        $this->closeChild();
        $bounds = MenuBox::boundsFor(
            $owner->getExtent(),
            $item->subMenu,
            new Point(
                $this->bounds->b->x - 1,
                $this->bounds->a->y + $this->selectedIndex() + 1,
            ),
        );
        $this->child = new self($bounds, $item->subMenu, $this);
        $owner->insert($this->child);
    }

    private function closeChild(): void
    {
        if ($this->child !== null) {
            $child = $this->child;
            $child->closeChild();
            if ($child->owner instanceof Group) {
                $child->owner->remove($child);
            }
        }
        $this->child = null;
    }

    private function dispatch(MenuItem $item): void
    {
        if ($item->command === 0) {
            return;
        }
        $root = $this->rootPopup();
        $owner = $root->owner;
        $root->closeChild();
        if ($owner instanceof Group) {
            $owner->putEvent(Event::command($item->command));
            $owner->endModal($item->command);
        }
    }

    private function rootPopup(): self
    {
        return $this->parentPopup?->rootPopup() ?? $this;
    }
}
