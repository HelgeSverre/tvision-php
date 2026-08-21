<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\State;

/** Startup splash dialog, directly modelled after splash.cc. */
final class SplashApp extends Application
{
    private ?Dialog $splash = null;

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = parent::initDeskTop($bounds);
        assert($desktop instanceof Desktop);
        $this->splash = $this->createSplashDialog();
        $desktop->insertWindow($this->splash);

        return $desktop;
    }

    public function createSplashDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(0, 0, 39, 13), 'About');
        $dialog->options |= State::Centered;
        $dialog->insert(StaticText::centered(Rect::of(6, 2, 33, 9), "Turbo Vision Demo\n\nPHP edition\n\nA reusable terminal UI framework"));
        $dialog->insert(new Button(Rect::of(14, 10, 26, 12), ' OK', Cmd::Ok, ButtonFlag::Default));

        return $dialog;
    }

    public function splashForTest(): Dialog
    {
        assert($this->splash instanceof Dialog);

        return $this->splash;
    }
}
