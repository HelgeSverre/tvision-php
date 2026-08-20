<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Exceptions;

/** Thrown when a terminal driver cannot complete an I/O or lifecycle operation. */
class DriverException extends TurboVisionException
{
    public static function notATty(): self
    {
        return new self('STDIN/STDOUT is not a TTY; a real terminal is required.');
    }

    public static function sttyUnavailable(): self
    {
        return new self('The "stty" command is unavailable; cannot enter raw mode.');
    }

    public static function writeFailed(?\Throwable $previous = null): self
    {
        return new self('Failed to write terminal output.', 0, $previous);
    }

    public static function readFailed(?\Throwable $previous = null): self
    {
        return new self('Failed to read terminal input.', 0, $previous);
    }

    public static function inputClosed(): InputClosedException
    {
        return new InputClosedException('Terminal input was closed.');
    }
}
