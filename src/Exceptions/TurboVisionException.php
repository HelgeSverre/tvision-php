<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Exceptions;

use RuntimeException;

/**
 * Base type for every exception thrown by the library.
 * Catch this to catch anything TurboVision-originated.
 */
class TurboVisionException extends RuntimeException
{
}
