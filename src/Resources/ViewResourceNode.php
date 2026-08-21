<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Resources;

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Persistence\PersistenceException;

/** One safe, declarative node in a ViewResource tree. */
final readonly class ViewResourceNode
{
    /**
     * @param array<string, mixed> $properties Constructor inputs understood by the registered factory.
     * @param list<ViewResourceNode> $children
     */
    public function __construct(
        public string $type,
        public Rect $bounds,
        public array $properties = [],
        public array $children = [],
    ) {
        if (trim($type) === '' || strlen($type) > 128) {
            throw new \InvalidArgumentException('A view resource node type must be a non-empty string of at most 128 bytes.');
        }
    }

    public function property(string $name, mixed $default = null): mixed
    {
        return $this->properties[$name] ?? $default;
    }

    public function string(string $name): string
    {
        $value = $this->property($name);
        if (! is_string($value)) {
            throw new ResourceException("View resource property '{$name}' for '{$this->type}' must be a string.");
        }

        return $value;
    }

    public function integer(string $name): int
    {
        $value = $this->property($name);
        if (! is_int($value)) {
            throw new ResourceException("View resource property '{$name}' for '{$this->type}' must be an integer.");
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'bounds' => [
                'left' => $this->bounds->a->x,
                'top' => $this->bounds->a->y,
                'right' => $this->bounds->b->x,
                'bottom' => $this->bounds->b->y,
            ],
            'properties' => $this->properties,
            'children' => array_map(static fn (self $child): array => $child->toArray(), $this->children),
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data, int $depth = 0): self
    {
        if ($depth > 64) {
            throw new PersistenceException('A view resource exceeds the 64-level node nesting limit.');
        }
        if (count($data) !== 4
            || ! array_key_exists('type', $data)
            || ! array_key_exists('bounds', $data)
            || ! array_key_exists('properties', $data)
            || ! array_key_exists('children', $data)
            || ! is_string($data['type'])
            || ! is_array($data['bounds'])
            || ! is_array($data['properties'])
            || ! is_array($data['children'])
            || ($data['properties'] !== [] && array_is_list($data['properties']))
            || ! array_is_list($data['children'])
        ) {
            throw new PersistenceException('A view resource node has an invalid schema.');
        }

        $bounds = self::boundsFromArray($data['bounds']);
        $properties = self::propertiesFromArray($data['properties']);
        $children = [];
        foreach ($data['children'] as $child) {
            if (! is_array($child)) {
                throw new PersistenceException('A view resource child must be an object.');
            }
            $children[] = self::fromArray($child, $depth + 1);
        }

        try {
            return new self($data['type'], $bounds, $properties, $children);
        } catch (\InvalidArgumentException $exception) {
            throw new PersistenceException('A view resource node is invalid.', 0, $exception);
        }
    }

    /** @param array<mixed> $bounds */
    private static function boundsFromArray(array $bounds): Rect
    {
        if (count($bounds) !== 4
            || ! array_key_exists('left', $bounds)
            || ! array_key_exists('top', $bounds)
            || ! array_key_exists('right', $bounds)
            || ! array_key_exists('bottom', $bounds)
            || ! is_int($bounds['left'])
            || ! is_int($bounds['top'])
            || ! is_int($bounds['right'])
            || ! is_int($bounds['bottom'])
        ) {
            throw new PersistenceException('View resource bounds must contain integer left, top, right, and bottom values.');
        }

        return Rect::of($bounds['left'], $bounds['top'], $bounds['right'], $bounds['bottom']);
    }

    /**
     * @param array<mixed> $properties
     * @return array<string, mixed>
     */
    private static function propertiesFromArray(array $properties): array
    {
        $result = [];
        foreach ($properties as $key => $value) {
            if (! is_string($key)) {
                throw new PersistenceException('View resource property names must be strings.');
            }
            self::assertDeclarativeValue($value, 0);
            $result[$key] = $value;
        }

        return $result;
    }

    private static function assertDeclarativeValue(mixed $value, int $depth): void
    {
        if ($depth > 32) {
            throw new PersistenceException('View resource property data exceeds the 32-level nesting limit.');
        }
        if (is_null($value) || is_scalar($value)) {
            if (is_float($value) && ! is_finite($value)) {
                throw new PersistenceException('View resource properties cannot contain non-finite floats.');
            }

            return;
        }
        if (! is_array($value)) {
            throw new PersistenceException('View resource properties may only contain JSON-compatible values.');
        }
        foreach ($value as $key => $item) {
            self::assertDeclarativeValue($item, $depth + 1);
        }
    }
}
