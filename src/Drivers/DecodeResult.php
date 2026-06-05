<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Events\Event;

/**
 * The outcome of one EscapeDecoder::decode() pass: every fully decoded Event, plus
 * the trailing bytes that form an incomplete sequence (the caller re-feeds these
 * prepended to the next chunk).
 */
final readonly class DecodeResult
{
    /** @param list<Event> $events */
    public function __construct(
        public array $events = [],
        public string $remainder = '',
    ) {}
}
