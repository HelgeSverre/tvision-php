<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** Shared keyboard, mnemonic, bitmap and rendering mechanics for item clusters. */
abstract class Cluster extends View
{
    /** @var list<string> */
    public array $items;

    public int $value = 0;
    public int $enableMask = 0xFFFFFFFF;
    public int $sel = 0;

    /** @param list<string>|SItem|null $items */
    public function __construct(Rect $bounds, SItem|array|null $items)
    {
        parent::__construct($bounds);
        $this->items = $items instanceof SItem ? $items->values() : ($items ?? []);
        $this->options |= State::Selectable | State::FirstClick | State::PreProcess | State::PostProcess;
        $this->setCursor(2, 0);
        $this->showCursor();
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes("\x10\x11\x12\x12\x1F");
    }

    public function dataSize(): int
    {
        return 2;
    }

    public function getData(): mixed
    {
        return $this->value;
    }

    public function setData(mixed $data): void
    {
        $this->value = is_int($data) ? $data : (is_numeric($data) ? (int) $data : 0);
        $this->drawView();
    }

    public function buttonState(int $item): bool
    {
        return $item >= 0 && $item < count($this->items) && (($this->enableMask & (1 << $item)) !== 0);
    }

    public function setButtonState(int $mask, bool $enable): void
    {
        $this->enableMask = $enable ? $this->enableMask | $mask : $this->enableMask & ~$mask;
        $this->options = $this->hasEnabledItem() ? $this->options | State::Selectable : $this->options & ~State::Selectable;
        $this->drawView();
    }

    public function mark(int $item): bool
    {
        return false;
    }

    public function multiMark(int $item): int
    {
        return $this->mark($item) ? 1 : 0;
    }

    public function movedTo(int $item): void {}

    abstract public function press(int $item): void;

    /** Draw compact [ ]/( ) style controls, using one item per row/column cell. */
    protected function drawMultiBox(string $icon, string $markers): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $normal = $this->getColor(0x0301) & 0xff;
        $selected = $this->getColor(0x0402) & 0xff;
        $disabled = $this->getColor(0x0505) & 0xff;
        $columns = max(1, (int) ceil(max(1, count($this->items)) / $height));
        $columnWidth = max(1, intdiv($width, $columns));

        for ($row = 0; $row < $height; $row++) {
            $b = new DrawBuffer($width, $normal);
            for ($col = 0; $col < $columns; $col++) {
                $item = $col * $height + $row;
                if (!isset($this->items[$item])) {
                    continue;
                }
                $x = $col * $columnWidth;
                $attr = !$this->buttonState($item) ? $disabled
                    : ($this->getState(State::Selected) && $item === $this->sel ? $selected : $normal);
                $b->moveChar($x, ' ', $attr, $columnWidth);
                $b->moveStr($x, $icon, $attr);
                $markerIndex = max(0, min(strlen($markers) - 1, $this->multiMark($item)));
                $b->moveStr($x + 2, $markers[$markerIndex] ?? ' ', $attr);
                $b->moveCStr($x + 5, $this->items[$item], $attr, $attr);
            }
            $this->writeLine(0, $row, $width, 1, $b);
        }
        $this->setCursor($this->column($this->sel) + 2, $this->row($this->sel));
    }

    public function handleEvent(Event $event): void
    {
        if ($this->items === [] || ($this->options & State::Selectable) === 0) {
            return;
        }
        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse === null) {
                return;
            }
            $item = $this->findSel($this->makeLocal($mouse->where));
            if ($item !== null && $this->buttonState($item)) {
                $this->selectItem($item);
                $this->press($item);
                $this->drawView();
            }
            $this->clearEvent($event);

            return;
        }
        if ($event->what !== EventType::KeyDown || !$this->getState(State::Focused)) {
            return;
        }
        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        $next = match ($key->keyCode) {
            Key::Up->value => $this->sel - 1,
            Key::Down->value => $this->sel + 1,
            Key::Left->value => $this->sel - max(1, $this->bounds->height()),
            Key::Right->value => $this->sel + max(1, $this->bounds->height()),
            default => null,
        };
        if ($next !== null) {
            $this->selectEnabled($next);
            $this->clearEvent($event);

            return;
        }
        if ($key->char === ' ') {
            if ($this->buttonState($this->sel)) {
                $this->press($this->sel);
                $this->drawView();
            }
            $this->clearEvent($event);

            return;
        }
        foreach ($this->items as $item => $text) {
            $hotKey = self::hotKey($text);
            if ($hotKey !== null && strcasecmp($hotKey, $key->char) === 0 && $this->buttonState($item)) {
                $this->selectItem($item);
                $this->press($item);
                $this->drawView();
                $this->clearEvent($event);

                return;
            }
        }
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & State::Selected) !== 0) {
            $this->selectEnabled($this->sel);
        }
    }

    private function selectEnabled(int $candidate): void
    {
        $count = count($this->items);
        if ($count === 0) {
            return;
        }
        for ($attempt = 0; $attempt < $count; $attempt++) {
            $candidate = (($candidate % $count) + $count) % $count;
            if ($this->buttonState($candidate)) {
                $this->selectItem($candidate);

                return;
            }
            $candidate++;
        }
    }

    private function selectItem(int $item): void
    {
        $this->sel = $item;
        $this->movedTo($item);
        $this->drawView();
    }

    private function hasEnabledItem(): bool
    {
        foreach (array_keys($this->items) as $item) {
            if ($this->buttonState($item)) {
                return true;
            }
        }

        return false;
    }

    private function row(int $item): int
    {
        return $this->bounds->height() > 0 ? $item % $this->bounds->height() : 0;
    }

    private function column(int $item): int
    {
        $height = max(1, $this->bounds->height());
        $columns = max(1, (int) ceil(max(1, count($this->items)) / $height));

        return intdiv($item, $height) * max(1, intdiv($this->bounds->width(), $columns));
    }

    private function findSel(Point $point): ?int
    {
        if ($point->x < 0 || $point->y < 0 || $point->y >= $this->bounds->height()) {
            return null;
        }
        $height = max(1, $this->bounds->height());
        $columns = max(1, (int) ceil(max(1, count($this->items)) / $height));
        $columnWidth = max(1, intdiv($this->bounds->width(), $columns));
        $item = intdiv($point->x, $columnWidth) * $height + $point->y;

        return isset($this->items[$item]) ? $item : null;
    }

    private static function hotKey(string $text): ?string
    {
        return Mnemonic::extract($text);
    }
}
