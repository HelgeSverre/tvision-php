<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;

/** Full-screen mouse catcher that paints one pull-down menu over the application. */
final class MenuPopup extends MenuView
{
    private int $menuWidth;

    public function __construct(
        Rect $bounds,
        private readonly MenuBar $menuBar,
        private readonly int $menuX,
        private readonly Menu $menu,
    ) {
        parent::__construct($bounds);
        $this->options |= State::FirstClick;
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->menuWidth = $this->measureWidth();
    }

    public function draw(): void
    {
        $items = $this->menu->items();
        if ($items === []) {
            return;
        }

        $normal = $this->getColor(1) & 0xFF;
        $hotkey = $this->getColor(2) & 0xFF;
        $selected = $this->getColor(3) & 0xFF;
        $selectedHotkey = $this->getColor(4) & 0xFF;
        $disabled = $this->getColor(5) & 0xFF;
        $shortcut = $this->getColor(6) & 0xFF;
        $x = min($this->menuX, max(0, $this->bounds->width() - $this->menuWidth));

        $this->drawBorder($x, 1, $this->menuWidth, count($items) + 2, $normal);
        foreach ($items as $index => $item) {
            $row = new DrawBuffer($this->menuWidth - 2);
            if ($item->name === '') {
                $row->moveChar(0, '─', $normal, $this->menuWidth - 2);
            } else {
                $enabled = $this->menuBar->itemEnabled($item);
                $isSelected = $enabled && $index === $this->menuBar->selectedIndex();
                $rowColor = $enabled ? ($isSelected ? $selected : $normal) : $disabled;
                $hotkeyColor = $isSelected ? $selectedHotkey : ($enabled ? $hotkey : $disabled);
                $row->moveChar(0, ' ', $rowColor, $this->menuWidth - 2);
                $row->moveCStr(1, $item->name, $rowColor, $hotkeyColor);
                if ($item->help !== '') {
                    $helpX = max(1, $this->menuWidth - 3 - TerminalText::length($item->help));
                    $row->moveStr($helpX, $item->help, $isSelected ? $selected : $shortcut);
                }
                if ($item->subMenu !== null) {
                    $row->moveStr($this->menuWidth - 4, '►', $rowColor);
                }
            }
            $this->writeLine($x + 1, $index + 2, $this->menuWidth - 2, 1, $row);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what !== EventType::MouseDown && $event->what !== EventType::MouseMove) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $local = $this->makeLocal($mouse->where);
        if ($local->y === 0 && $event->what === EventType::MouseDown) {
            $this->menuBar->activateTopAtColumn($local->x);
            $this->clearEvent($event);

            return;
        }

        $x = min($this->menuX, max(0, $this->bounds->width() - $this->menuWidth));
        $index = $local->y - 2;
        $inside = $local->x > $x && $local->x < $x + $this->menuWidth - 1
            && isset($this->menu->items()[$index]);
        if ($inside) {
            $this->menuBar->hoverPopupItem($index);
            if ($event->what === EventType::MouseDown) {
                $this->menuBar->activatePopupItem($index);
            }
        } elseif ($event->what === EventType::MouseDown) {
            $this->menuBar->dismissPopup();
        }
        $this->clearEvent($event);
    }

    private function measureWidth(): int
    {
        $width = 12;
        foreach ($this->menu->items() as $item) {
            $width = max(
                $width,
                TerminalText::length(str_replace('~', '', $item->name))
                    + TerminalText::length($item->help)
                    + ($item->help === '' ? 5 : 7),
            );
        }

        return min($width, max(2, $this->bounds->width()));
    }

    private function drawBorder(int $x, int $y, int $width, int $height, int $attr): void
    {
        $top = new DrawBuffer($width);
        $top->moveChar(0, '─', $attr, $width);
        $top->moveStr(0, '┌', $attr);
        $top->moveStr($width - 1, '┐', $attr);
        $this->writeLine($x, $y, $width, 1, $top);

        for ($row = 1; $row < $height - 1; $row++) {
            $line = new DrawBuffer($width);
            $line->moveChar(0, ' ', $attr, $width);
            $line->moveStr(0, '│', $attr);
            $line->moveStr($width - 1, '│', $attr);
            $this->writeLine($x, $y + $row, $width, 1, $line);
        }

        $bottom = new DrawBuffer($width);
        $bottom->moveChar(0, '─', $attr, $width);
        $bottom->moveStr(0, '└', $attr);
        $bottom->moveStr($width - 1, '┘', $attr);
        $this->writeLine($x, $y + $height - 1, $width, 1, $bottom);
    }
}
