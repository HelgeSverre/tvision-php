<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Key;

/**
 * One status-line entry (faithful to TStatusItem): a hint text (may be empty for a
 * key-only binding), the key that fires it, and the command it sends.
 */
final class StatusItem
{
    public function __construct(
        public string $text,
        public ?Key $key,
        public int $command,
    ) {}
}
