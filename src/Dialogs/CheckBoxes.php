<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** A cluster of independently toggled bits (Turbo Vision's TCheckBoxes). */
final class CheckBoxes extends Cluster
{
    public function draw(): void
    {
        $this->drawMultiBox(' [ ] ', ' X');
    }

    public function mark(int $item): bool
    {
        return ($this->value & (1 << $item)) !== 0;
    }

    public function press(int $item): void
    {
        $this->value ^= 1 << $item;
    }
}
