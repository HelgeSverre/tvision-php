<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

enum StudioFocus
{
    case Toolbox;
    case Canvas;
    case Inspector;

    public function next(int $direction = 1): self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);
        $index = is_int($index) ? $index : 0;
        $count = count($cases);

        return $cases[(($index + $direction) % $count + $count) % $count];
    }
}
