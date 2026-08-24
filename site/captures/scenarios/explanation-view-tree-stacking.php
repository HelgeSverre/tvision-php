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
    id: 'explanation/view-tree-stacking',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);

            $inventory = new Window(Rect::of(5, 3, 57, 18), 'Inventory', 1);
            $inventory->insert(new StaticText(
                Rect::of(3, 3, 45, 10),
                "Inserted first.\n\nIts frame is inactive where Search overlaps it.",
            ));
            $desktop->insertWindow($inventory);

            $search = new Window(Rect::of(25, 7, 76, 21), 'Search', 2);
            $search->insert(new StaticText(
                Rect::of(3, 3, 45, 9),
                "Inserted last and selected.\n\nThe active frame is drawn above Inventory.",
            ));
            $desktop->insertWindow($search);

            return $desktop;
        }
    },
);
