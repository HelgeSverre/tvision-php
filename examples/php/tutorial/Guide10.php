<?php

declare(strict_types=1);

/*
 * Guide10 — PHP port of tvguid10.cc. Same dual-pane window as Guide09 plus a sizeLimits()
 * override that floors the minimum width at leftPane.width + 9.
 */

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\SizeLimits;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide09.php';

final class Guide10Window extends Guide09Window
{
    /** Floor minimum width at the left pane width + 9 (faithful to tvguid10). */
    public function sizeLimits(): SizeLimits
    {
        $limits = parent::sizeLimits();
        $leftWidth = $this->leftPane?->getBounds()->width() ?? 0;

        return new SizeLimits(max($limits->minWidth, $leftWidth + 9), $limits->minHeight, $limits->maxWidth, $limits->maxHeight);
    }
}

final class Guide10App extends Guide09App
{
    private ?Guide10Window $guide10Window = null;

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $win = new Guide10Window($bounds, 'Demo Window', $number, $this->lines);
        $this->lastWindow = $win;
        $this->guide10Window = $win;

        return $win;
    }

    public function lastWindowForTest(): Guide10Window
    {
        assert($this->guide10Window !== null);

        return $this->guide10Window;
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide10App())->run());
}
