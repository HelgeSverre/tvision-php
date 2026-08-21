<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use Closure;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Dialogs\Mnemonic;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;

/**
 * A framed vertical menu (Turbo Vision's TMenuBox).
 *
 * It is useful on its own and is also the building block used by MenuBar's
 * pull-down hierarchy.  Callbacks let a host decide whether choosing a submenu
 * opens another box or whether choosing a command closes a surrounding modal.
 */
class MenuBox extends MenuView
{
    private int $selectedIndex = 0;

    /** @var null|Closure(MenuItem, self):void */
    private ?Closure $onActivate = null;

    /** @var null|Closure(MenuItem, self):void */
    private ?Closure $onSelect = null;

    public function __construct(
        Rect $bounds,
        private readonly Menu $menu,
        private readonly ?MenuView $parentMenu = null,
    ) {
        parent::__construct($bounds);
        $this->options |= State::PreProcess | State::FirstClick;
        $this->eventMask |= EventMask::Mouse;
        $this->selectedIndex = $this->firstSelectableIndex();
    }

    public function menu(): Menu
    {
        return $this->menu;
    }

    public function parentMenu(): ?MenuView
    {
        return $this->parentMenu;
    }

    public function selectedIndex(): int
    {
        return $this->selectedIndex;
    }

    public function selectedItem(): ?MenuItem
    {
        return $this->menu->items()[$this->selectedIndex] ?? null;
    }

    /** The selected item's context takes precedence, then its parent menu's context. */
    public function getHelpCtx(): int
    {
        $item = $this->selectedItem();
        if ($item !== null && $item->name !== '' && $item->helpCtx !== 0) {
            return $item->helpCtx;
        }

        return $this->parentMenu?->getHelpCtx() ?? parent::getHelpCtx();
    }

    /** @param null|callable(MenuItem, self):void $callback */
    public function onActivate(?callable $callback): static
    {
        $this->onActivate = $callback === null ? null : Closure::fromCallable($callback);

        return $this;
    }

    /** @param null|callable(MenuItem, self):void $callback */
    public function onSelect(?callable $callback): static
    {
        $this->onSelect = $callback === null ? null : Closure::fromCallable($callback);

        return $this;
    }

    public function selectIndex(int $index): bool
    {
        $item = $this->menu->items()[$index] ?? null;
        if ($item === null || ! $this->itemEnabled($item)) {
            return false;
        }
        if ($this->selectedIndex !== $index) {
            $this->selectedIndex = $index;
            $this->drawView();
            if ($this->onSelect !== null) {
                ($this->onSelect)($item, $this);
            }
        }

        return true;
    }

    public function selectRelative(int $direction): void
    {
        $items = $this->menu->items();
        $count = count($items);
        if ($count === 0) {
            return;
        }
        for ($step = 1; $step <= $count; $step++) {
            $index = (($this->selectedIndex + $direction * $step) % $count + $count) % $count;
            if ($this->selectIndex($index)) {
                return;
            }
        }
    }

    public function selectFirstItem(): void
    {
        $this->selectIndex($this->firstSelectableIndex());
    }

    public function selectLastItem(): void
    {
        $items = $this->menu->items();
        for ($index = count($items) - 1; $index >= 0; $index--) {
            if ($this->selectIndex($index)) {
                return;
            }
        }
    }

    /** Return the item row index at this global point, or null outside the content. */
    public function itemAt(Point $global): ?int
    {
        $local = $this->makeLocal($global);
        $index = $local->y - 1;
        if ($local->x <= 0 || $local->x >= $this->bounds->width() - 1
            || ! isset($this->menu->items()[$index])) {
            return null;
        }

        return $index;
    }

    /** Activate a selectable item. Returns it even when a host callback handles it. */
    public function activate(int $index): ?MenuItem
    {
        if (! $this->selectIndex($index)) {
            return null;
        }
        $item = $this->menu->items()[$index];
        if ($this->onActivate !== null) {
            ($this->onActivate)($item, $this);
        } elseif ($item->command !== 0) {
            $this->putCommand($item->command);
        }

        return $item;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width < 2 || $height < 2) {
            return;
        }

        $normal = $this->getColor(1) & 0xFF;
        $hotkey = $this->getColor(2) & 0xFF;
        $selected = $this->getColor(3) & 0xFF;
        $selectedHotkey = $this->getColor(4) & 0xFF;
        $disabled = $this->getColor(5) & 0xFF;
        $shortcut = $this->getColor(6) & 0xFF;

