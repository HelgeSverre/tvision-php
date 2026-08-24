<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'how-to/ui-events',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        private const int Refresh = Cmd::FirstSafeUser;
        private const int Export = Cmd::FirstSafeUser + 1;

        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(11, 4, 69, 18), 'Quarterly report', 1);
            $window->insert(new StaticText(
                Rect::of(3, 2, 53, 10),
                "Revenue summary\n\nLast refreshed: just now\nRows loaded: 48\n\nThe Export command is unavailable until a report is saved.",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }

        protected function initMenuBar(Rect $bounds): ?MenuBar
        {
            return new MenuBar(
                $bounds,
                new SubMenu('~R~eport', Key::AltR)->items(
                    new MenuItem('~R~efresh', self::Refresh, Key::F5, 'Reload the report'),
                    new MenuItem('~E~xport', self::Export, Key::F6, 'Export the report'),
                ),
                new SubMenu('~F~ile', Key::AltF)->items(
                    new MenuItem('E~x~it', Cmd::Quit, Key::AltX),
                ),
            );
        }

        protected function initStatusLine(Rect $bounds): ?StatusLine
        {
            return new StatusLine($bounds, StatusDef::all(
                new StatusItem('~F5~ Refresh', Key::F5, self::Refresh),
                new StatusItem('~F6~ Export', Key::F6, self::Export),
                new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            ));
        }

        public function showDisabledExport(): void
        {
            $this->disableCommand(self::Export);
            $this->menuBar?->handleEvent(Event::key(Key::AltR));
        }
    },
    prepare: static function (Application $application): void {
        /** @var Application&object{showDisabledExport(): void} $application */
        $application->showDisabledExport();
    },
);
