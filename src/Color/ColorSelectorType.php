<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

/** Which half of a classic terminal attribute a selector changes. */
enum ColorSelectorType: int
{
    case Background = 0;
    case Foreground = 1;

    public function maximum(): int
    {
        return $this === self::Foreground ? 15 : 7;
    }

    public function changedCommand(): int
    {
        return $this === self::Foreground
            ? ColorCommand::ForegroundChanged
            : ColorCommand::BackgroundChanged;
    }
}
