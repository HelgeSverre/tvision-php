#!/usr/bin/env php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Rendering\HtmlRenderer;
use HelgeSverre\TurboVision\Terminal\Screen;
use TurboVisionDocs\Captures\CaptureScenario;

$siteRoot = dirname(__DIR__);
$repositoryRoot = dirname($siteRoot);

require $repositoryRoot . '/vendor/autoload.php';
require __DIR__ . '/CaptureScenario.php';

/** @return never */
function fail(string $message, int $status = 1): void
{
    fwrite(STDERR, "capture: {$message}\n");
    exit($status);
}

/** @return list<CaptureScenario> */
function loadScenarios(string $directory): array
{
    $scenarios = [];
    $seen = [];
    $files = glob($directory . '/*.php') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $scenario = require $file;
        if (! $scenario instanceof CaptureScenario) {
            fail(basename($file) . ' must return a CaptureScenario');
        }
        if (isset($seen[$scenario->id])) {
            fail("duplicate capture id: {$scenario->id}");
        }

        $seen[$scenario->id] = true;
        $scenarios[] = $scenario;
    }

    return $scenarios;
}

function findChrome(): string
{
    $candidates = [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
    ];

    $path = getenv('PATH');
    foreach (explode(PATH_SEPARATOR, is_string($path) ? $path : '') as $directory) {
        foreach (['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable'] as $binary) {
            $candidates[] = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary;
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    fail('Chrome or Chromium is required');
}

/** @param list<string> $command */
function run(array $command): void
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
    if (! is_resource($process)) {
        fail('could not start Chrome');
    }

    $status = proc_close($process);
    if ($status !== 0) {
        fail("Chrome exited with status {$status}");
    }
}

/** @return never */
function usage(): void
{
    fwrite(STDERR, "Usage: php site/captures/generate.php [--list] [capture-id ...]\n");
    exit(2);
}

$scenarios = loadScenarios(__DIR__ . '/scenarios');
$arguments = array_slice($argv, 1);

if ($arguments === ['--list']) {
    foreach ($scenarios as $scenario) {
        fwrite(STDOUT, $scenario->id . "\n");
    }
    exit(0);
}
if (in_array('--list', $arguments, true) || in_array('--help', $arguments, true)) {
    usage();
}

$requested = array_fill_keys($arguments, true);
if ($requested !== []) {
    $known = array_fill_keys(array_map(static fn (CaptureScenario $scenario): string => $scenario->id, $scenarios), true);
    foreach (array_keys($requested) as $id) {
        if (! isset($known[$id])) {
            fail("unknown capture id: {$id}", 2);
        }
    }
}

$chrome = findChrome();
$outputRoot = $siteRoot . '/public/captures';
$temporaryBase = tempnam(sys_get_temp_dir(), 'tvision-doc-capture-');
if ($temporaryBase === false) {
    fail('could not create a temporary HTML file');
}
$temporaryHtml = $temporaryBase . '.html';
if (! rename($temporaryBase, $temporaryHtml)) {
    @unlink($temporaryBase);
    fail('could not prepare a temporary HTML file');
}
register_shutdown_function(static function () use ($temporaryHtml): void {
    @unlink($temporaryHtml);
});

foreach ($scenarios as $scenario) {
    if ($requested !== [] && ! isset($requested[$scenario->id])) {
        continue;
    }

    $driver = new HeadlessDriver($scenario->columns, $scenario->rows);
    $screen = new Screen($driver);
    $application = ($scenario->factory)($screen, $driver);
    if (! $application instanceof Application) {
        fail("factory for {$scenario->id} did not return an Application");
    }

    $application->bootForTest();
    if ($scenario->prepare !== null) {
        ($scenario->prepare)($application, $driver);
    }
    $application->drawAndFlushForTest();

    $buffer = $application->screen()?->back();
    if ($buffer === null) {
        fail("{$scenario->id} did not provide a screen buffer");
    }

    $html = (new HtmlRenderer())->render($buffer);
    if (file_put_contents($temporaryHtml, $html) === false) {
        fail("could not write temporary HTML for {$scenario->id}");
    }

    $output = $outputRoot . '/' . $scenario->id . '.png';
    $outputDirectory = dirname($output);
    if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
        fail("could not create {$outputDirectory}");
    }

    $viewportWidth = (int) ceil($scenario->columns * 9.64);
    $viewportHeight = $scenario->rows * 16;
    run([
        $chrome,
        '--headless=new',
        '--hide-scrollbars',
        '--force-device-scale-factor=2',
        "--window-size={$viewportWidth},{$viewportHeight}",
        "--screenshot={$output}",
        'file://' . $temporaryHtml,
    ]);

    fwrite(STDOUT, "generated {$scenario->publicPath()} ({$scenario->columns}x{$scenario->rows})\n");
}
