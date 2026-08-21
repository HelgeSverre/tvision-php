<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use InvalidArgumentException;

/** A named collection of palette entries, remembering its selected item. */
final class ColorGroup
{
    /** @var list<ColorItem> */
    private array $items;

    public int $selectedIndex = 0;

    /** @param iterable<mixed> $items */
    public function __construct(
        public readonly string $name,
        iterable $items = [],
    ) {
        $source = array_values(is_array($items) ? $items : iterator_to_array($items, false));
        $validated = [];
        foreach ($source as $item) {
            if (! $item instanceof ColorItem) {
                throw new InvalidArgumentException('A color group may only contain ColorItem instances.');
            }
            $validated[] = $item;
        }
        $this->items = $validated;
        $this->selectedIndex = $this->clampSelection($this->selectedIndex);
    }

    /** @return list<ColorItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function item(int $index): ?ColorItem
    {
        return $this->items[$index] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function select(int $index): void
    {
        $this->selectedIndex = $this->clampSelection($index);
    }

    private function clampSelection(int $index): int
    {
        if ($this->items === []) {
            return 0;
        }

        return min(max(0, $index), count($this->items) - 1);
    }
}
