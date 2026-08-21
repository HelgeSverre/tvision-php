<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/** Cursor/selection state stored alongside a reversible text edit. */
final readonly class EditorStateSnapshot
{
    public function __construct(
        public int $cursor,
        public int $selectionStart,
        public int $selectionEnd,
        public bool $modified,
    ) {}
}
