<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/**
 * A mutable tagged event passed down the view tree. A handler consumes it with
 * clear() (what -> Nothing), which stops further propagation — the faithful
 * Turbo Vision event-consumption pattern.
 */
final class Event
{
    public function __construct(
        public EventType $what = EventType::Nothing,
        public KeyDownEvent|MouseEvent|MessageEvent|null $payload = null,
    ) {}

    public static function nothing(): self
    {
        return new self();
    }

    public static function keyDown(KeyDownEvent $key): self
    {
        return new self(EventType::KeyDown, $key);
    }

    public static function key(Key $key, int $modifiers = 0): self
    {
        return self::keyDown(new KeyDownEvent($key->value, modifiers: $modifiers));
    }

    public static function mouse(EventType $what, MouseEvent $mouse): self
    {
        return new self($what, $mouse);
    }

    public static function command(int $command, mixed $info = null): self
    {
        return new self(EventType::Command, new MessageEvent($command, $info));
    }

    public static function broadcast(int $command, mixed $info = null): self
    {
        return new self(EventType::Broadcast, new MessageEvent($command, $info));
    }

    public function clear(): void
    {
        $this->what = EventType::Nothing;
        $this->payload = null;
    }

    public function isNothing(): bool
    {
        return $this->what === EventType::Nothing;
    }

    public function asKey(): ?KeyDownEvent
    {
        return $this->payload instanceof KeyDownEvent ? $this->payload : null;
    }

    public function asMouse(): ?MouseEvent
    {
        return $this->payload instanceof MouseEvent ? $this->payload : null;
    }

    public function asMessage(): ?MessageEvent
    {
        return $this->payload instanceof MessageEvent ? $this->payload : null;
    }

    public function isKey(Key $key): bool
    {
        return $this->what === EventType::KeyDown
            && $this->asKey()?->is($key) === true;
    }

    public function isCommand(int $command): bool
    {
        return ($this->what === EventType::Command || $this->what === EventType::Broadcast)
            && $this->asMessage()?->command === $command;
    }
}
