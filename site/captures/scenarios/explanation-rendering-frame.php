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
    id: 'explanation/rendering-frame',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(7, 3, 73, 21), 'Rendered frame', 1);
            $window->insert(StaticText::centered(
                Rect::of(3, 3, 63, 13),
                "One retained tree produces this complete frame.\n\n"
                . "The window, frame, text, desktop, menu, and status line\n"
                . "all write their local cells into the same screen buffer.",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }
    },
);
