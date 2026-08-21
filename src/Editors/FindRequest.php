<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/** Typed find-dialog data, analogous to TFindDialogRec. */
final readonly class FindRequest
{
    public function __construct(
        public string $find,
        public int $options = SearchOptions::CaseSensitive,
        public bool $wrap = true,
    ) {}
}
