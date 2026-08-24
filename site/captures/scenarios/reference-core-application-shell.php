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
    id: 'reference/core-application-shell',
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(11, 4, 69, 18), 'Application root', 1);
            $window->insert(StaticText::centered(
                Rect::of(3, 2, 55, 10),
                "The menu bar occupies the first row.\n\n"
                . "The desktop owns the working area.\n\n"
                . "The status line occupies the last row.",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }
    },
);
