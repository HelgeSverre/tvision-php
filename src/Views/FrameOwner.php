<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

/** What a Frame needs from its owning Window to draw itself. Implemented by Window. */
interface FrameOwner
{
    public function frameTitle(): string;

    public function frameFlags(): int;

    public function frameNumber(): int;

    public function frameIsZoomed(): bool;
}
