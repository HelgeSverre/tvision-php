<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'reference/core-menu-event',
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(14, 5, 66, 17), 'Event target', 1);
            $window->insert(StaticText::centered(
                Rect::of(3, 3, 49, 7),
                'The open menu is an event-driven overlay.',
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }

        protected function initMenuBar(Rect $bounds): MenuBar
        {
            return new MenuBar(
                $bounds,
                new SubMenu('~F~ile', Key::AltF)->items(
                    new MenuItem('~N~ew', Cmd::New),
                    new MenuItem('~O~pen...', Cmd::Open),
                    new MenuItem('~S~ave', Cmd::Save),
                    MenuItem::separator(),
                    new MenuItem('E~x~it', Cmd::Quit, Key::AltX),
                ),
                new SubMenu('~W~indow', Key::AltW)->items(
                    new MenuItem('~N~ext', Cmd::Next),
                    new MenuItem('~P~revious', Cmd::Prev),
                ),
            );
        }
    },
    prepare: static function (Application $application): void {
        $application->handleEvent(Event::key(Key::AltF));
    },
);
