<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\Window;

/** A standard 50×18 scrollable Help window, faithful to THelpWindow. */
final class HelpWindow extends Window
{
    private const string PALETTE = "\x80\x81\x82\x83\x84\x85\x86\x87";

    public readonly HelpViewer $viewer;

    public function __construct(HelpFile $helpFile, int $context)
    {
        parent::__construct(Rect::of(0, 0, 50, 18), 'Help');
        $this->options |= \HelgeSverre\TurboVision\Views\State::Centered;
        $extent = $this->getExtent()->grow(-2, -1);
        $hBar = $this->standardScrollBar(ScrollBarOrientation::Horizontal, true);
        $vBar = $this->standardScrollBar(ScrollBarOrientation::Vertical, true);
        $this->viewer = new HelpViewer($extent, $hBar, $vBar, $helpFile, $context);
        $this->insert($this->viewer);
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }
}
