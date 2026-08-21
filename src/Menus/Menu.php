<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

/** A collection of MenuItems (faithful to TMenu) — what a MenuBar/MenuBox draws. */
final class Menu
{
    /** @param list<MenuItem> $items */
    public function __construct(private array $items = []) {}

    /** Build a Menu from top-level SubMenus, lowering each to a submenu MenuItem. */
    public static function of(SubMenu ...$subMenus): self
    {
        $items = [];
        foreach ($subMenus as $sub) {
            $items[] = new MenuItem(
                name: $sub->name,
                command: 0,
                key: $sub->key,
                help: '',
                subMenu: $sub->menu(),
                helpCtx: $sub->helpCtx,
            );
        }

        return new self($items);
    }

    public function add(MenuItem $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<MenuItem> */
    public function items(): array
    {
        return $this->items;
    }
}
