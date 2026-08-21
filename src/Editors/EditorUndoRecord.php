<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/** A compact, reversible replacement instead of a whole-document undo snapshot. */
final readonly class EditorUndoRecord
{
    public function __construct(
        public int $start,
        public string $removed,
        public int $removedLength,
        public string $inserted,
        public int $insertedLength,
        public EditorStateSnapshot $before,
        public EditorStateSnapshot $after,
    ) {}

    /** Conservative retained payload estimate used to cap undo memory. */
    public function retainedBytes(): int
    {
        return strlen($this->removed) + strlen($this->inserted) + 96;
    }
}
