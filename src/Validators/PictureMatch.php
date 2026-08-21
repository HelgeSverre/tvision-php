<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/** @internal A single bounded-backtracking state for PictureValidator. */
final readonly class PictureMatch
{
    /** @param list<string> $out */
    public function __construct(
        public int $pos,
        public array $out,
        public bool $complete,
    ) {}
}
