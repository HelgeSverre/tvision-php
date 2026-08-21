<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

/** Broadcast command codes used by the colour-selection controls. */
final class ColorCommand
{
    public const int ForegroundChanged = 71;
    public const int BackgroundChanged = 72;
    public const int Set = 73;
    public const int NewItem = 74;
    public const int NewIndex = 75;
    public const int SaveIndex = 76;
}
