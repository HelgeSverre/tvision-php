<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;

/**
 * Abstract scrollable list (faithful to TListViewer). Subclasses supply getText().
 * Tracks focused/topItem/range across one or more columns, navigates by keyboard and
 * mouse, draws items (highlighting the focused one when selected+active), reacts to a
 * vertical scroll bar's cmScrollBarChanged, and broadcasts cmListItemSelected. The
 * stable base M3's concrete ListBox extends.
 */
abstract class ListViewer extends View
{
    /** cpListViewer: 1=active, 2=inactive, 3=focused, 4=selected, 5=divider. */
    private const string PALETTE = "\x1A\x1A\x1B\x1C\x1D";

    public int $focused = 0;

    public int $topItem = 0;

    public int $range = 0;

    public function __construct(
        Rect $bounds,
        public int $numCols = 1,
        protected ?ScrollBar $hScrollBar = null,
        protected ?ScrollBar $vScrollBar = null,
    ) {
        parent::__construct($bounds);
        $this->numCols = $this->effectiveColumnCount();
        $this->options |= State::Selectable | State::FirstClick;

        if ($this->vScrollBar !== null) {
            if ($this->numCols === 1) {
                $this->vScrollBar->setStep($bounds->height() - 1, 1);
            } else {
                $this->vScrollBar->setStep($this->pageSize(), $bounds->height());
            }
        }
        $this->hScrollBar?->setStep(intdiv($bounds->width(), $this->numCols), 1);
    }

    /** Provide the text for an item, truncated to $maxLen graphemes. */
    abstract public function getText(int $item, int $maxLen): string;

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function isSelected(int $item): bool
    {
        return $item === $this->focused;
    }

    public function setRange(int $range): void
    {
        $range = max(0, $range);
        $this->range = $range;
        if ($this->focused >= $range) {
            $this->focused = $range - 1 >= 0 ? $range - 1 : 0;
        }
        if ($this->vScrollBar !== null) {
            $this->vScrollBar->setParams(
                $this->focused,
                0,
                $range - 1,
                $this->vScrollBar->pageStep,
                $this->vScrollBar->arrowStep,
            );
        } else {
            $this->drawView();
        }
    }

    public function focusItem(int $item): void
    {
        $this->focused = $item;
        if ($this->vScrollBar !== null) {
            $this->vScrollBar->setValue($item);
        } else {
            $this->drawView();
        }

        $height = max(1, $this->bounds->height());
        $numCols = $this->effectiveColumnCount();
        $pageSize = $height * $numCols;
        if ($item < $this->topItem) {
            $this->topItem = $numCols === 1 ? $item : $item - $item % $height;
        } elseif ($item >= IntMath::add($this->topItem, $pageSize)) {
            if ($numCols === 1) {
                $this->topItem = IntMath::add($item, 1 - $height);
            } else {
                $columnOffset = $height * ($numCols - 1);
                $this->topItem = IntMath::add($item - $item % $height, -$columnOffset);
            }
        }
    }

    public function focusItemNum(int $item): void
    {
        if ($item < 0) {
            $item = 0;
        } elseif ($item >= $this->range && $this->range > 0) {
            $item = $this->range - 1;
        }
        if ($this->range !== 0) {
            $this->focusItem($item);
        }
    }

    /** Override in M3 to react to a chosen item; default broadcasts cmListItemSelected. */
    public function selectItem(int $item): void
    {
        $this->owner?->handleEvent(Event::broadcast(Cmd::ListItemSelected, $this));
    }

