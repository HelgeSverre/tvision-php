<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'tutorials/first-application',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(8, 3, 58, 15), 'Hello, PHP', 1);
            $window->insert(StaticText::centered(
                Rect::of(2, 2, 48, 8),
                "TurboVision for PHP lives!\n\nMove, resize, zoom, and close this window.",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }
    },
);
