<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/** @internal Parsed atom in a PictureValidator mask. */
final readonly class PictureNode
{
    /**
     * @param list<list<PictureNode>> $alternatives
     * @param int<0, max>|null $repeatCount Null (or zero) means unbounded repetition.
     */
    public function __construct(
        public string $type,
        public ?string $value = null,
        public bool $optional = false,
        public array $alternatives = [],
        public ?self $node = null,
        public ?int $repeatCount = null,
    ) {}
}
