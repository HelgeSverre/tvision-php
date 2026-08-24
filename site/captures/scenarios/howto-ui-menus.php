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
    id: 'how-to/ui-menus',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        private const int ThemeDark = Cmd::FirstSafeUser;
        private const int ThemeClassic = Cmd::FirstSafeUser + 1;
        private const int Refresh = Cmd::FirstSafeUser + 2;

        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(9, 4, 71, 19), 'Workspace', 1);
            $window->insert(StaticText::centered(
                Rect::of(3, 3, 59, 10),
                "Use Alt-V to open View.\n\nArrow right opens the Theme pull-right menu.",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }

        protected function initMenuBar(Rect $bounds): ?MenuBar
        {
            return new MenuBar(
                $bounds,
                new SubMenu('~F~ile', Key::AltF)->items(
                    new MenuItem('~O~pen…', Cmd::Open, Key::F3),
                    new MenuItem('~S~ave', Cmd::Save, Key::CtrlS),
                    MenuItem::separator(),
                    new MenuItem('E~x~it', Cmd::Quit, Key::AltX),
                ),
                new SubMenu('~V~iew', Key::AltV)->items(
                    new SubMenu('~T~heme')->items(
                        new MenuItem('~D~ark', self::ThemeDark),
                        new MenuItem('~C~lassic', self::ThemeClassic),
                    ),
                    new MenuItem('~R~efresh', self::Refresh, Key::F5),
                ),
            );
        }

        protected function initStatusLine(Rect $bounds): ?StatusLine
        {
            return new StatusLine($bounds, StatusDef::all(
                new StatusItem('~F1~ Help', Key::F1, Cmd::Help),
                new StatusItem('~F3~ Open', Key::F3, Cmd::Open),
                new StatusItem('~F5~ Refresh', Key::F5, self::Refresh),
                new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            ));
        }

        public function showThemeMenu(): void
        {
            $this->menuBar?->handleEvent(Event::key(Key::AltV));
            $this->menuBar?->handleEvent(Event::key(Key::Right));
        }
    },
    prepare: static function (Application $application): void {
        /** @var Application&object{showThemeMenu(): void} $application */
        $application->showThemeMenu();
    },
);
