<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Legacy\NoMenusApp;

require_once __DIR__ . '/../../../vendor/autoload.php';

exit((new NoMenusApp())->runOrder());
