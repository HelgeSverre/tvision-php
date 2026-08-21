<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Workbench\WorkbenchApp;

require_once __DIR__ . '/../../vendor/autoload.php';

if (WorkbenchApp::runningAsMain(__FILE__)) {
    exit((new WorkbenchApp())->run());
}
