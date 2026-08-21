<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Collections;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Insertion-ordered directory tree rows.
 *
 * @implements IteratorAggregate<int, DirEntry>
 */
final class DirCollection implements Countable, IteratorAggregate
{
    /** @var list<DirEntry> */
    private array $items = [];

    public function insert(DirEntry $item): int
    {
        $this->items[] = $item;

        return array_key_last($this->items);
    }

    public function at(int $index): ?DirEntry
    {
        return $this->items[$index] ?? null;
    }

    /** @return list<DirEntry> */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, DirEntry> */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
