<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/** A small window hosting a HistoryViewer. */
final class HistoryWindow extends Window
{
    public readonly HistoryViewer $viewer;

    public function __construct(Rect $bounds, public int $historyId)
    {
        parent::__construct($bounds, '');
        $this->flags = WindowFlags::Close;
        $extent = $this->getExtent();
        $vertical = $this->standardScrollBar(ScrollBarOrientation::Vertical);
        $this->viewer = new HistoryViewer(
            Rect::of(1, 1, max(1, $extent->b->x - 1), max(1, $extent->b->y - 1)),
            null,
            $vertical,
            $historyId,
        );
        $this->insert($this->viewer);
    }

    public function getSelection(): string
    {
        return $this->viewer->selection();
    }
}
