<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ListViewer;
use HelgeSverre\TurboVision\Views\ScrollBar;
use InvalidArgumentException;
use Stringable;

/** General string-list control built on ListViewer (Turbo Vision's TListBox). */
class ListBox extends ListViewer
{
    /** @var list<string> */
    private array $items = [];

    public function __construct(Rect $bounds, int $numCols = 1, ?ScrollBar $scrollBar = null)
    {
        parent::__construct($bounds, $numCols, null, $scrollBar);
    }

    public function getText(int $item, int $maxLen): string
    {
        return TerminalText::slice($this->items[$item] ?? '', 0, max(0, $maxLen));
    }

    /** @param iterable<mixed> $items */
    public function newList(iterable $items): void
    {
        $this->items = [];
        foreach ($items as $item) {
            if (!is_string($item) && !$item instanceof Stringable) {
                throw new InvalidArgumentException('ListBox items must be strings or Stringable values.');
            }
            $this->items[] = (string) $item;
        }
        $this->setRange(count($this->items));
        $this->focusItemNum(0);
        $this->drawView();
    }

    /** @return list<string> */
    public function list(): array
    {
        return $this->items;
    }

    public function dataSize(): int
    {
        return 2;
    }

    /** @return array{collection:list<string>,selection:int} */
    public function getData(): mixed
    {
        return ['collection' => $this->items, 'selection' => $this->focused];
    }

    public function setData(mixed $data): void
    {
        if (is_array($data) && array_key_exists('collection', $data)) {
            $collection = $data['collection'];
            if (!is_iterable($collection)) {
                return;
            }
            $this->newList($collection);
            $selection = $data['selection'] ?? 0;
            $this->focusItemNum(is_int($selection) ? $selection : 0);
            return;
        }
        if (is_iterable($data)) {
            $this->newList($data);
        }
    }
}
