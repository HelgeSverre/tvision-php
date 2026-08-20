<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Workbench;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;

final class WorkbenchTaskList extends ListViewer
{
    /** @param list<string> $tasks */
    public function __construct(Rect $bounds, ?ScrollBar $vertical, private readonly array $tasks)
    {
        parent::__construct($bounds, 1, null, $vertical);
        $this->setRange(count($tasks));
    }

    public function getText(int $item, int $maxLen): string
    {
        return mb_substr($this->tasks[$item] ?? '', 0, $maxLen);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown && $event->asKey()?->is(Key::Enter) === true) {
            $this->selectItem($this->focused);
            $this->clearEvent($event);

            return;
        }

        parent::handleEvent($event);
    }

    public function selectItem(int $item): void
    {
        $task = $this->tasks[$item] ?? null;
        if ($task === null) {
            return;
        }
        for ($owner = $this->owner; $owner !== null; $owner = $owner->owner) {
            if ($owner instanceof Group) {
                $owner->putEvent(Event::command(WorkbenchCommand::TaskDetails, $task));

                return;
            }
        }
    }
}
