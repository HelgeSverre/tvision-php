<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** A cluster where exactly one item is active (Turbo Vision's TRadioButtons). */
final class RadioButtons extends Cluster
{
    public function draw(): void
    {
        $this->drawMultiBox(' ( ) ', ' .');
    }

    public function mark(int $item): bool
    {
        return $this->value === $item;
    }

    public function press(int $item): void
    {
        $this->value = $item;
    }

    public function movedTo(int $item): void
    {
        $this->value = $item;
    }

    public function setData(mixed $data): void
    {
        parent::setData($data);
        // Keep the stored index inside the item list so exactly one row renders
        // selected and the cursor cannot sit apart from it.
        $last = max(0, count($this->items) - 1);
        $this->value = max(0, min($last, $this->value));
        $this->sel = $this->value;
    }
}
