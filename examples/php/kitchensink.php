<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\KitchenSink\KitchenSinkApp;

require_once __DIR__ . '/../../vendor/autoload.php';

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(new KitchenSinkApp()->run());
}
