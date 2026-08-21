<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Collections\DirCollection;
use HelgeSverre\TurboVision\Collections\DirEntry;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Single-column current-path and child-directory tree. */
final class DirListBox extends ListBox
{
    private DirCollection $directories;

    public function __construct(Rect $bounds, ?ScrollBar $scrollBar = null)
    {
        parent::__construct($bounds, 1, $scrollBar);
        $this->directories = new DirCollection();
    }

    public function directories(): DirCollection
    {
        return $this->directories;
    }

    public function entry(int $item): ?DirEntry
    {
        return $this->directories->at($item);
    }

    public function getText(int $item, int $maxLen): string
    {
        $entry = $this->entry($item);

        return TerminalText::slice($entry === null ? '' : $entry->text, 0, max(0, $maxLen));
    }

    public function isSelected(int $item): bool
    {
        return $item === $this->focused;
    }

    public function selectItem(int $item): void
    {
        $entry = $this->entry($item);
        if ($entry !== null) {
            $this->owner?->handleEvent(Event::command(FileCommand::ChangeDir, $entry));
        }
    }

    public function newDirectory(string $directory): void
    {
        $directory = FilePath::existingDirectory($directory);
        $entries = new DirCollection();
        $parts = array_values(array_filter(explode('/', trim($directory, '/')), static fn (string $part): bool => $part !== ''));
        $entries->insert(new DirEntry('/', DIRECTORY_SEPARATOR));
        $path = '';
        foreach ($parts as $depth => $part) {
            $path .= '/' . $part;
            $entries->insert(new DirEntry(str_repeat('  ', $depth) . '└─ ' . $part, $path));
        }
        $current = max(0, $entries->count() - 1);

        $children = [];
        $names = is_readable($directory) ? @scandir($directory) : false;
        if (is_array($names)) {
            foreach ($names as $name) {
                if ($name !== '.' && $name !== '..' && !str_starts_with($name, '.')) {
                    $path = FilePath::join($directory, $name);
                    if (is_dir($path)) {
                        $children[] = $name;
                    }
                }
            }
        }
        natcasesort($children);
        foreach (array_values($children) as $index => $name) {
            $branch = $index === array_key_last($children) ? '└─ ' : '├─ ';
            $entries->insert(new DirEntry(str_repeat('  ', count($parts)) . $branch . $name, FilePath::join($directory, $name)));
        }

        $this->directories = $entries;
        parent::newList(array_map(static fn (DirEntry $entry): string => $entry->text, $entries->all()));
        $this->focusItemNum($current);
    }
}
