<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Views\View;

/**
 * Abstract base for menu views (faithful to TMenuView). Carries the menu palette
 * cpMenuView "\x02\x03\x04\x05\x06\x07" and shares the getColor word lookup used by
 * MenuBar::draw() / StatusLine::draw().
 */
abstract class MenuView extends View
{
    /** cpMenuView: indexes 1..6 -> attribute indexes 0x02..0x07 in the app palette. */
    public function getPalette(): ?Palette
    {
        return Palette::fromBytes("\x02\x03\x04\x05\x06\x07");
    }

    /** Whether a menu entry is actionable: separators never, submenus only with items, commands via the enable chain. */
    public function itemEnabled(MenuItem $item): bool
    {
        if ($item->name === '') {
            return false;
        }

        if ($item->subMenu !== null) {
            return $item->subMenu->items() !== [];
        }

        return $item->command !== 0 && $this->commandEnabled($item->command);
    }
}
