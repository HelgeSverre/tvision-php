<?php

declare(strict_types=1);

/*
 * Guide02 — PHP port of Turbo Vision's tvguid02.cc (Borland, 1991).
 * Adds a custom status line with two items. The original binds "Close" to kbAltF3;
 * M1's Key enum stops at the Alt-letter set, so we bind Close to Esc (the conventional
 * close key) — the intent (a second status item dispatching cmClose) is preserved.
 */

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;

require_once __DIR__ . '/../../../vendor/autoload.php';

final class Guide02App extends Application
{
    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
            new StatusItem('~Alt-F3~ Close', Key::Esc, Cmd::Close),
        ));
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide02App())->run());
}
