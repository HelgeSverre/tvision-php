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
        $this->sel = max(0, min(count($this->items) - 1, $this->value));
    }
}
