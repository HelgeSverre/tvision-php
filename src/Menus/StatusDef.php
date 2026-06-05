<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

/**
 * A help-context-ranged set of StatusItems (faithful to TStatusDef). Items are added
 * fluently via ->items(...). The status line picks the def whose [min,max] contains
 * the current help context (M1 uses a single full-range def).
 */
final class StatusDef
{
    /** @var list<StatusItem> */
    private array $statusItems = [];

    public function __construct(
        public int $min,
        public int $max,
    ) {}

    /**
     * Fluent appender: adds StatusItems and returns $this for chaining.
     * Use getItems() to retrieve the accumulated list.
     */
    public function items(StatusItem ...$newItems): static
    {
        foreach ($newItems as $item) {
            $this->statusItems[] = $item;
        }

        return $this;
    }

    /**
     * Returns all accumulated StatusItems.
     *
     * @return list<StatusItem>
     */
    public function getItems(): array
    {
        return $this->statusItems;
    }
}
