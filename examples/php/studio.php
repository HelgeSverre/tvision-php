<?php

declare(strict_types=1);

/*
 * Turbo Studio — visual interface builder and PHP app generator.
 *
 * Usage:
 *   php examples/php/studio.php [project.json] [generated.php]
 *
 * Essential keys:
 *   Tab / Shift-Tab       Move between toolbox, canvas, and inspector
 *   Arrows                Select tools/properties or move a component
 *   Enter                 Add a tool or edit an inspector property
 *   +/-                   Resize the selected component
 *   Ctrl-S / Ctrl-O       Save/load the project
 *   Ctrl-Z / Ctrl-Y       Undo/redo
 *   F2 / F5 / F9          Theme / preview / generated code
 *   Q / Alt-X / Ctrl-Q    Quit
 */

use HelgeSverre\TurboVision\Examples\Studio\StudioApp;

require_once __DIR__ . '/../../vendor/autoload.php';

final class TurboStudioDemoApp extends StudioApp {}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    $projectPath = $argv[1] ?? getcwd() . '/studio-project.json';
    $exportPath = $argv[2] ?? getcwd() . '/studio-generated.php';
    exit((new TurboStudioDemoApp(projectPath: $projectPath, exportPath: $exportPath))->run());
}
