<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\MessageBox;
use HelgeSverre\TurboVision\Dialogs\MsgBoxFlag;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'tutorials/interactive-application',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        public const int About = Cmd::FirstSafeUser;

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

        protected function initMenuBar(Rect $bounds): MenuBar
        {
            return new MenuBar($bounds, new SubMenu('~H~elp', Key::AltH)->items(
                new MenuItem('~A~bout', self::About, Key::F1, 'About this application'),
            ));
        }
    },
    prepare: static function (Application $application): void {
        $desktop = $application->desktopForTest();
        if ($desktop === null) {
            throw new RuntimeException('Application desktop was not initialized.');
        }

        $dialog = MessageBox::dialog(
            Rect::of(21, 8, 59, 15),
            'Built with TurboVision for PHP.',
            MsgBoxFlag::Information | MsgBoxFlag::OkButton,
        );
        $dialog->setState(State::Modal, true);
        $desktop->insertWindow($dialog);
    },
);
