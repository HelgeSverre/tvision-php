<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Scrollable list of colour groups. Selecting a group publishes its items. */
final class ColorGroupList extends ListViewer
{
    /** @var list<ColorGroup> */
    private array $groups;

    /** @param iterable<ColorGroup> $groups */
    public function __construct(Rect $bounds, ?ScrollBar $scrollBar, iterable $groups)
    {
        $this->groups = array_values(is_array($groups) ? $groups : iterator_to_array($groups, false));
        parent::__construct($bounds, 1, null, $scrollBar);
        $this->eventMask |= EventMask::Broadcast;
        $this->setRange(count($this->groups));
    }

    /** @return list<ColorGroup> */
    public function groups(): array
    {
        return $this->groups;
    }

    public function group(int $index): ?ColorGroup
    {
        return $this->groups[$index] ?? null;
    }

    public function getText(int $item, int $maxLen): string
    {
        return mb_strcut($this->groups[$item]->name ?? '', 0, $maxLen, 'UTF-8');
    }

    public function focusItem(int $item): void
    {
        if (! isset($this->groups[$item])) {
            return;
        }
        parent::focusItem($item);
        $this->owner?->handleEvent(Event::broadcast(ColorCommand::NewItem, $this->groups[$item]));
    }

    public function setGroupIndex(int $groupIndex, int $itemIndex): void
    {
        $this->group($groupIndex)?->select($itemIndex);
    }

    public function getGroupIndex(int $groupIndex): int
    {
        $group = $this->group($groupIndex);

        return $group === null ? 0 : $group->selectedIndex;
    }

    public function getNumGroups(): int
    {
        return count($this->groups);
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->isCommand(ColorCommand::SaveIndex)) {
            $item = $event->asMessage()?->info;
            if (is_int($item)) {
                $this->setGroupIndex($this->focused, $item);
            }
        }
    }
}
