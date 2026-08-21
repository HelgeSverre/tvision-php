<?php

declare(strict_types=1);

/*
 * Guide01 — PHP port of Turbo Vision's tvguid01.cc (Borland, 1991).
 * The smallest complete app: inherited defaults supply an empty desktop, menu bar,
 * and status line. Run directly to launch on a real terminal; `require` it from a
 * test to use Guide01App headlessly.
 */

use HelgeSverre\TurboVision\Application\Application;

require_once __DIR__ . '/../../../vendor/autoload.php';

final class Guide01App extends Application {}

if (Guide01App::runningAsMain(__FILE__)) {
    exit((new Guide01App())->run());
}
