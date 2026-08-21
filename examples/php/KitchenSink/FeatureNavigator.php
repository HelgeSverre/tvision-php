<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\KitchenSink;

use HelgeSverre\TurboVision\Dialogs\ListBox;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\State;

/** A launchable ListBox: Space, Enter, or a double-click opens the selected lab. */
final class FeatureNavigator extends ListBox
{
    /** @param list<int> $commands */
    public function __construct(Rect $bounds, ?ScrollBar $scrollBar, private readonly array $commands)
    {
        parent::__construct($bounds, 1, $scrollBar);
        $this->growMode = State::GrowHiX | State::GrowHiY;
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->isKey(Key::Enter) && $this->focused < count($this->commands)) {
            $this->selectItem($this->focused);
            $this->clearEvent($event);
        }
    }

    /** Remap ListViewer roles into the compact Window palette used by this launcher. */
    public function getPalette(): Palette
    {
        return Palette::fromBytes("\x02\x01\x04\x03\x05");
    }

    public function selectItem(int $item): void
    {
        $command = $this->commands[$item] ?? null;
        if ($command !== null && $this->owner instanceof Group) {
            $this->owner->putEvent(Event::command($command, $this));
        }
    }
}
