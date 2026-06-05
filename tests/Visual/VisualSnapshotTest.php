<?php

declare(strict_types=1);

/*
 * Visual regression tests: render each example to a PNG (via bin/tv-shot — the HTML
 * renderer + headless Chrome) and pixel-diff it against a committed baseline with
 * ImageMagick. This is the coverage that the glyph-only headless snapshots lacked:
 * it would have caught the monochrome-palette bug instantly (a colour regression is
 * tens of thousands of differing pixels).
 *
 * Opt-in: run with `vendor/bin/pest --group=visual`. Skipped automatically when
 * Chrome or ImageMagick are unavailable, so the normal suite/CI is unaffected.
 *
 * Baselines are environment-specific (font rendering). Regenerate after an
 * intentional visual change with: bin/tv-shot <example> tests/Visual/__baselines__/<name>.png
 */

function tvVisualChrome(): ?string
{
    $candidates = ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'];
    foreach (['chromium', 'chromium-browser', 'google-chrome'] as $bin) {
        $path = trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if ($path !== '') {
            $candidates[] = $path;
        }
    }
    foreach ($candidates as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    return null;
}

function tvVisualTools(): bool
{
    $hasMagick = trim((string) shell_exec('command -v magick 2>/dev/null')) !== '';

    return $hasMagick && tvVisualChrome() !== null;
}

/** Differing-pixel ceiling. Same-machine renders diff by 0; a colour regression is huge. */
const TV_VISUAL_THRESHOLD = 200;

/** Render an example and return how many pixels differ from its committed baseline. */
function tvVisualDiff(string $example, string $baselineName): int
{
    $root = dirname(__DIR__, 2);
    $baseline = "{$root}/tests/Visual/__baselines__/{$baselineName}.png";
    if (! is_file($baseline)) {
        return PHP_INT_MAX;
    }

    $outDir = "{$root}/tests/Visual/__output__";
    if (! is_dir($outDir)) {
        mkdir($outDir, 0777, true);
    }
    $candidate = "{$outDir}/{$baselineName}.png";
    $diff = "{$outDir}/{$baselineName}.diff.png";

    shell_exec(sprintf(
        '%s %s %s 80 25 2>/dev/null',
        escapeshellarg("{$root}/bin/tv-shot"),
        escapeshellarg("{$root}/examples/php/tutorial/{$example}.php"),
        escapeshellarg($candidate),
    ));
    if (! is_file($candidate)) {
        return PHP_INT_MAX;
    }

    $report = (string) shell_exec(sprintf(
        'magick compare -metric AE %s %s %s 2>&1',
        escapeshellarg($baseline),
        escapeshellarg($candidate),
        escapeshellarg($diff),
    ));
    preg_match('/\d+/', $report, $m);

    return (int) ($m[0] ?? PHP_INT_MAX);
}

test('every example matches its visual baseline', function (): void {
    foreach (['Guide01' => 'guide01', 'Guide02' => 'guide02', 'Guide03' => 'guide03'] as $example => $name) {
        expect(tvVisualDiff($example, $name))->toBeLessThanOrEqual(
            TV_VISUAL_THRESHOLD,
            "{$example}: too many pixels differ from baseline (see tests/Visual/__output__/{$name}.diff.png).",
        );
    }
})->group('visual')->skip(
    fn (): bool => ! tvVisualTools(),
    'Visual tests require Chrome/Chromium + ImageMagick (magick).',
);
