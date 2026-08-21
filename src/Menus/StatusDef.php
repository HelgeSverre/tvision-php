<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

/**
 * A help-context-ranged set of StatusItems (faithful to TStatusDef). Items are added
 * fluently via ->items(...). The status line picks the definition whose [min,max]
 * contains the current help context.
 */
final class StatusDef
{
    /** @var list<StatusItem> */
    private array $statusItems = [];

    public function __construct(
        public int $min,
        public int $max,
    ) {}

    public static function all(StatusItem ...$items): self
    {
        return new self(0, 0xFFFF)->items(...$items);
    }

    public function items(StatusItem ...$newItems): static
    {
        foreach ($newItems as $item) {
            $this->statusItems[] = $item;
        }

        return $this;
    }

    /** @return list<StatusItem> */
    public function getItems(): array
    {
        return $this->statusItems;
    }
}
