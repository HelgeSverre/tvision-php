<?php

declare(strict_types=1);

/*
 * Guide03 — PHP port of Turbo Vision's tvguid03.cc (Borland, 1991).
 * Adds a full menu bar (File: Open/New/Exit; Window: Next/Zoom) and a status line
 * (F10 Menu, Alt-X Exit, Alt-F3 Close). User command codes 200/201 from the original
 * become Cmd::FirstUser + n.
 */

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;

require_once __DIR__ . '/../../../vendor/autoload.php';

const CM_MY_FILE_OPEN = Cmd::FirstUser + 100; // 200
const CM_MY_NEW_WIN = Cmd::FirstUser + 101;   // 201

final class Guide03App extends Application
{
    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar(
            $bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('~O~pen', CM_MY_FILE_OPEN, Key::F3, 'F3'),
                new MenuItem('~N~ew', CM_MY_NEW_WIN, Key::F4, 'F4'),
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
        return new StatusLine($bounds, StatusDef::all(
            new StatusItem('', Key::F10, Cmd::Menu),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            new StatusItem('~Alt-F3~ Close', Key::AltF3, Cmd::Close),
        ));
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide03App())->run());
}
