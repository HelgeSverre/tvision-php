<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Scrollable list of items in the selected ColorGroup. */
final class ColorItemList extends ListViewer
{
    private ?ColorGroup $group = null;

    public function __construct(Rect $bounds, ?ScrollBar $scrollBar, ?ColorGroup $group = null)
    {
        $this->group = $group;
        parent::__construct($bounds, 1, null, $scrollBar);
        $this->eventMask |= EventMask::Broadcast;
        $this->setRange($group?->count() ?? 0);
        if ($group !== null) {
            $this->focused = $group->selectedIndex;
        }
    }

    public function currentGroup(): ?ColorGroup
    {
        return $this->group;
    }

    public function getText(int $item, int $maxLen): string
    {
        $entry = $this->group?->item($item);

        return $entry === null ? '' : mb_strcut($entry->name, 0, $maxLen, 'UTF-8');
    }

    public function focusItem(int $item): void
    {
        $entry = $this->group?->item($item);
        if ($entry === null) {
            return;
        }
        parent::focusItem($item);
        $this->group->select($item);
        $this->owner?->handleEvent(Event::broadcast(ColorCommand::SaveIndex, $item));
        $this->owner?->handleEvent(Event::broadcast(ColorCommand::NewIndex, $entry->index));
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if (! $event->isCommand(ColorCommand::NewItem)) {
            return;
        }
        $group = $event->asMessage()?->info;
        if (! $group instanceof ColorGroup) {
            return;
        }
        $this->group = $group;
        $this->setRange($group->count());
        if ($group->count() > 0) {
            $this->focusItem($group->selectedIndex);
        }
        $this->drawView();
    }
}
