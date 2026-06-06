<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Fills its whole extent with one pattern character (faithful to TBackground).
 *
 * Glyph choice — "faithful core, modern skin": the palette attribute (0x71, blue ink
 * on a light-gray field) is byte-faithful and unchanged. The original CP437 light-shade
 * 0xB0 ('░', 25% dots) blended into a smooth blue desktop on a blurry CRT, but renders
 * as sharp blue-on-gray speckle on a crisp modern font. We default to the dark-shade
 * 0xB2 ('▓', 75% ink) so the blue dominates — reproducing the *appearance* a TV user saw
 * on hardware. Pass a different pattern (e.g. '░' or '▒') to the constructor for the
 * literal retro dither.
 */
class Background extends View
{
    public const string DEFAULT_PATTERN = '▓';

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
