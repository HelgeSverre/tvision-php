<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Terminal\TerminalCapabilities;

/** Optional Driver extension; Screen remains compatible with legacy custom drivers. */
interface ProvidesTerminalCapabilities
{
    public function terminalCapabilities(): TerminalCapabilities;
}
