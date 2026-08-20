<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Workbench\WorkbenchApp;

require_once __DIR__ . '/../../vendor/autoload.php';

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(new WorkbenchApp()->run());
}
