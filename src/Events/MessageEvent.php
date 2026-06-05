<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/** Immutable command/broadcast payload: a command code plus optional info. */
final readonly class MessageEvent
{
    public function __construct(
        public int $command,
        public mixed $info = null,
    ) {}
}
