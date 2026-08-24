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
    id: 'reference/special-html-headless',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(12, 4, 68, 17), 'Headless render', 1);
            $window->insert(StaticText::centered(
                Rect::of(2, 2, 54, 10),
                "One deterministic back buffer.\n\nHeadlessDriver captures ANSI output;\nHtmlRenderer turns the same cells into HTML.",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }
    },
);
