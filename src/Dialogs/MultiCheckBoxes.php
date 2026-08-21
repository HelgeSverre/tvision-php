<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Geometry\Rect;

/** A compact multi-state bit-field cluster (Turbo Vision's TMultiCheckBoxes). */
final class MultiCheckBoxes extends Cluster
{
    /** @param list<string>|SItem|null $items */
    public function __construct(
        Rect $bounds,
        SItem|array|null $items,
        public int $selectionRange,
        public int $flags,
        public string $states,
    ) {
        parent::__construct($bounds, $items);
    }

    public function dataSize(): int
    {
        return 4;
    }

    public function draw(): void
    {
        $this->drawMultiBox(' [ ] ', $this->states);
    }

    public function multiMark(int $item): int
    {
        $bits = max(1, $this->flags >> 8);
        $mask = $this->flags & 0xff;

        return ($this->value >> ($item * $bits)) & $mask;
    }

    public function press(int $item): void
    {
        $bits = max(1, $this->flags >> 8);
        $mask = $this->flags & 0xff;
        $current = $this->multiMark($item);
        $next = ($current - 1 + max(1, $this->selectionRange)) % max(1, $this->selectionRange);
        $shift = $item * $bits;
        $this->value = ($this->value & ~($mask << $shift)) | ($next << $shift);
    }
}
