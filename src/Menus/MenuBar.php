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
 * The application menu bar. Pull-downs are hosted in a full-screen input shield
 * so they can be dismissed by an outside click while their visible MenuBoxes retain
 * normal, compact bounds. Nested submenus may be opened to arbitrary depth.
 */
final class MenuBar extends MenuView
{
    private Menu $menu;

    private int $activeIndex = -1;

    private ?MenuOverlay $overlay = null;

    /** @var list<MenuBox> */
    private array $boxes = [];

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
        foreach ($menus as $menu) {
            if ($menu instanceof Menu) {
                foreach ($menu->items() as $item) {
                    $merged->add($item);
                }
            } else {
                $merged->add(new MenuItem(
                    name: $menu->name,
                    command: 0,
                    key: $menu->key,
                    help: '',
                    subMenu: $menu->menu(),
                    helpCtx: $menu->helpCtx,
                ));
            }
        }

        return $merged;
    }

    public function menu(): Menu
    {
        return $this->menu;
    }

    public function activeIndex(): int
    {
        return $this->activeIndex;
    }

    /** Index selected in the visible deepest menu, retained for API compatibility. */
    public function selectedIndex(): int
    {
        return $this->boxes === [] ? 0 : $this->boxes[array_key_last($this->boxes)]->selectedIndex();
    }

    public function activeSubMenu(): ?Menu
    {
        return $this->menu->items()[$this->activeIndex]->subMenu ?? null;
    }

    public function getHelpCtx(): int
    {
        $box = $this->deepestBox();

        return $box?->getHelpCtx() ?? parent::getHelpCtx();
    }

    /** @return list<MenuBox> Visible top-level-to-deepest menu boxes. */
    public function openBoxes(): array
    {
        return $this->boxes;
    }

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

    public function draw(): void
    {
        $width = $this->bounds->width();
        $normal = $this->getColor(0x0301) & 0xFF;
        $highlight = $this->getColor(0x0302) & 0xFF;
        $selected = $this->getColor(3) & 0xFF;
        $selectedHighlight = $this->getColor(4) & 0xFF;

        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, ' ', $normal, $width);
        $x = 1;
        foreach ($this->menu->items() as $index => $item) {
            if ($item->name === '') {
                continue;
            }
            $length = $this->visibleLength($item->name);
            if ($x + $length < $width) {
                $itemNormal = $index === $this->activeIndex ? $selected : $normal;
                $itemHotkey = $index === $this->activeIndex ? $selectedHighlight : $highlight;
                $buffer->moveChar($x, ' ', $itemNormal, 1);
                $buffer->moveCStr($x + 1, $item->name, $itemNormal, $itemHotkey);
                $buffer->moveChar($x + $length + 1, ' ', $itemNormal, 1);
            }
            $x += $length + 2;
        }
        $this->writeBuf(0, 0, $width, 1, $buffer);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Broadcast && $event->asMessage()?->command === Cmd::CommandSetChanged) {
            $this->drawView();
            foreach ($this->boxes as $box) {
                $box->drawView();
            }

            return;
        }

        if ($event->isCommand(Cmd::Menu)) {
            $this->overlay === null ? $this->openMenu($this->firstSubMenuIndex()) : $this->closeMenu();
            $this->clearEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            if ($this->overlay !== null) {
                $this->handleOpenMenuKey($key);
                $this->clearEvent($event);

                return;
            }
            foreach ($this->menu->items() as $index => $item) {
                if ($item->key !== null && $key->is($item->key) && $this->itemEnabled($item)) {
                    if ($item->subMenu !== null) {
                        $this->openMenu($index);
                    } else {
                        $this->putCommand($item->command);
                    }
                    $this->clearEvent($event);

                    return;
                }
            }
            // Menu-item hotkeys work globally, just as in Turbo Vision. Search
            // every submenu recursively without requiring its parent to be open.
            $command = $this->findHotkeyCommand($this->menu, $key);
            if ($command !== null) {
                $this->putCommand($command);
                $this->clearEvent($event);
            }

            return;
        }

        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse !== null && $this->mouseInView($mouse->where)) {
                $this->activateTopAtColumn($this->makeLocal($mouse->where)->x);
                $this->clearEvent($event);
            }
        }
    }

    /** Called by the full-screen overlay for every mouse event while a menu is open. */
    public function handleOverlayMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        if ($this->mouseInView($mouse->where)) {
            $index = $this->topIndexAtColumn($this->makeLocal($mouse->where)->x);
            if ($index !== null && $this->itemEnabled($this->menu->items()[$index])) {
                if ($event->what === EventType::MouseDown || $this->activeIndex !== $index) {
                    $this->openMenu($index);
                }
            }
            $this->clearEvent($event);

            return;
        }

        for ($depth = count($this->boxes) - 1; $depth >= 0; $depth--) {
            $box = $this->boxes[$depth];
            $index = $box->itemAt($mouse->where);
            if ($index === null) {
                continue;
            }
            $box->selectIndex($index);
            $item = $box->menu()->items()[$index];
            if ($item->subMenu !== null && $event->what === EventType::MouseMove) {
                $this->openSubMenu($depth, $item, $box);
            } elseif ($event->what === EventType::MouseDown) {
                $this->activateBoxItem($depth, $item, $box);
            }
            $this->clearEvent($event);

            return;
        }

        if ($event->what === EventType::MouseDown) {
            $this->closeMenu();
        }
        $this->clearEvent($event);
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

    /** Select an item in the deepest box; retained for existing integrations. */
    public function hoverPopupItem(int $index): void
    {
        if ($this->boxes !== []) {
            $this->boxes[array_key_last($this->boxes)]->selectIndex($index);
        }
    }

    /** Activate an item in the deepest box; retained for existing integrations. */
    public function activatePopupItem(int $index): void
    {
        if ($this->boxes === []) {
            return;
        }
        $depth = array_key_last($this->boxes);
        $box = $this->boxes[$depth];
        $item = $box->menu()->items()[$index] ?? null;
        if ($item !== null) {
            $this->activateBoxItem($depth, $item, $box);
        }
    }

    public function dismissPopup(): void
    {
        $this->closeMenu();
    }

    /** The direct command of the top-level item under local column $localX, or 0. */
    public function commandAtColumn(int $localX): int
    {
        $index = $this->topIndexAtColumn($localX);
        if ($index === null) {
            return 0;
        }
        $item = $this->menu->items()[$index];

        return $item->subMenu === null && $this->itemEnabled($item) ? $item->command : 0;
    }

    private function handleOpenMenuKey(KeyDownEvent $key): void
    {
        if ($key->is(Key::Esc) || $key->is(Key::F10)) {
            $this->closeMenu();

            return;
        }
        foreach ($this->menu->items() as $index => $item) {
            if ($item->key !== null && $key->is($item->key) && $this->itemEnabled($item)) {
                $this->openMenu($index);

                return;
            }
        }
        if ($key->is(Key::Left)) {
            if (count($this->boxes) > 1) {
                $this->closeDeeper(count($this->boxes) - 2);
            } else {
                $this->switchMenu(-1);
            }

            return;
        }
        if ($key->is(Key::Right)) {
            $box = $this->deepestBox();
            $item = $box?->selectedItem();
            if ($box !== null && $item?->subMenu !== null) {
                $this->openSubMenu($this->deepestDepth(), $item, $box);
            } elseif (count($this->boxes) === 1) {
                $this->switchMenu(1);
            }

            return;
        }
        $box = $this->deepestBox();
        if ($box === null) {
            return;
        }
        if ($key->is(Key::Up)) {
            $box->selectRelative(-1);

            return;
        }
        if ($key->is(Key::Down)) {
            $box->selectRelative(1);

            return;
        }
        if ($key->is(Key::Home)) {
            $box->selectFirstItem();

            return;
        }
        if ($key->is(Key::End)) {
            $box->selectLastItem();

            return;
        }
        if ($key->is(Key::Enter)) {
            $item = $box->selectedItem();
            if ($item !== null) {
                $this->activateBoxItem($this->deepestDepth(), $item, $box);
            }

            return;
        }
        $character = strtolower($key->char);
        if ($character === '') {
            return;
        }
        foreach ($box->menu()->items() as $index => $item) {
            if ($this->hotkey($item->name) === $character && $box->itemEnabled($item)) {
                $box->selectIndex($index);
                $this->activateBoxItem($this->deepestDepth(), $item, $box);

                return;
            }
        }
    }

    private function activateBoxItem(int $depth, MenuItem $item, MenuBox $box): void
    {
        if (! $box->itemEnabled($item)) {
            return;
        }
        if ($item->subMenu !== null) {
            $this->openSubMenu($depth, $item, $box);

            return;
        }
        $this->closeMenu();
        $this->putCommand($item->command);
    }

    private function openMenu(?int $index): void
    {
        if ($index === null) {
            return;
        }
        $item = $this->menu->items()[$index] ?? null;
        if ($item?->subMenu === null || $item->subMenu->items() === []) {
            $this->closeMenu();

            return;
        }
        $this->removeOverlay();
        $owner = $this->owner;
        if (! $owner instanceof Group) {
            return;
        }
        $this->activeIndex = $index;
        $this->overlay = new MenuOverlay($owner->getExtent(), $this);
        $owner->insert($this->overlay);
        $origin = $this->absoluteOrigin();
        $overlayOrigin = $this->overlay->absoluteOrigin();
        $boxBounds = MenuBox::boundsFor(
            $this->overlay->getExtent(),
            $item->subMenu,
            new Point($origin->x - $overlayOrigin->x + $this->topItemX($index), $origin->y - $overlayOrigin->y + 1),
        );
        $this->addBox($item->subMenu, $boxBounds);
        $this->drawView();
    }

    private function openSubMenu(int $depth, MenuItem $item, MenuBox $parent): void
    {
        $overlay = $this->overlay;
        if ($item->subMenu === null || $item->subMenu->items() === [] || $overlay === null) {
            return;
        }
        $this->closeDeeper($depth);
        $parentBounds = $parent->getBounds();
        $anchor = new Point(
            $parentBounds->b->x - 1,
            $parentBounds->a->y + $parent->selectedIndex() + 1,
        );
        $bounds = MenuBox::boundsFor($overlay->getExtent(), $item->subMenu, $anchor);
        $this->addBox($item->subMenu, $bounds, $parent);
    }

    private function addBox(Menu $menu, Rect $bounds, ?MenuView $parentMenu = null): void
    {
        if ($this->overlay === null) {
            return;
        }
        $box = new MenuBox($bounds, $menu, $parentMenu);
        $this->overlay->insert($box);
        $this->boxes[] = $box;
    }

    /** Keep boxes through $depth (inclusive), removing all children below it. */
    private function closeDeeper(int $depth): void
    {
        while (count($this->boxes) > $depth + 1) {
            $box = array_pop($this->boxes);
            if ($box !== null && $box->owner instanceof Group) {
                $box->owner->remove($box);
            }
        }
    }

    private function closeMenu(): void
    {
        $this->removeOverlay();
        $this->activeIndex = -1;
        $this->drawView();
    }

    private function removeOverlay(): void
    {
        if ($this->overlay !== null && $this->overlay->owner instanceof Group) {
            $this->overlay->owner->remove($this->overlay);
        }
        $this->overlay = null;
        $this->boxes = [];
    }

    private function switchMenu(int $direction): void
    {
        $count = count($this->menu->items());
        if ($count === 0) {
            return;
        }
        for ($offset = 1; $offset <= $count; $offset++) {
            $index = (($this->activeIndex + $direction * $offset) % $count + $count) % $count;
            $item = $this->menu->items()[$index];
            if ($item->subMenu !== null && $item->subMenu->items() !== []) {
                $this->openMenu($index);

                return;
            }
        }
    }

    private function firstSubMenuIndex(): ?int
    {
        foreach ($this->menu->items() as $index => $item) {
            if ($item->subMenu !== null && $item->subMenu->items() !== []) {
                return $index;
            }
        }

        return null;
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

    private function findHotkeyCommand(Menu $menu, KeyDownEvent $key): ?int
    {
        foreach ($menu->items() as $item) {
            if ($item->key !== null && $key->is($item->key) && $this->itemEnabled($item) && $item->subMenu === null) {
                return $item->command;
            }
            if ($item->subMenu !== null && ($command = $this->findHotkeyCommand($item->subMenu, $key)) !== null) {
                return $command;
            }
        }

        return null;
    }

    /** A non-empty menu hierarchy always has a concrete deepest index. */
    private function deepestDepth(): int
    {
        return count($this->boxes) - 1;
    }

    private function deepestBox(): ?MenuBox
    {
        $depth = $this->deepestDepth();

        return $depth >= 0 ? $this->boxes[$depth] : null;
    }

    private function hotkey(string $label): string
    {
        return preg_match('/~(.)~/u', $label, $matches) === 1 ? strtolower($matches[1]) : '';
    }

    private function putCommand(int $command): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->putEvent(Event::command($command));
        }
    }

    private function visibleLength(string $name): int
    {
        return TerminalText::length(str_replace('~', '', $name));
    }
}
