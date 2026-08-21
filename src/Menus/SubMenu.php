<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Events\Key;

/**
 * A named, hotkeyed pull-down (faithful to TSubMenu). Items are added fluently via
 * ->items(...); they accumulate into an internal Menu. The name may contain ~hotkey~.
 */
final class SubMenu
{
    private Menu $menu;

    public function __construct(
        public string $name,
        public ?Key $key = null,
        public int $helpCtx = 0,
    ) {
        $this->menu = new Menu();
    }

    /** Fluently append items (MenuItem or nested SubMenu). Returns $this. */
    public function items(MenuItem|SubMenu ...$items): static
    {
        foreach ($items as $item) {
            if ($item instanceof SubMenu) {
                $this->menu->add(new MenuItem(
                    name: $item->name,
                    command: 0,
                    key: $item->key,
                    help: '',
                    subMenu: $item->menu(),
                    helpCtx: $item->helpCtx,
                ));
            } else {
                $this->menu->add($item);
            }
        }

        return $this;
    }

    public function menu(): Menu
    {
        return $this->menu;
    }
}
