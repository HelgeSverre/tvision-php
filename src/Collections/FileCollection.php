<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Collections;

/**
 * Sorted file entries: parent first, then directories, then files.
 *
 * @extends SortedCollection<SearchRec>
 */
final class FileCollection extends SortedCollection
{
    public function __construct()
    {
        parent::__construct(self::compare(...));
    }

    public static function compare(SearchRec $left, SearchRec $right): int
    {
        if ($left->name === $right->name) {
            return 0;
        }
        if ($left->name === '..') {
            return -1;
        }
        if ($right->name === '..') {
            return 1;
        }
        if ($left->isDirectory() !== $right->isDirectory()) {
            return $left->isDirectory() ? -1 : 1;
        }

        return strnatcasecmp($left->name, $right->name);
    }

    /** @return list<SearchRec> */
    public function all(): array
    {
        return parent::all();
    }
}
