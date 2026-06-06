<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Bios;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Desktop;

/**
 * A Desktop variant whose background is pure black (0x00).
 * The BiosScreen sits on top and fills the whole screen, so this backdrop
 * is never visible; it exists only to satisfy the view-tree hierarchy.
 */
final class BlackDesktop extends Desktop
{
    protected function initBackground(): Background
    {
        $extent = $this->getExtent();

        return new BlackBackground($extent);
    }
}

/**
 * A Background that renders as space-on-black (attr 0x00).
 */
final class BlackBackground extends Background
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds, ' ');
    }

    public function getPalette(): ?Palette
    {
        return null; // bypass palette chain
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $b     = new DrawBuffer($width);
        $b->moveChar(0, ' ', 0x00, $width);
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }
}
