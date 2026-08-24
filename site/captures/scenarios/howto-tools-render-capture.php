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
    id: 'howto-tools/render-capture',
    columns: 100,
    rows: 28,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $report = new Window(Rect::of(8, 3, 92, 24), 'Inventory report', 1);
            $report->insert(StaticText::centered(
                Rect::of(3, 2, 78, 16),
                "Stock at close of business\n\n"
                . " SKU       Description                 On hand   Reorder\n"
                . " ----------------------------------------------------------\n"
                . " A-100     Terminal cable                   42       no\n"
                . " B-205     Blue floppy disks                 8       yes\n"
                . " C-410     Desk reference stand             16       no\n\n"
                . " 3 products shown · 1 item needs attention",
            ));
            $desktop->insertWindow($report);

            return $desktop;
        }
    },
);
