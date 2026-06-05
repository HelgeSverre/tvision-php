<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;

/**
 * The top menu bar (faithful to TMenuBar::draw). Renders top-level items as
 * ' ' + ~hotkey~ name + ' ' starting at column 1.
 *
 * M1 SCOPE: render + top-level hotkey/click command dispatch only. Opening and
 * navigating a pull-down MenuBox is deferred to M3.
 */
final class MenuBar extends MenuView
{
    private Menu $menu;

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

        $b = new DrawBuffer($width);
        $b->moveChar(0, ' ', $cNormal, $width);

        $x = 1;
        foreach ($this->menu->items() as $item) {
            if ($item->name === '') {
                continue;
            }
            $len = $this->visibleLength($item->name);
            if ($x + $len < $width) {
                $b->moveChar($x, ' ', $cNormal, 1);
                $b->moveCStr($x + 1, $item->name, $cNormal, $cHighlight);
                $b->moveChar($x + $len + 1, ' ', $cNormal, 1);
            }
            $x += $len + 2;
        }

        $this->writeBuf(0, 0, $width, 1, $b);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            foreach ($this->menu->items() as $item) {
                if ($item->key !== null && $key->is($item->key)) {
                    // M1: top-level hotkey recognized. A direct command dispatches;
                    // a submenu host is consumed (pull-down navigation is M3).
                    if ($item->command !== 0) {
                        $this->putCommand($item->command);
                    }
                    $this->clearEvent($event);

                    return;
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
            $command = $this->commandAtColumn($localX);
            if ($command !== 0) {
                $this->putCommand($command);
            }
            $this->clearEvent($event);
        }
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
                return $item->command;
            }
            $x = $end;
        }

        return 0;
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
        return mb_strlen(str_replace('~', '', $name));
    }
}
