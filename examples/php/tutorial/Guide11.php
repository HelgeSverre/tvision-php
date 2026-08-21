<?php

declare(strict_types=1);

/*
 * Guide11 — PHP port of tvguid11.cc. Extends the twin-scroller demo with a
 * Window > Dialog command that inserts a normal (non-modal) Dialog.
 */

use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Support\SizeLimits;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide09.php';

const CM_G11_NEW_DIALOG = Cmd::FirstSafeUser + 2; // original cmNewDialog = 202

final class Guide11Window extends Guide09Window
{
    public function sizeLimits(): SizeLimits
    {
        $limits = parent::sizeLimits();
        $leftWidth = $this->leftPane?->getBounds()->width() ?? 0;

        return new SizeLimits(max($limits->minWidth, $leftWidth + 9), $limits->minHeight, $limits->maxWidth, $limits->maxHeight);
    }
}

class Guide11App extends Guide09App
{
    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $win = new Guide11Window($bounds, 'Demo Window', $number, $this->lines);
        $this->lastWindow = $win;

        return $win;
    }

    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar(
            $bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('~O~pen', CM_G4_FILE_OPEN, Key::F3, 'F3'),
                new MenuItem('~N~ew', CM_G4_NEW_WIN, Key::F4, 'F4'),
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
            new SubMenu('~W~indow', Key::AltW)->items(
                new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
                new MenuItem('~Z~oom', Cmd::Zoom, Key::F5, 'F5'),
                new MenuItem('~D~ialog', CM_G11_NEW_DIALOG, Key::F2, 'F2'),
            ),
        );
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->what === EventType::Command
            && $event->asMessage()?->command === CM_G11_NEW_DIALOG
        ) {
            $this->handleNewDialog();
            $this->clearEvent($event);
        }
    }

    protected function handleNewDialog(): void
    {
        $this->openDialog();
    }

    /** Insert the Guide11 dialog: deliberately non-modal, as in tvguid11.cc. */
    public function openDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(20, 6, 60, 19), 'Demo Dialog');
        $this->desktopForTest()?->insertWindow($dialog);

        return $dialog;
    }

    public function openDialogForTest(): Dialog
    {
        return $this->openDialog();
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide11App())->run());
}