    public function draw(): void
    {
        $selectedActive = $this->getState(State::Selected) && $this->getState(State::Active);
        $normal = $selectedActive ? $this->getColor(1) : $this->getColor(2);
        $focusedColor = $this->getColor(3);
        $selectedColor = $this->getColor(4);

        $indent = $this->hScrollBar !== null ? $this->hScrollBar->value : 0;
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $numCols = $this->effectiveColumnCount();
        $colWidth = intdiv($width, $numCols) + 1;

        for ($i = 0; $i < $height; $i++) {
            $b = new DrawBuffer($width);
            for ($j = 0; $j < $numCols; $j++) {
                $item = IntMath::add($this->topItem, $j * $height + $i);
                $curCol = $j * $colWidth;

                if ($selectedActive && $this->focused === $item && $this->range > 0) {
                    $color = $focusedColor;
                    $this->setCursor($curCol + 1, $i);
                } elseif ($item < $this->range && $this->isSelected($item)) {
                    $color = $selectedColor;
                } else {
                    $color = $normal;
                }

                $b->moveChar($curCol, ' ', $color & 0xFF, $colWidth);
                if ($item < $this->range) {
                    $text = $this->getText($item, $colWidth + $indent);
                    $text = TerminalText::slice($text, $indent, max(0, $colWidth - 1));
                    $b->moveStr($curCol + 1, $text, $color & 0xFF);
                }
            }
            $this->writeLine(0, $i, $width, 1, $b);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::MouseDown) {
            $this->handleMouse($event);

            return;
        }
        if ($event->what === EventType::KeyDown) {
            $this->handleKey($event);

            return;
        }
        if ($event->isCommand(Cmd::ScrollBarChanged)
            && ($this->options & State::Selectable) !== 0
        ) {
            $info = $event->asMessage()?->info;
            if ($info === $this->vScrollBar && $this->vScrollBar !== null) {
                $this->focusItemNum($this->vScrollBar->value);
                $this->drawView();
            } elseif ($info === $this->hScrollBar) {
                $this->drawView();
            }
        }
    }

    private function handleMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $numCols = $this->effectiveColumnCount();
        $colWidth = intdiv($this->bounds->width(), $numCols) + 1;
        $local = $this->makeLocal($mouse->where);
        $column = max(0, min($numCols - 1, intdiv($local->x, $colWidth)));
        $newItem = IntMath::add(
            IntMath::add($this->topItem, $this->bounds->height() * $column),
            $local->y,
        );
        $this->focusItemNum($newItem);
        $this->drawView();
        if ($mouse->doubleClick && $this->range > $newItem) {
            $this->selectItem($newItem);
        }
        $this->clearEvent($event);
    }

    private function handleKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        if ($key->char === ' ' && $this->focused < $this->range) {
            $this->selectItem($this->focused);
            $this->clearEvent($event);

            return;
        }

        $height = max(1, $this->bounds->height());
        $numCols = $this->effectiveColumnCount();
        $pageSize = $height * $numCols;
        $newItem = match ($key->keyCode) {
            Key::Up->value => IntMath::add($this->focused, -1),
            Key::Down->value => IntMath::add($this->focused, 1),
            Key::PageUp->value => IntMath::add($this->focused, -$pageSize),
            Key::PageDown->value => IntMath::add($this->focused, $pageSize),
            Key::Home->value => $this->topItem,
            Key::End->value => IntMath::add($this->topItem, $pageSize - 1),
            Key::Right->value => $numCols > 1 ? IntMath::add($this->focused, $height) : null,
            Key::Left->value => $numCols > 1 ? IntMath::add($this->focused, -$height) : null,
            default => null,
        };

        if ($newItem === null) {
            return;
        }

        $this->focusItemNum($newItem);
        $this->drawView();
        $this->clearEvent($event);
    }

    /** Columns that can affect this view, also bounded so height*columns stays integral. */
    private function effectiveColumnCount(): int
    {
        $height = max(1, $this->bounds->height());
        $maxByPageSize = max(1, intdiv(PHP_INT_MAX, $height));

        return min(
            max(1, $this->numCols),
            max(1, $this->bounds->width()),
            $maxByPageSize,
        );
    }

    private function pageSize(): int
    {
        return max(1, $this->bounds->height()) * $this->effectiveColumnCount();
    }

}
