#!/usr/bin/env php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\OpenCode\OpenCodeApp;

require_once __DIR__ . '/../../vendor/autoload.php';

if (OpenCodeApp::runningAsMain(__FILE__)) {
    exit((new OpenCodeApp())->run());
}
