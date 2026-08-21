<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/** Typed replace-dialog data, analogous to TReplaceDialogRec. */
final readonly class ReplaceRequest
{
    public function __construct(
        public string $find,
        public string $replace,
        public int $options = SearchOptions::CaseSensitive | SearchOptions::ReplaceAll,
    ) {}
}
