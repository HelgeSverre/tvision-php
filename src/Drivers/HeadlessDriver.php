<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use HelgeSverre\TurboVision\Terminal\TerminalCapabilities;
use InvalidArgumentException;

/**
 * A no-I/O Driver for tests: scripted input queue, captured output, fixed/settable
 * size. The keystone that makes the entire render/input pipeline deterministic.
 */
final class HeadlessDriver implements Driver, ProvidesTerminalCapabilities
{
    private string $inputQueue = '';

    private string $captured = '';

    private bool $resized = false;

    private bool $initialised = false;

    public function __construct(
        private int $cols = 80,
        private int $rows = 24,
        private readonly TerminalCapabilities $capabilities = new TerminalCapabilities(synchronizedUpdates: true),
    ) {
        self::assertSize($this->cols, $this->rows);
    }

    public function init(): void
    {
        $this->initialised = true;
    }

    public function shutdown(): void
    {
        $this->initialised = false;
    }

    public function isInitialised(): bool
    {
        return $this->initialised;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        return [$this->cols, $this->rows];
    }

    public function write(string $bytes): void
    {
        $this->captured .= $bytes;
    }

    public function pollInput(int $timeoutMs): string
    {
        if ($timeoutMs < 0) {
            throw new InvalidArgumentException('The poll timeout must be non-negative.');
        }

        $out = $this->inputQueue;
        $this->inputQueue = '';

        return $out;
    }

    public function resized(): bool
    {
        $was = $this->resized;
        $this->resized = false;

        return $was;
    }

    /** Queue raw bytes to be returned by the next pollInput(). */
    public function feedInput(string $bytes): void
    {
        $this->inputQueue .= $bytes;
    }

    /** Peek at everything written so far without draining it. */
    public function output(): string
    {
        return $this->captured;
    }

    /** Return everything written so far and clear the capture buffer. */
    public function takeOutput(): string
    {
        $out = $this->captured;
        $this->captured = '';

        return $out;
    }

    /** Change the reported size and latch the resize flag. */
    public function resizeTo(int $cols, int $rows): void
    {
        self::assertSize($cols, $rows);
        $this->cols = $cols;
        $this->rows = $rows;
        $this->resized = true;
    }

    public function terminalCapabilities(): TerminalCapabilities
    {
        return $this->capabilities;
    }

    private static function assertSize(int $cols, int $rows): void
    {
        if ($cols < 0 || $rows < 0) {
            throw new InvalidArgumentException('Headless terminal dimensions must be non-negative.');
        }
    }
}
