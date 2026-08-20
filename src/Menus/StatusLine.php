<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\MessageEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;

/**
 * The bottom status/hint bar (faithful to TStatusLine). Renders the current def's
 * items, maps a matching key press to its command (rewriting the event in place), and
 * dispatches a clicked item's command.
 */
final class StatusLine extends MenuView
{
    /** @var list<StatusDef> */
    private array $defs;

    private int $helpCtx = 0;

    public function __construct(Rect $bounds, StatusDef ...$defs)
    {
        parent::__construct($bounds);
        $this->defs = array_values($defs);
    }

    /** cpStatusLine "\x02\x03\x04\x05\x06\x07" — identical to cpMenuView. */
    public function getPalette(): Palette
    {
        return Palette::fromBytes("\x02\x03\x04\x05\x06\x07");
    }

    /**
     * The StatusItems active for the current help context.
     *
     * @return list<StatusItem>
     */
    private function items(): array
    {
        foreach ($this->defs as $def) {
            if ($this->helpCtx >= $def->min && $this->helpCtx <= $def->max) {
                return $def->getItems();
            }
        }

        return [];
    }

    /** @return list<StatusItem> */
    private function enabledItems(): array
    {
        return array_values(array_filter(
            $this->items(),
            fn (StatusItem $item): bool => $this->commandEnabled($item->command),
        ));
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $cNormal = $this->getColor(0x0301) & 0xFF;
        $cHighlight = $this->getColor(0x0302) & 0xFF;

        $b = new DrawBuffer($width);
        $b->moveChar(0, ' ', $cNormal, $width);

        $x = 0;
        foreach ($this->enabledItems() as $item) {
            if ($item->text === '') {
                continue;
            }
            $len = $this->visibleLength($item->text);
            if ($x + $len < $width) {
                $b->moveChar($x, ' ', $cNormal, 1);
                $b->moveCStr($x + 1, $item->text, $cNormal, $cHighlight);
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
            foreach ($this->enabledItems() as $item) {
                if ($item->key !== null && $key->is($item->key)) {
                    // Faithful: rewrite this event into a Command in place.
                    $event->what = EventType::Command;
                    $event->payload = new MessageEvent($item->command);

                    return;
                }
            }

            return;
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
                $owner = $this->owner;
                if ($owner instanceof Group) {
                    $owner->putEvent(Event::command($command));
                }
            }
            $this->clearEvent($event);
        }
    }

    public function commandAtColumn(int $localX): int
    {
        $x = 0;
        foreach ($this->enabledItems() as $item) {
            if ($item->text === '') {
                continue;
            }
            $len = $this->visibleLength($item->text);
            $end = $x + $len + 2;
            if ($localX >= $x && $localX < $end) {
                return $item->command;
            }
            $x = $end;
        }

        return 0;
    }

    private function visibleLength(string $text): int
    {
        return TerminalText::length(str_replace('~', '', $text));
    }
}
