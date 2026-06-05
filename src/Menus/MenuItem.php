<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Key;

/**
 * A single menu entry (faithful to TMenuItem). Either a command item (command != 0)
 * or a submenu host ($subMenu != null). The name may contain a ~hotkey~ marker.
 */
final class MenuItem
{
    public function __construct(
        public string $name,
        public int $command,
        public ?Key $key = null,
        public string $help = '',
        public ?Menu $subMenu = null,
    ) {}
}
