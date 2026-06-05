<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * The backdrop Group (faithful to TDeskTop). Occupies the area between the menu bar
 * and the status line, and owns a Background filling its extent. Hosts windows in M2.
 */
class Desktop extends Group
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->insert($this->initBackground());
    }

    protected function initBackground(): Background
    {
        $extent = $this->getExtent();

        return new Background($extent);
    }
}
