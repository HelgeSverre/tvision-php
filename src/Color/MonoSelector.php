<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use Closure;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** Chooses a classic monochrome video attribute. */
final class MonoSelector extends View
{
    private const string PALETTE = "\x07";

    /** @var list<array{0:string,1:int}> */
    private const array CHOICES = [
        ['Normal', 0x07],
        ['Highlight', 0x0F],
        ['Underline', 0x01],
        ['Inverse', 0x70],
    ];

    private readonly ?Closure $onChanged;

    public function __construct(Rect $bounds, int $value = 0x07, ?callable $onChanged = null)
    {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick;
        $this->eventMask |= EventMask::Broadcast;
        $this->onChanged = $onChanged === null ? null : Closure::fromCallable($onChanged);
        $this->value = $value & 0xFF;
    }

    public int $value;

    public function getPalette(): Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    /** @return list<array{0:string,1:int}> */
    public function choices(): array
    {
        return self::CHOICES;
    }

    public function mark(int $item): bool
    {
        return (self::CHOICES[$item][1] ?? null) === $this->value;
    }

    public function press(int $item): void
    {
        $value = self::CHOICES[$item][1] ?? null;
        if ($value === null || $value === $this->value) {
            return;
        }
        $this->value = $value;
        $this->drawView();
        $this->announce();
    }

    public function movedTo(int $item): void
    {
        $this->press($item);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        for ($row = 0; $row < $height; $row++) {
            $buffer = new DrawBuffer(max(1, $width));
            $buffer->moveChar(0, ' ', $this->mapColor(1), $width);
            if (isset(self::CHOICES[$row])) {
                [$label] = self::CHOICES[$row];
                $marker = $this->mark($row) ? '[x] ' : '[ ] ';
                $buffer->moveStr(0, TerminalText::slice($marker . $label, 0, $width), $this->mapColor(1));
            }
            $this->writeLine(0, $row, $width, 1, $buffer);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Broadcast && $event->isCommand(ColorCommand::Set)) {
            $value = $event->asMessage()?->info;
            if (! is_int($value)) {
                return;
            }
            $this->value = $value & 0xFF;
            $this->drawView();

            return;
        }

        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse === null || ! $this->mouseInView($mouse->where)) {
                return;
            }
            $this->press($this->makeLocal($mouse->where)->y);
            $this->clearEvent($event);

            return;
        }

        if ($event->what !== EventType::KeyDown) {
            return;
        }
        $current = array_find_key(self::CHOICES, fn (array $choice): bool => $choice[1] === $this->value) ?? 0;
        $next = match ($event->asKey()?->keyCode) {
            Key::Up->value, Key::Left->value => ($current + count(self::CHOICES) - 1) % count(self::CHOICES),
            Key::Down->value, Key::Right->value => ($current + 1) % count(self::CHOICES),
            default => null,
        };
        if ($next === null) {
            return;
        }
        $this->press($next);
        $this->clearEvent($event);
    }

    private function announce(): void
    {
        if ($this->onChanged !== null) {
            ($this->onChanged)($this->value);
        }
        $this->owner?->handleEvent(Event::broadcast(ColorCommand::ForegroundChanged, $this->value & 0x0F));
        $this->owner?->handleEvent(Event::broadcast(ColorCommand::BackgroundChanged, ($this->value >> 4) & 0x0F));
    }
}
