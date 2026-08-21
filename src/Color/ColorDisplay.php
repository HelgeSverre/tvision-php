<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use Closure;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\View;

/** Preview text using the selected palette attribute. */
final class ColorDisplay extends View
{
    private readonly ?Closure $onChanged;

    public function __construct(
        Rect $bounds,
        public string $text = 'Text ',
        int $color = 0x07,
        ?callable $onChanged = null,
    ) {
        parent::__construct($bounds);
        $this->eventMask |= EventMask::Broadcast;
        $this->color = $color & 0xFF;
        $this->onChanged = $onChanged === null ? null : Closure::fromCallable($onChanged);
    }

    public int $color;

    public function setColor(int $color, bool $announce = true): void
    {
        $this->color = $color & 0xFF;
        if ($this->onChanged !== null) {
            ($this->onChanged)($this->color);
        }
        if ($announce) {
            $this->owner?->handleEvent(Event::broadcast(ColorCommand::Set, $this->color));
        }
        $this->drawView();
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $attribute = $this->color === 0 ? 0x4F : $this->color;
        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, ' ', $attribute, $width);
        $offset = 0;
        while ($offset < $width) {
            $buffer->moveStr($offset, $this->text, $attribute);
            $offset += max(1, \HelgeSverre\TurboVision\Drawing\TerminalText::length($this->text));
        }
        $this->writeLine(0, 0, $width, $height, $buffer);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what !== EventType::Broadcast) {
            return;
        }
        if ($event->isCommand(ColorCommand::ForegroundChanged)) {
            $value = $event->asMessage()?->info;
            if (! is_int($value)) {
                return;
            }
            $this->setColor(($this->color & 0xF0) | ($value & 0x0F), false);
        } elseif ($event->isCommand(ColorCommand::BackgroundChanged)) {
            $value = $event->asMessage()?->info;
            if (! is_int($value)) {
                return;
            }
            $this->setColor(($this->color & 0x0F) | (($value & 0x0F) << 4), false);
        }
    }
}
