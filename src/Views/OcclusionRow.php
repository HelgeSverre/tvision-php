<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Support\IntMath;

/** @internal Shared row clipping for View and Group occlusion calculations. */
final class OcclusionRow
{
    private function __construct() {}

    /** @return null|array{0:int,1:int} */
    public static function clip(View $view, int $globalY, int $minX, int $maxX): ?array
    {
        if (! $view->getState(State::Visible) || $minX >= $maxX) {
            return null;
        }

        $origin = $view->absoluteOrigin();
        $bounds = $view->getBounds();
        $bottom = IntMath::add($origin->y, $bounds->height());
        if ($globalY < $origin->y || $globalY >= $bottom) {
            return null;
        }

        $start = max($minX, $origin->x);
        $end = min($maxX, IntMath::add($origin->x, $bounds->width()));

        return $start < $end ? [$start, $end] : null;
    }
}
