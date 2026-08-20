<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Fills its whole extent with one pattern character (faithful to TBackground). The
 * default is the CP437 light-shade '░' (sparse light specks). With the modern dark
 * application palette this becomes a subtle graphite texture on black. Pass a
 * different pattern (e.g. a space for a flat desktop, or '▒'/'▓' for denser
 * texture). The classic blue appearance remains available through
 * Palettes::CLASSIC_COLOR.
 */
class Background extends View
{
    public const string DEFAULT_PATTERN = '░';

    public function __construct(Rect $bounds, protected string $pattern = self::DEFAULT_PATTERN)
    {
        parent::__construct($bounds);
    }

    /** cpBackground "\x01": one palette entry -> the backdrop attribute. */
    public function getPalette(): ?Palette
    {
        return new Palette([1 => 0x01]);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $attr = $this->mapColor(1);

        $b = new DrawBuffer($width);
        $b->moveChar(0, $this->pattern, $attr, $width);
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }
}
