<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

enum StudioProperty: string
{
    case Text = 'Text';
    case X = 'X';
    case Y = 'Y';
    case Width = 'Width';
    case Height = 'Height';

    public function numeric(): bool
    {
        return $this !== self::Text;
    }
}
