<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Legacy\LifeApp;

require_once __DIR__ . '/../../../vendor/autoload.php';

exit((new LifeApp())->run());
