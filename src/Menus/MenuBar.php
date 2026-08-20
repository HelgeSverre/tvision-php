<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;

/**
 * The top menu bar (faithful to TMenuBar::draw). Renders top-level items as
 * ' ' + ~hotkey~ name + ' ' starting at column 1.
 *
 * Supports keyboard- and mouse-driven pull-downs. Alt-hotkeys open a menu, F10
 * opens the first menu, arrows traverse menus/items, Enter activates, and Esc closes.
 */
final class MenuBar extends MenuView
{
    private Menu $menu;

    private int $activeIndex = -1;

    private int $selectedIndex = 0;

    private ?MenuPopup $popup = null;

    public function __construct(Rect $bounds, SubMenu|Menu ...$menus)
    {
        parent::__construct($bounds);
        $this->options |= State::PreProcess;
        $this->growMode = State::GrowHiX;
        $this->menu = $this->buildMenu(array_values($menus));
    }

    /** @param list<SubMenu|Menu> $menus */
    private function buildMenu(array $menus): Menu
    {
        $merged = new Menu();
        foreach ($menus as $m) {
            if ($m instanceof Menu) {
                foreach ($m->items() as $item) {
                    $merged->add($item);
                }
            } else {
                $merged->add(new MenuItem(
                    name: $m->name,
                    command: 0,
                    key: $m->key,
                    help: '',
                    subMenu: $m->menu(),
                ));
            }
        }

        return $merged;
    }

    public function menu(): Menu
    {
        return $this->menu;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $cNormal = $this->getColor(0x0301) & 0xFF;
        $cHighlight = $this->getColor(0x0302) & 0xFF;
        $cSelected = $this->getColor(3) & 0xFF;
        $cSelectedHighlight = $this->getColor(4) & 0xFF;

        $b = new DrawBuffer($width);
        $b->moveChar(0, ' ', $cNormal, $width);

        $x = 1;
        foreach ($this->menu->items() as $index => $item) {
            if ($item->name === '') {
                continue;
            }
            $len = $this->visibleLength($item->name);
            if ($x + $len < $width) {
                $normal = $index === $this->activeIndex ? $cSelected : $cNormal;
                $highlight = $index === $this->activeIndex ? $cSelectedHighlight : $cHighlight;
                $b->moveChar($x, ' ', $normal, 1);
                $b->moveCStr($x + 1, $item->name, $normal, $highlight);
                $b->moveChar($x + $len + 1, ' ', $normal, 1);
            }
            $x += $len + 2;
        }

        $this->writeBuf(0, 0, $width, 1, $b);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->isCommand(Cmd::Menu)) {
            if ($this->popup === null) {
                $this->openMenu($this->firstSubMenuIndex());
            } else {
                $this->closeMenu();
            }
            $this->clearEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }

            if ($this->popup !== null) {
                $this->handleOpenMenuKey($key);
                $this->clearEvent($event);

                return;
            }

            foreach ($this->menu->items() as $index => $item) {
                if ($item->key !== null && $key->is($item->key)) {
                    if ($item->command !== 0 && ! $this->commandEnabled($item->command)) {
                        continue;
                    }
                    if ($item->subMenu !== null) {
                        $this->openMenu($index);
                    } elseif ($item->command !== 0) {
                        $this->putCommand($item->command);
                    }
                    $this->clearEvent($event);

                    return;
                }
            }

