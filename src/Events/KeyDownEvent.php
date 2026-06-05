<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/** Immutable key-press payload. $char is the printable grapheme, '' for special keys. */
final readonly class KeyDownEvent
{
    public function __construct(
        public int $keyCode,
        public string $char = '',
        public int $modifiers = 0,
    ) {}

    public function is(Key $key): bool
    {
        return $this->keyCode === $key->value;
    }
}
