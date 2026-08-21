<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Resources;

use ArrayAccess;
use Countable;
use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\Streamable;
use HelgeSverre\TurboVision\Persistence\StreamableType;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Traversable;

/**
 * Immutable indexed strings, analogous to Turbo Vision's TStringList.
 *
 * @implements ArrayAccess<int, string>
 * @implements IteratorAggregate<int, string>
 */
final readonly class StringList implements ArrayAccess, Countable, IteratorAggregate, Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'tvision.string-list';

    /** @var list<string> */
    private array $strings;

    /** @param iterable<mixed> $strings */
    public function __construct(iterable $strings = [])
    {
        $values = [];
        foreach ($strings as $string) {
            if (! is_string($string)) {
                throw new InvalidArgumentException('StringList may contain only strings.');
            }
            $values[] = $string;
        }
        $this->strings = $values;
    }

    public function count(): int
    {
        return count($this->strings);
    }

    public function get(int $index): string
    {
        if (! array_key_exists($index, $this->strings)) {
            throw new OutOfBoundsException("String list index {$index} is out of bounds.");
        }

        return $this->strings[$index];
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->strings;
    }

    public function contains(string $needle): bool
    {
        return in_array($needle, $this->strings, true);
    }

    /** @return Traversable<int, string> */
    public function getIterator(): Traversable
    {
        yield from $this->strings;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->strings);
    }

    public function offsetGet(mixed $offset): string
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException('StringList is immutable; use StringListMaker to create a new list.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException('StringList is immutable; use StringListMaker to create a new list.');
    }

    /** @return array{strings:list<string>} */
    public function streamData(): array
    {
        return ['strings' => $this->strings];
    }

    /** @param array<string, mixed> $data */
    public static function fromStreamData(array $data): static
    {
        if (count($data) !== 1 || ! array_key_exists('strings', $data) || ! is_array($data['strings']) || ! array_is_list($data['strings'])) {
            throw new PersistenceException('A persisted string list must contain one strings array.');
        }
        foreach ($data['strings'] as $string) {
            if (! is_string($string)) {
                throw new PersistenceException('A persisted string list may contain only strings.');
            }
        }

        /** @var list<string> $strings */
        $strings = $data['strings'];

        return new self($strings);
    }
}
