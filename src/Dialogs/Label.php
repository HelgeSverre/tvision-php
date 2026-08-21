<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\View;

/** A mnemonic label that transfers focus to an associated control. */
final class Label extends StaticText
{
    private bool $light = false;

    public function __construct(Rect $bounds, private string $label, public ?View $link = null)
    {
        parent::__construct($bounds, $label);
        $this->options |= State::PreProcess | State::PostProcess;
        $this->eventMask |= EventMask::Broadcast;
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes("\x07\x08\x09\x0A");
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $normal = $this->mapColor($this->light ? 2 : 1);
        $shortcut = $this->mapColor($this->light ? 4 : 3);
        for ($y = 0; $y < $height; $y++) {
            $b = new DrawBuffer($width, $normal);
            if ($y === 0) {
                $b->moveCStr(0, $this->label, $normal, $shortcut);
            }
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }

    public function handleEvent(Event $event): void
    {
        $mouse = $event->asMouse();
        $focus = $event->what === EventType::MouseDown
            && $mouse !== null
            && $this->mouseInView($mouse->where);
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            $focus = $key !== null && Mnemonic::matches($this->label, $key);
        }
        if ($focus) {
            $this->focusLink();
            $this->clearEvent($event);
            return;
        }

        if ($event->what === EventType::Broadcast) {
            $info = $event->asMessage()?->info;
            if ($info === $this->link) {
                $this->light = $event->asMessage()?->command === \HelgeSverre\TurboVision\Events\Cmd::ReceivedFocus;
                $this->drawView();
            }
        }
    }

    private function focusLink(): void
    {
        if ($this->link !== null && $this->link->owner instanceof Group) {
            $this->link->owner->setCurrent($this->link);
        }
    }
}
