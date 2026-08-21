<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;

/**
 * Abstract scrollable list (faithful to TListViewer). Subclasses supply getText().
 * Tracks focused/topItem/range across one or more columns, navigates by keyboard and
 * mouse, draws items (highlighting the focused one when selected+active), reacts to a
 * vertical scroll bar's cmScrollBarChanged, and broadcasts cmListItemSelected.
 */
abstract class ListViewer extends View
{
    /** cpListViewer: 1=active, 2=inactive, 3=focused, 4=selected, 5=divider. */
    private const string PALETTE = "\x1A\x1A\x1B\x1C\x1D";

    public int $focused = 0;

    public int $topItem = 0;

    public int $range = 0;

    private int $mouseAutoCount = 0;

    public function __construct(
        Rect $bounds,
        public int $numCols = 1,
        protected ?ScrollBar $hScrollBar = null,
        protected ?ScrollBar $vScrollBar = null,
    ) {
        parent::__construct($bounds);
        $this->numCols = $this->effectiveColumnCount();
        $this->options |= State::Selectable | State::FirstClick;
        $this->eventMask |= EventMask::Mouse | EventMask::Broadcast;

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

    public function changeBounds(Rect $bounds): void
    {
        parent::changeBounds($bounds);
        $columns = $this->effectiveColumnCount();
        $this->hScrollBar?->setStep(intdiv($bounds->width(), $columns), $this->hScrollBar->arrowStep);
        $this->vScrollBar?->setStep(max(0, $bounds->height()), $this->vScrollBar->arrowStep);
    }

    /** Override to react to a chosen item; the default broadcasts cmListItemSelected. */
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
        if ($event->what === EventType::MouseDown || $this->getState(State::Dragging)) {
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
        } elseif ($event->isCommand(Cmd::ScrollBarClicked)
            && ($event->asMessage()?->info === $this->hScrollBar
                || $event->asMessage()?->info === $this->vScrollBar)
        ) {
            $this->focus();
        }
    }

    private function handleMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        if ($event->what === EventType::MouseDown) {
            $this->mouseAutoCount = 0;
            $newItem = $this->itemAt($mouse->where) ?? $this->focused;
            $this->focusItemNum($newItem);
            $this->drawView();
            if ($mouse->doubleClick && $this->range > $newItem) {
                $this->selectItem($newItem);
            } else {
                $this->setState(State::Dragging, true);
            }
            $this->clearEvent($event);

            return;
        }

        if (! $this->getState(State::Dragging)) {
            return;
        }

        $newItem = $this->itemAt($mouse->where);
        if ($newItem === null && $event->what === EventType::MouseAuto) {
            $this->mouseAutoCount++;
            if ($this->mouseAutoCount === 4) {
                $this->mouseAutoCount = 0;
                $newItem = $this->autoScrollItem($mouse->where);
            }
        } elseif ($newItem !== null) {
            $this->mouseAutoCount = 0;
        }
        if ($newItem !== null) {
            $this->focusItemNum($newItem);
            $this->drawView();
        }
        if ($event->what === EventType::MouseUp) {
            if ($mouse->doubleClick && $newItem !== null && $newItem < $this->range) {
                $this->selectItem($newItem);
            }
            $this->setState(State::Dragging, false);
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
        $newItem = match (true) {
            $key->keyCode === Key::PageUp->value && ($key->modifiers & KeyModifier::Ctrl) !== 0 => 0,
            $key->keyCode === Key::PageDown->value && ($key->modifiers & KeyModifier::Ctrl) !== 0 => $this->range - 1,
            $key->keyCode === Key::Up->value => IntMath::add($this->focused, -1),
            $key->keyCode === Key::Down->value => IntMath::add($this->focused, 1),
            $key->keyCode === Key::PageUp->value => IntMath::add($this->focused, -$pageSize),
            $key->keyCode === Key::PageDown->value => IntMath::add($this->focused, $pageSize),
            $key->keyCode === Key::Home->value => $this->topItem,
            $key->keyCode === Key::End->value => IntMath::add($this->topItem, $pageSize - 1),
            $key->keyCode === Key::Right->value => $numCols > 1 ? IntMath::add($this->focused, $height) : null,
            $key->keyCode === Key::Left->value => $numCols > 1 ? IntMath::add($this->focused, -$height) : null,
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

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & (State::Selected | State::Active | State::Visible)) !== 0) {
            $visible = $this->getState(State::Active) && $this->getState(State::Visible);
            $this->hScrollBar?->setState(State::Visible, $visible);
            $this->vScrollBar?->setState(State::Visible, $visible);
            $this->drawView();
        }
    }

    private function itemAt(\HelgeSverre\TurboVision\Geometry\Point $where): ?int
    {
        if (! $this->mouseInView($where)) {
            return null;
        }
        $numCols = $this->effectiveColumnCount();
        $colWidth = intdiv($this->bounds->width(), $numCols) + 1;
        $local = $this->makeLocal($where);
        $column = max(0, min($numCols - 1, intdiv($local->x, $colWidth)));

        return IntMath::add(
            IntMath::add($this->topItem, $this->bounds->height() * $column),
            $local->y,
        );
    }

    private function autoScrollItem(\HelgeSverre\TurboVision\Geometry\Point $where): ?int
    {
        $local = $this->makeLocal($where);
        $height = max(1, $this->bounds->height());
        if ($this->effectiveColumnCount() === 1) {
            return match (true) {
                $local->y < 0 => $this->focused - 1,
                $local->y >= $height => $this->focused + 1,
                default => null,
            };
        }

        return match (true) {
            $local->x < 0 => $this->focused - $height,
            $local->x >= $this->bounds->width() => $this->focused + $height,
            $local->y < 0 => $this->focused - $this->focused % $height,
            $local->y >= $height => $this->focused - $this->focused % $height + $height - 1,
            default => null,
        };
    }

}
