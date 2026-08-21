<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Collections\SearchRec;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;

/** InputLine that mirrors the currently focused FileList record. */
class FileInputLine extends InputLine
{
    public function __construct(Rect $bounds, int $maxLen)
    {
        parent::__construct($bounds, $maxLen);
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what !== EventType::Broadcast || ! $event->isCommand(FileCommand::Focused)
            || $this->getState(State::Selected)) {
            return;
        }

        $entry = $event->asMessage()?->info;
        if (! $entry instanceof SearchRec) {
            return;
        }
        $value = $entry->name;
        if ($entry->isDirectory()) {
            $pattern = $this->owner instanceof FileDialog ? $this->owner->wildCard : '*';
            $value = FilePath::join($entry->name, $pattern);
        }
        $this->setText($value);
    }
}
