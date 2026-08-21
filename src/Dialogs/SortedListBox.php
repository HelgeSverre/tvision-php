<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use Stringable;

/** A ListBox with case-insensitive incremental prefix search. */
class SortedListBox extends ListBox
{
    private string $search = '';

    public function __construct(Rect $bounds, int $numCols = 1, ?ScrollBar $scrollBar = null)
    {
        parent::__construct($bounds, $numCols, $scrollBar);
        $this->showCursor();
        $this->setCursor(1, 0);
    }

    public function searchTerm(): string
    {
        return $this->search;
    }

    /** @param iterable<string|Stringable> $items */
    public function newList(iterable $items): void
    {
        parent::newList($items);
        $this->search = '';
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what !== EventType::KeyDown) {
            return;
        }

        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        if ($key->keyCode === Key::Backspace->value) {
            if ($this->search !== '') {
                $this->search = mb_substr($this->search, 0, mb_strlen($this->search) - 1);
                $this->focusPrefix($this->search);
                $this->clearEvent($event);
            }

            return;
        }
        if ($key->char === '' || preg_match('/^\p{C}$/u', $key->char) === 1) {
            return;
        }

        $candidate = $this->search . $key->char;
        if ($this->focusPrefix($candidate)) {
            $this->search = $candidate;
            $this->clearEvent($event);
        }
    }

    protected function searchText(int $item): string
    {
        return rtrim($this->getText($item, PHP_INT_MAX), '/');
    }

    private function focusPrefix(string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }
        for ($item = 0; $item < $this->range; $item++) {
            if (str_starts_with(mb_strtolower($this->searchText($item)), mb_strtolower($prefix))) {
                $this->focusItem($item);
                $this->setCursor(min($this->bounds->width() - 1, mb_strlen($prefix) + 1), 0);

                return true;
            }
        }

        return false;
    }
}
