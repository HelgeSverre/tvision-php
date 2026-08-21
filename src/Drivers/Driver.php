<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Exceptions\DriverException;
use HelgeSverre\TurboVision\Exceptions\InputClosedException;

/**
 * The sole boundary to a real terminal. Implementations: AnsiDriver (real TTY) and
 * HeadlessDriver (scripted, for tests). Everything above this interface is pure.
 */
interface Driver
{
    /**
     * Enter alt screen, raw mode, hide cursor, enable mouse. Safe to call
     * repeatedly; implementations treat subsequent calls as no-ops.
     *
     * @throws \HelgeSverre\TurboVision\Exceptions\DriverException when the terminal cannot be claimed (not a TTY, stty unavailable)
     */
    public function init(): void;

    /** Restore every terminal mutation made by init(). MUST be idempotent. */
    public function shutdown(): void;

    /**
     * Current terminal size.
     *
     * @return array{0:int,1:int} [cols, rows]
     */
    public function size(): array;

    /** Write raw bytes to the terminal. */
    public function write(string $bytes): void;

    /**
     * Raw input bytes available within $timeoutMs; '' if none arrived. Negative
     * timeouts are rejected.
     *
     * @throws InputClosedException when the underlying terminal input permanently closes
     * @throws DriverException when the wait or read fails for any other reason
     * @throws \InvalidArgumentException when $timeoutMs is negative
     */
    public function pollInput(int $timeoutMs): string;

    /** True once since the last call if the terminal was resized (clears the flag). */
    public function resized(): bool;
}
