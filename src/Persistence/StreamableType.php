<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Persistence;

/**
 * Convenience for Streamable classes declaring a stable STREAM_TYPE constant.
 *
 * The type is still inert until an application registers the class with a
 * StreamableRegistry. This trait deliberately does not derive a type from a
 * PHP class name, so moving or renaming a class does not corrupt saved data.
 */
trait StreamableType
{
    public static function streamType(): string
    {
        return static::STREAM_TYPE;
    }
}
