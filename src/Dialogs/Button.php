<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\View;

/** Push button with default-button and mnemonic support (Turbo Vision's TButton). */
class Button extends View
{
    public const int Normal = ButtonFlag::Normal;
    public const int Default = ButtonFlag::Default;
    public const int LeftJust = ButtonFlag::LeftJust;
    public const int Broadcast = ButtonFlag::Broadcast;
    public const int GrabFocus = ButtonFlag::GrabFocus;

    public bool $amDefault;

    public function __construct(
        Rect $bounds,
        public string $title,
        public int $command,
        public int $flags = ButtonFlag::Normal,
    ) {
        parent::__construct($bounds);
        $this->amDefault = ($flags & ButtonFlag::Default) !== 0;
        $this->options |= State::Selectable | State::FirstClick | State::PreProcess | State::PostProcess;
        $this->eventMask |= EventMask::Broadcast;
        if (! $this->commandEnabled($command)) {
            $this->state |= State::Disabled;
        }
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes("\x0A\x0B\x0C\x0D\x0E\x0E\x0E\x0F");
    }

    public function draw(): void
    {
        $this->drawState(false);
    }

    public function drawState(bool $down): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $textAttr = $this->getState(State::Disabled) ? $this->mapColor(4)
            : ($this->getState(State::Selected) ? $this->mapColor(3)
                : ($this->amDefault ? $this->mapColor(2) : $this->mapColor(1)));
        $shortcutAttr = $this->mapColor($this->getState(State::Selected) ? 7 : ($this->amDefault ? 6 : 5));
        $shadowAttr = $this->mapColor(8);
        $contentHeight = max(0, $height - 1);
        $titleRow = intdiv(max(0, $contentHeight - 1), 2);

        for ($y = 0; $y < $contentHeight; $y++) {
            $b = new DrawBuffer($width);
            $b->moveChar(0, ' ', $textAttr, $width);
            if (! $down) {
                $b->putAttribute($width - 1, $shadowAttr);
            }
            if ($y === $titleRow) {
                $this->drawTitle($b, $textAttr, $shortcutAttr, $down);
            }
            $this->writeLine(0, $y, $width, 1, $b);
        }
        $bottom = new DrawBuffer($width);
        $bottom->moveChar(0, ' ', $shadowAttr, $width);
        $this->writeLine(0, $height - 1, $width, 1, $bottom);
    }

    private function drawTitle(DrawBuffer $buffer, int $normal, int $shortcut, bool $down): void
    {
        $width = $this->bounds->width();
        $visible = preg_replace('/~/u', '', $this->title) ?? $this->title;
        $length = TerminalText::length($visible);
        $x = ($this->flags & ButtonFlag::LeftJust) !== 0 ? 1 : max(1, intdiv(max(0, $width - $length), 2));
        if ($down) {
            $x = min($width - 1, $x + 1);
        }
        $buffer->moveCStr($x, $this->title, $normal, $shortcut);
    }

    public function handleEvent(Event $event): void
    {
        if ($this->getState(State::Disabled)) {
            return;
        }

        $mouse = $event->asMouse();
        if ($event->what === EventType::MouseDown && $mouse !== null && $this->mouseInView($mouse->where)) {
            if (($this->flags & ButtonFlag::GrabFocus) !== 0 && $this->owner instanceof \HelgeSverre\TurboVision\Views\Group) {
                $this->owner->setCurrent($this);
            }
            $this->drawState(true);
            $this->press();
            $this->drawState(false);
            $this->clearEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if (($this->getState(State::Focused) && $key?->char === ' ')
                || ($key !== null && Mnemonic::matches($this->title, $key))
            ) {
                $this->press();
                $this->clearEvent($event);
            }

            return;
        }

        if ($event->what !== EventType::Broadcast) {
            return;
        }
        $command = $event->asMessage()?->command;
        if ($command === Cmd::Default && $this->amDefault) {
            $this->press();
            $this->clearEvent($event);
        } elseif (defined(Cmd::class . '::GrabDefault') && $command === Cmd::GrabDefault && ($this->flags & ButtonFlag::Default) !== 0) {
            $this->amDefault = false;
            $this->drawView();
        } elseif (defined(Cmd::class . '::ReleaseDefault') && $command === Cmd::ReleaseDefault && ($this->flags & ButtonFlag::Default) !== 0) {
            $this->amDefault = true;
            $this->drawView();
        } elseif ($command === Cmd::CommandSetChanged) {
            $this->setState(State::Disabled, ! $this->commandEnabled($this->command));
            $this->drawView();
        }
    }

    public function makeDefault(bool $enable): void
    {
        if (($this->flags & ButtonFlag::Default) !== 0 || $this->amDefault === $enable) {
            return;
        }
        $this->amDefault = $enable;
        $this->owner?->handleEvent(Event::broadcast($enable ? Cmd::GrabDefault : Cmd::ReleaseDefault, $this));
        $this->drawView();
    }

    public function press(): void
    {
        if (($this->flags & ButtonFlag::Broadcast) !== 0) {
            $this->owner?->handleEvent(Event::broadcast($this->command, $this));

            return;
        }
        if ($this->owner instanceof Group) {
            $this->owner->putEvent(Event::command($this->command, $this));
        }
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & State::Focused) !== 0) {
            $this->makeDefault($enable);
        }
        if (($flag & (State::Selected | State::Active | State::Disabled)) !== 0) {
            $this->drawView();
        }
    }

}
