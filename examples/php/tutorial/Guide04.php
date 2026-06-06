<?php

declare(strict_types=1);

/*
 * Guide04 — PHP port of Turbo Vision's tvguid04.cc (Borland, 1991).
 * A File>New command opens a bare, movable/resizable/closable/zoomable Window on the
 * desktop. Window position is deterministic (the original used random()) so headless
 * snapshots are stable. Window cmd 201 -> Cmd::FirstUser + 101.
 */

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/../../../vendor/autoload.php';

const CM_G4_FILE_OPEN = Cmd::FirstUser + 100; // 200
const CM_G4_NEW_WIN = Cmd::FirstUser + 101;   // 201

class Guide04App extends Application
{
    private int $winNumber = 0;

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
            ),
        );
    }

    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('', Key::F10, Cmd::Menu),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
        ));
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);

        if ($event->what === EventType::Command
            && $event->asMessage()?->command === CM_G4_NEW_WIN
        ) {
            $this->openNewWindow();
            $this->clearEvent($event);
        }
    }

    /** Build a Window class via factory so subclasses (Guide05+) override the interior. */
    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Window($bounds, 'Demo Window', $number);
    }

    public function openNewWindow(): void
    {
        $this->winNumber++;
        // Deterministic placement (original randomised within 53x16).
        $x = ($this->winNumber * 3) % 50;
        $y = ($this->winNumber * 2) % 14;
        $bounds = Rect::of($x, $y, $x + 26, $y + 7);
        $window = $this->makeWindow($bounds, $this->winNumber);

        $this->desktopForTest()?->insertWindow($window);
    }

    // --- test helpers ---

    public function openNewWindowForTest(): void
    {
        $this->openNewWindow();
    }

    public function closeTopWindowForTest(): void
    {
        $desk = $this->desktopForTest();
        $current = $desk?->current();
        if ($current instanceof Window) {
            $current->close();
        }
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide04App())->run());
}
