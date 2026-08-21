<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Persistence;

use Closure;
use InvalidArgumentException;

/** A named, explicit factory for one persisted type. */
final readonly class StreamableClass
{
    /** @param Closure $factory */
    public function __construct(
        public string $type,
        public Closure $factory,
    ) {
        if (trim($type) === '') {
            throw new InvalidArgumentException('A streamable type must not be empty.');
        }
    }

    public static function forClass(string $type, string $class): self
    {
        if (! is_a($class, Streamable::class, true)) {
            throw new InvalidArgumentException("{$class} must implement " . Streamable::class . '.');
        }
        if ($class::streamType() !== $type) {
            throw new InvalidArgumentException("Registered type '{$type}' does not match {$class}::streamType().");
        }

        return new self($type, static function (array $data) use ($class): Streamable {
            $map = [];
            foreach ($data as $key => $value) {
                if (! is_string($key)) {
                    throw new PersistenceException('Streamable factory data keys must be strings.');
                }
                $map[$key] = $value;
            }

            return $class::fromStreamData($map);
        });
    }
}
