<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Collections;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Small, idiomatic replacement for Turbo Vision's TSortedCollection.
 *
 * Items are kept in comparator order. The comparator follows usort semantics
 * and must return a negative value when its first argument belongs first.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
class SortedCollection implements Countable, IteratorAggregate
{
    /** @var list<T> */
    protected array $items = [];

    /** @param callable(T, T): int $compare */
    public function __construct(protected readonly mixed $compare)
    {
    }

    /** @return list<T> */
    public function all(): array
    {
        return $this->items;
    }

    /** @return T|null */
    public function at(int $index): mixed
    {
        return $this->items[$index] ?? null;
    }

    /** @param T $item */
    public function insert(mixed $item): int
    {
        $low = 0;
        $high = count($this->items);

        while ($low < $high) {
            $middle = $low + intdiv($high - $low, 2);
            if (($this->compare)($this->items[$middle], $item) <= 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        array_splice($this->items, $low, 0, [$item]);

        return $low;
    }

    /** @param iterable<T> $items */
    public function replace(iterable $items): void
    {
        $this->items = [];
        foreach ($items as $item) {
            $this->insert($item);
        }
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
