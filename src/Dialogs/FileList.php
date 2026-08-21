<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Collections\FileCollection;
use HelgeSverre\TurboVision\Collections\SearchRec;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Two-column filesystem list with directory navigation and file broadcasts. */
final class FileList extends SortedListBox
{
    private FileCollection $files;

    public function __construct(Rect $bounds, ?ScrollBar $scrollBar = null)
    {
        parent::__construct($bounds, 2, $scrollBar);
        $this->files = new FileCollection();
    }

    public function files(): FileCollection
    {
        return $this->files;
    }

    public function entry(int $item): ?SearchRec
    {
        return $this->files->at($item);
    }

    public function getText(int $item, int $maxLen): string
    {
        $entry = $this->entry($item);
        if ($entry === null) {
            return '';
        }

        return TerminalText::slice($entry->name . ($entry->isDirectory() ? '/' : ''), 0, max(0, $maxLen));
    }

    public function focusItem(int $item): void
    {
        parent::focusItem($item);
        $this->owner?->handleEvent(Event::broadcast(FileCommand::Focused, $this->entry($item)));
    }

    public function selectItem(int $item): void
    {
        $this->owner?->handleEvent(Event::broadcast(FileCommand::DoubleClicked, $this->entry($item)));
    }

    public function readDirectory(string $directory, string $wildCard = '*'): void
    {
        $directory = FilePath::existingDirectory($directory);
        $files = new FileCollection();
        $names = is_readable($directory) ? @scandir($directory) : false;

        if (is_array($names)) {
            foreach ($names as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                if (str_starts_with($name, '.') && !str_starts_with($wildCard, '.')) {
                    continue;
                }
                $path = FilePath::join($directory, $name);
                if (is_dir($path) || FilePath::matches($name, $wildCard)) {
                    $files->insert(SearchRec::fromPath($path, $name));
                }
            }
        }
        if ($directory !== dirname($directory)) {
            $files->insert(SearchRec::parent($directory));
        }

        $this->files = $files;
        parent::newList(array_map(static fn (SearchRec $entry): string => $entry->name, $files->all()));
        if ($files->count() > 0) {
            $this->focusItem(0);
        } else {
            $this->owner?->handleEvent(Event::broadcast(FileCommand::Focused));
        }
    }
}
