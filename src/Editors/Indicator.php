<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\View;

/** The compact line/column and modified-state readout used by an EditWindow. */
class Indicator extends View
{
    private const string PALETTE = "\x06\x07";

    public Point $location;

    public bool $modified = false;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->location = new Point(0, 0);
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function setValue(Point $location, bool $modified): void
    {
        if ($this->location->equals($location) && $this->modified === $modified) {
            return;
        }
        $this->location = $location;
        $this->modified = $modified;
        $this->drawView();
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $b = new DrawBuffer($width);
        $normal = $this->getColor(1);
        $changed = $this->getColor(2);
        $b->moveChar(0, ' ', $normal, $width);
        $text = sprintf('%s%02d:%02d', $this->modified ? '*' : ' ', $this->location->y + 1, $this->location->x + 1);
        $b->moveStr(0, TerminalText::slice($text, 0, $width), $this->modified ? $changed : $normal);
        $this->writeLine(0, 0, $width, $this->bounds->height(), $b);
    }
}
