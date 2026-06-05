<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

/**
 * A no-I/O Driver for tests: scripted input queue, captured output, fixed/settable
 * size. The keystone that makes the entire render/input pipeline deterministic.
 */
final class HeadlessDriver implements Driver
{
    private string $inputQueue = '';

    private string $captured = '';

    private bool $resized = false;

    private bool $initialised = false;

    public function __construct(
        private int $cols = 80,
        private int $rows = 24,
    ) {}

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
        $this->cols = $cols;
        $this->rows = $rows;
        $this->resized = true;
    }
}