            foreach ($this->menu->items() as $item) {
                foreach ($item->subMenu?->items() ?? [] as $child) {
                    if ($child->key !== null && $key->is($child->key) && $this->itemEnabled($child)) {
                        $this->putCommand($child->command);
                        $this->clearEvent($event);

                        return;
                    }
                }
            }
        }

        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse === null) {
                return;
            }
            $origin = $this->absoluteOrigin();
            $localX = $mouse->where->x - $origin->x;
            $index = $this->topIndexAtColumn($localX);
            if ($index !== null) {
                $item = $this->menu->items()[$index];
                if ($item->subMenu !== null) {
                    $this->openMenu($index);
                } elseif ($this->itemEnabled($item)) {
                    $this->putCommand($item->command);
                }
            }
            $this->clearEvent($event);
        }
    }

    public function activeIndex(): int
    {
        return $this->activeIndex;
    }

    public function selectedIndex(): int
    {
        return $this->selectedIndex;
    }

    public function activeSubMenu(): ?Menu
    {
        return $this->menu->items()[$this->activeIndex]->subMenu ?? null;
    }

    public function itemEnabled(MenuItem $item): bool
    {
        return $item->name !== '' && $item->command !== 0 && $this->commandEnabled($item->command);
    }

    public function activateTopAtColumn(int $column): void
    {
        $index = $this->topIndexAtColumn($column);
        if ($index === null) {
            $this->closeMenu();

            return;
        }

        $item = $this->menu->items()[$index];
        if ($item->subMenu !== null) {
            $this->openMenu($index);
        } elseif ($this->itemEnabled($item)) {
            $this->closeMenu();
            $this->putCommand($item->command);
        }
    }

    public function hoverPopupItem(int $index): void
    {
        $items = $this->activeSubMenu()?->items() ?? [];
        if (isset($items[$index]) && $this->itemEnabled($items[$index])) {
            $this->selectedIndex = $index;
            $this->drawView();
            $this->popup?->drawView();
        }
    }

    public function activatePopupItem(int $index): void
    {
        $items = $this->activeSubMenu()?->items() ?? [];
        if (! isset($items[$index]) || ! $this->itemEnabled($items[$index])) {
            return;
        }

        $command = $items[$index]->command;
        $this->closeMenu();
        $this->putCommand($command);
    }

    public function dismissPopup(): void
    {
        $this->closeMenu();
    }

    /** The direct command of the item under local column $localX, or 0. */
    public function commandAtColumn(int $localX): int
    {
        $x = 1;
        foreach ($this->menu->items() as $item) {
            if ($item->name === '') {
                continue;
            }
            $len = $this->visibleLength($item->name);
            $end = $x + $len + 2;
            if ($localX >= $x && $localX < $end) {
                return $this->commandEnabled($item->command) ? $item->command : 0;
            }
            $x = $end;
        }

        return 0;
    }

    private function handleOpenMenuKey(KeyDownEvent $key): void
    {
        if ($key->is(Key::Esc) || $key->is(Key::F10)) {
            $this->closeMenu();

            return;
        }
        if ($key->is(Key::Left)) {
            $this->switchMenu(-1);

            return;
        }
        if ($key->is(Key::Right)) {
            $this->switchMenu(1);

            return;
        }
        if ($key->is(Key::Up)) {
            $this->moveSelection(-1);

            return;
        }
        if ($key->is(Key::Down)) {
            $this->moveSelection(1);

            return;
        }
        if ($key->is(Key::Enter)) {
            $this->activatePopupItem($this->selectedIndex);

            return;
        }

        $character = strtolower($key->char);
        if ($character === '') {
            return;
        }
        foreach ($this->activeSubMenu()?->items() ?? [] as $index => $item) {
            if ($this->hotkey($item->name) === $character && $this->itemEnabled($item)) {
                $this->activatePopupItem($index);

                return;
            }
        }
    }

    private function openMenu(?int $index): void
    {
        if ($index === null) {
            return;
        }
        $items = $this->menu->items();
        $subMenu = $items[$index]->subMenu ?? null;
        if ($subMenu === null || $subMenu->items() === []) {
            $this->closeMenu();

            return;
        }

        $this->removePopup();
        $this->activeIndex = $index;
        $this->selectedIndex = $this->firstEnabledIndex($subMenu);
        $owner = $this->owner;
        if ($owner instanceof Group && $owner->getExtent()->height() > 1) {
            $this->popup = new MenuPopup(
                $owner->getExtent(),
                $this,
                $this->topItemX($index),
                $subMenu,
            );
            $owner->insert($this->popup);
        }
        $this->drawView();
    }

    private function closeMenu(): void
    {
        $this->removePopup();
        $this->activeIndex = -1;
        $this->selectedIndex = 0;
        $this->drawView();
    }

    private function removePopup(): void
    {
        if ($this->popup !== null && $this->popup->owner instanceof Group) {
            $this->popup->owner->remove($this->popup);
        }
        $this->popup = null;
    }

    private function switchMenu(int $direction): void
    {
        $items = $this->menu->items();
        $count = count($items);
        if ($count === 0) {
            return;
        }
        for ($offset = 1; $offset <= $count; $offset++) {
            $index = (($this->activeIndex + $direction * $offset) % $count + $count) % $count;
            if (($items[$index]->subMenu?->items() ?? []) !== []) {
                $this->openMenu($index);

                return;
            }
        }
    }

    private function moveSelection(int $direction): void
    {
        $items = $this->activeSubMenu()?->items() ?? [];
        $count = count($items);
        if ($count === 0) {
            return;
        }
        for ($offset = 1; $offset <= $count; $offset++) {
            $index = (($this->selectedIndex + $direction * $offset) % $count + $count) % $count;
            if ($this->itemEnabled($items[$index])) {
                $this->selectedIndex = $index;
                $this->popup?->drawView();

                return;
            }
        }
    }

    private function firstSubMenuIndex(): ?int
    {
        foreach ($this->menu->items() as $index => $item) {
            if (($item->subMenu?->items() ?? []) !== []) {
                return $index;
            }
        }

        return null;
    }

    private function firstEnabledIndex(Menu $menu): int
    {
        foreach ($menu->items() as $index => $item) {
            if ($this->itemEnabled($item)) {
                return $index;
            }
        }

        return 0;
    }

    private function topIndexAtColumn(int $column): ?int
    {
        $x = 1;
        foreach ($this->menu->items() as $index => $item) {
            if ($item->name === '') {
                continue;
            }
            $end = $x + $this->visibleLength($item->name) + 2;
            if ($column >= $x && $column < $end) {
                return $index;
            }
            $x = $end;
        }

        return null;
    }

    private function topItemX(int $target): int
    {
        $x = 1;
        foreach ($this->menu->items() as $index => $item) {
            if ($index === $target) {
                return $x;
            }
            if ($item->name !== '') {
                $x += $this->visibleLength($item->name) + 2;
            }
        }

        return 1;
    }

    private function hotkey(string $label): string
    {
        return preg_match('/~(.)~/u', $label, $matches) === 1 ? strtolower($matches[1]) : '';
    }

    private function putCommand(int $command): void
    {
        $owner = $this->owner;
        if ($owner instanceof Group) {
            $owner->putEvent(Event::command($command));
        }
    }

    /** Length of a ~hotkey~-marked label with the tildes removed. */
    private function visibleLength(string $name): int
    {
        return TerminalText::length(str_replace('~', '', $name));
    }
}
