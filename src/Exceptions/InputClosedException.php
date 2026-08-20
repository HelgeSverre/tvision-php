<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Exceptions;

/** Signals a normal end-of-input after a terminal or PTY disconnects. */
final class InputClosedException extends DriverException
{
}
