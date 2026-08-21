<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Legacy\TveditApp;

require_once __DIR__ . '/../../../vendor/autoload.php';

exit((new TveditApp())->run());
