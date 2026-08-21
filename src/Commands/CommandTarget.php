<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Commands;

/**
 * A command-state owner that can be changed one command at a time.
 *
 * Program implements this contract so a CommandSet can apply a coherent batch
 * without knowing anything about the application's event loop.
 */
interface CommandTarget
{
    public function enableCommand(int $command): void;

    public function disableCommand(int $command): void;

    public function commandEnabled(int $command): bool;
}
