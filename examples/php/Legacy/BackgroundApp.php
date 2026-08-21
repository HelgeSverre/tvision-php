<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Desktop;

/**
 * Compact port of the original background.cc tutorial.
 *
 * It demonstrates that applications can replace the desktop backdrop without
 * custom drawing: the reusable Background view accepts any one-cell pattern.
 */
final class BackgroundApp extends Application
{
    protected function initDeskTop(Rect $bounds): Desktop
    {
        return new class($bounds) extends Desktop {
            protected function initBackground(): Background
            {
                return new Background($this->getExtent(), '?');
            }
        };
    }
}
