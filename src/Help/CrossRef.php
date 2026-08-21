<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

/** A selectable span in a topic, addressed in grapheme offsets across its paragraphs. */
final readonly class CrossRef
{
    public function __construct(
        public int $ref,
        public int $offset,
        public int $length,
        public ?string $label = null,
    ) {
        if ($this->offset < 0 || $this->length < 1) {
            throw new \InvalidArgumentException('A help cross-reference needs a non-negative offset and positive length.');
        }
    }

    /** @return array{ref:int,offset:int,length:int,label:?string} */
    public function toArray(): array
    {
        return ['ref' => $this->ref, 'offset' => $this->offset, 'length' => $this->length, 'label' => $this->label];
    }
}
