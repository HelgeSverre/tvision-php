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

    /**
     * Lower this submenu into the MenuItem form its parent menu stores, keeping
     * name, hotkey, and help context. Shared by Menu::of(), MenuBar::buildMenu(),
     * and items() so the lowering exists exactly once.
     */
    public function toMenuItem(): MenuItem
    {
        return new MenuItem(
            name: $this->name,
            command: 0,
            key: $this->key,
            help: '',
            subMenu: $this->menu(),
            helpCtx: $this->helpCtx,
        );
    }

    /** Fluently append items (MenuItem or nested SubMenu). Returns $this. */
    public function items(MenuItem|SubMenu ...$items): static
    {
        foreach ($items as $item) {
            if ($item instanceof SubMenu) {
                $this->menu->add($item->toMenuItem());
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
