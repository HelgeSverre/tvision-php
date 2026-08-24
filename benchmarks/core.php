#!/usr/bin/env php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Rendering\DiffPresenter;
use HelgeSverre\TurboVision\Rendering\HtmlRenderer;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\View;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @return array{median_ns:float,min_ns:float,max_ns:float,iterations:int}
 */
function benchmark(callable $operation, int $iterations): array
{
    for ($i = 0; $i < max(1, intdiv($iterations, 20)); $i++) {
        $operation();
    }

    $samples = [];
    for ($sample = 0; $sample < 7; $sample++) {
        $started = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $operation();
        }
        $samples[] = (hrtime(true) - $started) / $iterations;
    }
    sort($samples, SORT_NUMERIC);

    return [
        'median_ns' => $samples[3],
        'min_ns' => $samples[0],
        'max_ns' => $samples[6],
        'iterations' => $iterations,
    ];
}

$encoder = new AnsiEncoder();
$presenter = new DiffPresenter();
$ascii = str_repeat('Turbo Vision ', 64);
$unicode = str_repeat("Borders ─│┼ café e\u{0301} ", 24);
$keyInput = str_repeat("a\e[A\e[B\e[<0;40;12M", 32);

$unchanged = new Buffer(160, 50, new Cell(' ', 0x07));
$fullFront = new Buffer(160, 50, new Cell(' ', 0x07));
$fullBack = new Buffer(160, 50, new Cell('x', 0x1F));
$sparseFront = new Buffer(160, 50, new Cell(' ', 0x07));
$sparseBack = new Buffer(160, 50, new Cell(' ', 0x07));
for ($y = 0; $y < 50; $y += 5) {
    for ($x = 0; $x < 160; $x += 16) {
        $sparseBack->put($x, $y, new Cell('x', 0x1F));
    }
}

$driver = new HeadlessDriver(160, 50);
$screen = new Screen($driver);
$screen->init();
$screen->back()->fill(Rect::of(0, 0, 160, 50), new Cell('x', 0x1F));
$screen->flush();
$driver->takeOutput();
$drawText = str_repeat('Turbo Vision ', 14);

$nestedDriver = new HeadlessDriver(160, 50);
$nestedScreen = new Screen($nestedDriver);
$nestedScreen->init();
$nestedRoot = new class($nestedScreen) extends Group {
    public function __construct(private readonly Screen $rootScreen)
    {
        parent::__construct(Rect::of(0, 0, $rootScreen->cols(), $rootScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
};
$nestedOwner = $nestedRoot;
for ($depth = 0; $depth < 8; $depth++) {
    $group = new Group(Rect::of(1, 1, 159 - $depth * 2, 49 - $depth * 2));
    $nestedOwner->insert($group);
    $nestedOwner = $group;
}
$nestedView = new View(Rect::of(1, 1, 121, 31));
$nestedOwner->insert($nestedView);
$nestedRow = new DrawBuffer(120);
$nestedRow->moveChar(0, 'x', 0x1F, 120);

$cases = [
    'text.graphemes.ascii' => [static fn (): array => TerminalText::graphemes($ascii), 1000],
    'text.graphemes.unicode' => [static fn (): array => TerminalText::graphemes($unicode), 1000],
    'text.cell-glyph.ascii' => [static fn (): string => TerminalText::cellGlyph('x'), 20000],
    'draw-buffer.ascii-160' => [static function () use ($drawText): void {
        $buffer = new DrawBuffer(160);
        $buffer->moveStr(0, $drawText, 0x1F);
    }, 1000],
    'decoder.mixed-640b' => [static fn () => (new EscapeDecoder())->decode($keyInput), 500],
    'present.unchanged-160x50' => [static fn (): string => $presenter->present($unchanged, $unchanged, $encoder), 100],
    'present.sparse-160x50' => [static fn (): string => $presenter->present($sparseFront, $sparseBack, $encoder), 100],
    'present.full-160x50' => [static fn (): string => $presenter->present($fullFront, $fullBack, $encoder), 100],
    'html.render-160x50' => [static fn (): string => (new HtmlRenderer())->render($fullBack), 50],
    'screen.flush-idle-160x50' => [static function () use ($screen, $driver): void {
        $screen->flush();
        $driver->takeOutput();
    }, 100],
    'view.write-line-120x30-depth8' => [
        static fn () => $nestedView->writeLine(0, 0, 120, 30, $nestedRow),
        200,
    ],
];

$results = [];
foreach ($cases as $name => [$operation, $iterations]) {
    $results[$name] = benchmark($operation, $iterations);
}

if (in_array('--json', $argv, true)) {
    echo json_encode(
        [
            'php' => PHP_VERSION,
            'jit' => ini_get('opcache.jit'),
            'peak_bytes' => memory_get_peak_usage(true),
            'results' => $results,
        ],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
    ) . "\n";

    exit(0);
}

printf("PHP %s, JIT=%s, peak=%.1f MiB\n", PHP_VERSION, ini_get('opcache.jit') ?: 'off', memory_get_peak_usage(true) / 1_048_576);
printf("%-34s %12s %12s\n", 'case', 'median', 'ops/sec');
foreach ($results as $name => $result) {
    $median = $result['median_ns'];
    printf("%-34s %9.2f ms %12.0f\n", $name, $median / 1_000_000, 1_000_000_000 / $median);
}