        $this->drawBorder($normal);
        foreach ($this->menu->items() as $index => $item) {
            $rowY = $index + 1;
            if ($rowY >= $height - 1) {
                break;
            }
            $row = new DrawBuffer($width - 2);
            if ($item->name === '') {
                $row->moveChar(0, '─', $normal, $width - 2);
            } else {
                $enabled = $this->itemEnabled($item);
                $isSelected = $enabled && $index === $this->selectedIndex;
                $rowColor = $enabled ? ($isSelected ? $selected : $normal) : $disabled;
                $hotkeyColor = $isSelected ? $selectedHotkey : ($enabled ? $hotkey : $disabled);
                $row->moveChar(0, ' ', $rowColor, $width - 2);
                $row->moveCStr(1, $item->name, $rowColor, $hotkeyColor);
                if ($item->subMenu !== null) {
                    $row->moveStr($width - 4, '►', $rowColor);
                } elseif ($item->help !== '') {
                    $helpX = max(1, $width - 3 - TerminalText::length($item->help));
                    $row->moveStr($helpX, $item->help, $isSelected ? $selected : $shortcut);
                }
            }
            $this->writeLine(1, $rowY, $width - 2, 1, $row);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key !== null && $this->handleKey($key)) {
                $this->clearEvent($event);
            }

            return;
        }
        if ($event->what !== EventType::MouseDown && $event->what !== EventType::MouseMove) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $index = $this->itemAt($mouse->where);
        if ($index !== null) {
            $this->selectIndex($index);
            if ($event->what === EventType::MouseDown) {
                $this->activate($index);
            }
            $this->clearEvent($event);
        }
    }

    /** Size and clamp a menu box inside $available, anchored at the requested corner. */
    public static function boundsFor(Rect $available, Menu $menu, Point $anchor): Rect
    {
        [$width, $height] = self::dimensionsFor($available, $menu);
        $x = max($available->a->x, min($anchor->x, $available->b->x - $width));
        $y = max($available->a->y, min($anchor->y, $available->b->y - $height));

        return Rect::of($x, $y, $x + $width, $y + $height);
    }

    /** @return array{int, int} */
    public static function dimensionsFor(Rect $available, Menu $menu): array
    {
        $width = 12;
        foreach ($menu->items() as $item) {
            if ($item->name === '') {
                continue;
            }
            $width = max(
                $width,
                TerminalText::length(str_replace('~', '', $item->name))
                    + TerminalText::length($item->help)
                    + ($item->subMenu !== null ? 6 : ($item->help === '' ? 5 : 7)),
            );
        }

        return [
            min($width, max(0, $available->width())),
            min(count($menu->items()) + 2, max(0, $available->height())),
        ];
    }

    protected function handleKey(KeyDownEvent $key): bool
    {
        if ($key->is(Key::Up)) {
            $this->selectRelative(-1);

            return true;
        }
        if ($key->is(Key::Down)) {
            $this->selectRelative(1);

            return true;
        }
        if ($key->is(Key::Home)) {
            $this->selectFirstItem();

            return true;
        }
        if ($key->is(Key::End)) {
            $this->selectLastItem();

            return true;
        }
        if ($key->is(Key::Enter)) {
            $this->activate($this->selectedIndex);

            return true;
        }
        $char = strtolower($key->char);
        if ($char === '') {
            return false;
        }
        foreach ($this->menu->items() as $index => $item) {
            if (Mnemonic::hotKey($item->name) === $char && $this->itemEnabled($item)) {
                $this->activate($index);

                return true;
            }
        }

        return false;
    }

    private function firstSelectableIndex(): int
    {
        foreach ($this->menu->items() as $index => $item) {
            if ($this->itemEnabled($item)) {
                return $index;
            }
        }

        return 0;
    }

    private function drawBorder(int $attr): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $top = new DrawBuffer($width);
        $top->moveChar(0, '─', $attr, $width);
        $top->moveStr(0, '┌', $attr);
        $top->moveStr($width - 1, '┐', $attr);
        $this->writeLine(0, 0, $width, 1, $top);
        for ($row = 1; $row < $height - 1; $row++) {
            $line = new DrawBuffer($width);
            $line->moveChar(0, ' ', $attr, $width);
            $line->moveStr(0, '│', $attr);
            $line->moveStr($width - 1, '│', $attr);
            $this->writeLine(0, $row, $width, 1, $line);
        }
        $bottom = new DrawBuffer($width);
        $bottom->moveChar(0, '─', $attr, $width);
        $bottom->moveStr(0, '└', $attr);
        $bottom->moveStr($width - 1, '┘', $attr);
        $this->writeLine(0, $height - 1, $width, 1, $bottom);
    }

    private function putCommand(int $command): void
    {
        $owner = $this->owner;
        if ($owner instanceof Group) {
            $owner->putEvent(Event::command($command));
        }
    }
}
