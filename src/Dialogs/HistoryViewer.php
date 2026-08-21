<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Displays one shared History list. */
final class HistoryViewer extends ListViewer
{
    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar,
        ?ScrollBar $vScrollBar,
        public int $historyId,
    ) {
        parent::__construct($bounds, 1, $hScrollBar, $vScrollBar);
        $this->setRange(count(History::items($historyId)));
    }

    public function getText(int $item, int $maxLen): string
    {
        return TerminalText::slice(History::items($this->historyId)[$item] ?? '', 0, $maxLen);
    }

    public function historyWidth(): int
    {
        return max([0, ...array_map(static fn (string $item): int => TerminalText::length($item), History::items($this->historyId))]);
    }

    public function selection(): string
    {
        return History::items($this->historyId)[$this->focused] ?? '';
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what === EventType::KeyDown && $event->isKey(Key::Enter)) {
            $this->owner?->endModal(Cmd::Ok);
            $this->clearEvent($event);
        } elseif ($event->what === EventType::KeyDown && $event->isKey(Key::Esc)) {
            $this->owner?->endModal(Cmd::Cancel);
            $this->clearEvent($event);
        } elseif ($event->what === EventType::Command && $event->isCommand(Cmd::Cancel)) {
            $this->owner?->endModal(Cmd::Cancel);
            $this->clearEvent($event);
        }
    }
}
