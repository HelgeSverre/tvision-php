<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Persistence;

use InvalidArgumentException;

/** Allow-list of constructors accepted by StreamCodec. */
final class StreamableRegistry
{
    /** @var array<string, StreamableClass> */
    private array $classes = [];

    public function register(StreamableClass $class): self
    {
        if (isset($this->classes[$class->type])) {
            throw new InvalidArgumentException("A streamable factory is already registered for '{$class->type}'.");
        }

        $this->classes[$class->type] = $class;

        return $this;
    }

    public function registerClass(string $type, string $class): self
    {
        return $this->register(StreamableClass::forClass($type, $class));
    }

    public function has(string $type): bool
    {
        return isset($this->classes[$type]);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->classes);
    }

    /** @param array<string, mixed> $data */
    public function create(string $type, array $data): Streamable
    {
        $class = $this->classes[$type] ?? null;
        if ($class === null) {
            throw new PersistenceException("Persisted type '{$type}' is not registered.");
        }

        try {
            $object = ($class->factory)($data);
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException("Could not construct persisted type '{$type}'.", 0, $exception);
        }
        if (! $object instanceof Streamable || $object::streamType() !== $type) {
            throw new PersistenceException("Factory for '{$type}' returned an incompatible object.");
        }

        return $object;
    }
}
