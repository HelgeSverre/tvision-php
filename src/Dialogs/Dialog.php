<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

/** A non-growable movable/closable modal window (Turbo Vision's TDialog). */
class Dialog extends Window
{
    public function __construct(Rect $bounds, string $title = '')
    {
        parent::__construct($bounds, $title);
        $this->growMode = 0;
        $this->flags = WindowFlags::Move | WindowFlags::Close;
        $this->setPalette(WindowPalette::Gray);
    }

    public function getPalette(): ?Palette
    {
        // The reference uses the 32-entry dialog palette. The window palette is its
        // shared frame prefix and lets contained controls provide their own entries.
        return Palette::fromBytes("\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2A\x2B\x2C\x2D\x2E\x2F\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3A\x3B\x3C\x3D\x3E\x3F");
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->what === EventType::KeyDown) {
            if ($event->isKey(Key::Esc)) {
                $this->putEvent(Event::command(Cmd::Cancel));
                $this->clearEvent($event);
            } elseif ($event->isKey(Key::Enter)) {
                $this->putEvent(Event::broadcast(Cmd::Default));
                $this->clearEvent($event);
            }

            return;
        }

        if ($event->what !== EventType::Command) {
            return;
        }
        $command = $event->asMessage()?->command;
        if ($command !== null && in_array($command, [Cmd::Ok, Cmd::Cancel, Cmd::Yes, Cmd::No], true)
            && $this->getState(State::Modal)
        ) {
            // Group::execView owns the end-state and its single validation pass.
            $this->owner?->endModal($command);
            $this->clearEvent($event);
        }
    }

    /** Cancellation deliberately bypasses control validation. */
    public function valid(int $command): bool
    {
        if ($command === Cmd::Cancel) {
            return true;
        }

        foreach ($this->subviews() as $view) {
            if ($view->valid($command) === false) {
                return false;
            }
        }

        return true;
    }
}
