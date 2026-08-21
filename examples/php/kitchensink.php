<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\KitchenSink\KitchenSinkApp;

require_once __DIR__ . '/../../vendor/autoload.php';

if (KitchenSinkApp::runningAsMain(__FILE__)) {
    exit((new KitchenSinkApp())->run());
}
