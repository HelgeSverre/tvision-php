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

    /**
     * Binary search for an equivalent item; returns its index or null. Equivalence
     * means the comparator returns 0 — the same rule insertion uses.
     *
     * @param T $item
     */
    public function search(mixed $item): ?int
    {
        $low = 0;
        $high = count($this->items);

        while ($low < $high) {
            $middle = $low + intdiv($high - $low, 2);
            $order = ($this->compare)($this->items[$middle], $item);
            if ($order === 0) {
                return $middle;
            }
            if ($order < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return null;
    }

    /**
     * Remove the first equivalent item; returns whether one was found.
     *
     * @param T $item
     */
    public function remove(mixed $item): bool
    {
        $index = $this->search($item);
        if ($index === null) {
            return false;
        }
        array_splice($this->items, $index, 1);

        return true;
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
