<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Menus;

use Closure;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Dialogs\Mnemonic;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
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

    /** @var null|Closure(int):string */
    private ?Closure $hintProvider = null;

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

    /** Change the active help context and redraw only when it actually changes. */
    public function setHelpContext(int $helpCtx): void
    {
        if ($this->helpCtx === $helpCtx) {
            return;
        }
        $this->helpCtx = $helpCtx;
        $this->drawView();
    }

    /** Pull the active context from the owning view tree (normally the desktop). */
    public function update(): void
    {
        $this->setHelpContext($this->owner?->getHelpCtx() ?? 0);
    }

    /** @param null|callable(int):string $provider */
    public function setHintProvider(?callable $provider): void
    {
        $this->hintProvider = $provider === null ? null : Closure::fromCallable($provider);
        $this->drawView();
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

        $layout = $this->hintLayout();
        foreach ($layout as ['item' => $item, 'x' => $x, 'len' => $len]) {
            if ($x + $len < $width) {
                $b->moveChar($x, ' ', $cNormal, 1);
                $b->moveCStr($x + 1, $item->text, $cNormal, $cHighlight);
                $b->moveChar($x + $len + 1, ' ', $cNormal, 1);
            }
        }
        $last = end($layout);
        $after = $last === false ? 0 : $last['x'] + $last['len'] + 2;

        $hint = $this->hintProvider === null ? '' : ($this->hintProvider)($this->helpCtx);
        if ($hint !== '' && $after < $width - 2) {
            $b->moveStr($after, ' │ ', $cNormal);
            $b->moveStr($after + 3, TerminalText::slice($hint, 0, max(0, $width - $after - 3)), $cNormal);
        }

        $this->writeBuf(0, 0, $width, 1, $b);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Broadcast && $event->asMessage()?->command === Cmd::CommandSetChanged) {
            $this->drawView();

            return;
        }
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            foreach ($this->enabledItems() as $item) {
                if ($item->key !== null && $key->is($item->key)) {
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
        foreach ($this->hintLayout() as ['item' => $item, 'x' => $x, 'len' => $len]) {
            if ($localX >= $x && $localX < $x + $len + 2) {
                return $item->command;
            }
        }

        return 0;
    }

    /**
     * Horizontal placement of every visible enabled hint, laid out left to
     * right with two trailing spaces per entry. Painting and hit-testing both
     * consume this so they can never drift.
     *
     * @return list<array{item: StatusItem, x: int, len: int}>
     */
    private function hintLayout(): array
    {
        $entries = [];
        $x = 0;
        foreach ($this->enabledItems() as $item) {
            if ($item->text === '') {
                continue;
            }
            $len = Mnemonic::visibleLength($item->text);
            $entries[] = ['item' => $item, 'x' => $x, 'len' => $len];
            $x += $len + 2;
        }

        return $entries;
    }

}
