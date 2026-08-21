<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Collections;

/** Path and rendered tree label used by DirListBox. */
final readonly class DirEntry
{
    public function __construct(
        public string $text,
        public string $dir,
    ) {
    }
}
