<?php

declare(strict_types=1);

/*
 * BIOS Setup Utility demo — PHP port / TurboVision example.
 *
 * Run: php examples/php/bios.php
 *
 * Keys:
 *   ↑/↓        Move between fields
 *   ←/→        Switch tab
 *   Enter      Edit text/number field; cycle Cycle field; activate Action
 *   +/-        Cycle field forward/backward
 *   Backspace  Delete char when editing
 *   F10        "Save" (shows status message)
 *   Esc        Cancel edit / Exit
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use HelgeSverre\TurboVision\Examples\Bios\BiosApp;

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new BiosApp())->run());
}
