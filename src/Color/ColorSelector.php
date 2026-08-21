<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use Closure;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** Selects one of the sixteen foreground or eight background attribute colours. */
final class ColorSelector extends View
{
    private const string PALETTE = "\x07";

    private readonly ?Closure $onChanged;

    public function __construct(
        Rect $bounds,
        public readonly ColorSelectorType $type,
        int $color = 0,
        ?callable $onChanged = null,
    ) {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick | State::Framed;
        $this->eventMask |= EventMask::Broadcast;
        $this->onChanged = $onChanged === null ? null : Closure::fromCallable($onChanged);
        $this->color = $this->normalize($color);
    }

    public int $color;

    public function getPalette(): Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function setColor(int $color, bool $announce = false): void
    {
        $color = $this->normalize($color);
        if ($this->color === $color) {
            return;
        }

        $this->color = $color;
        $this->drawView();
        if ($announce) {
            $this->announce();
        }
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $normal = $this->mapColor(1);
        for ($row = 0; $row < $height; $row++) {
            $buffer = new DrawBuffer(max(1, $width));
            $buffer->moveChar(0, ' ', $normal, $width);
            for ($column = 0; $column < 4; $column++) {
                $value = $row * 4 + $column;
                if ($value > $this->type->maximum()) {
                    break;
                }
                $x = $column * 3;
                if ($x >= $width) {
                    break;
                }
                $attribute = $value;
                if ($value === 0) {
                    $attribute = 0x70;
                }
                $buffer->moveChar($x, '•', $attribute, min(3, $width - $x));
                if ($value === $this->color && $x + 1 < $width) {
                    $buffer->moveChar($x + 1, '◀', $attribute, 1);
                    if ($value === 0) {
                        $buffer->putAttribute($x + 1, 0x70);
                    }
                }
            }
            $this->writeLine(0, $row, $width, 1, $buffer);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Broadcast && $event->isCommand(ColorCommand::Set)) {
            $attribute = $event->asMessage()?->info;
            if (! is_int($attribute)) {
                return;
            }
            $this->setColor($this->type === ColorSelectorType::Foreground ? $attribute & 0x0F : ($attribute >> 4) & 0x0F);

            return;
        }

        $old = $this->color;
        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse === null || ! $this->mouseInView($mouse->where)) {
                return;
            }
            $local = $this->makeLocal($mouse->where);
            $candidate = $local->y * 4 + intdiv($local->x, 3);
            if ($candidate <= $this->type->maximum()) {
                $this->color = $candidate;
            }
        } elseif ($event->what === EventType::KeyDown) {
            $key = $event->asKey()?->keyCode;
            $maximum = $this->type->maximum();
            $this->color = match ($key) {
                Key::Left->value => $this->color > 0 ? $this->color - 1 : $maximum,
                Key::Right->value => $this->color < $maximum ? $this->color + 1 : 0,
                Key::Up->value => $this->color > 3 ? $this->color - 4 : ($this->color === 0 ? $maximum : $this->color + $maximum - 3),
                Key::Down->value => $this->color < $maximum - 3 ? $this->color + 4 : ($this->color === $maximum ? 0 : $this->color - ($maximum - 3)),
                default => $this->color,
            };
            if ($old === $this->color) {
                return;
            }
        } else {
            return;
        }

        if ($old !== $this->color) {
            $this->drawView();
            $this->announce();
        }
        $this->clearEvent($event);
    }

    private function announce(): void
    {
        if ($this->onChanged !== null) {
            ($this->onChanged)($this->color);
        }
        $this->owner?->handleEvent(Event::broadcast($this->type->changedCommand(), $this->color));
    }

    private function normalize(int $color): int
    {
        return min(max(0, $color), $this->type->maximum());
    }
}
