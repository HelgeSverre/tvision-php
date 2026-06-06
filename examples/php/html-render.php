#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * HtmlRenderer demo — paint a colourful frame into a Buffer and render it to an HTML
 * file you can open in any browser. This is the same Rendering\HtmlRenderer the visual
 * snapshot tests use; it turns a screen Buffer into a coloured monospace HTML grid
 * using the canonical CGA/VGA palette.
 *
 *   Run:   php examples/php/html-render.php [out.html]
 *
 * To render a real TUI app's frame instead of this demo scene:
 *   php bin/tv-render examples/php/tutorial/Guide03.php frame.html && open frame.html
 */

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\Color;
use HelgeSverre\TurboVision\Rendering\HtmlRenderer;

require __DIR__ . '/../../vendor/autoload.php';

/** Paint a string into the buffer at (x, y) with one attribute. */
function paint(Buffer $buffer, int $x, int $y, string $text, Attribute $attr): void
{
    foreach (mb_str_split($text) as $i => $char) {
        $buffer->put($x + $i, $y, Cell::of($char, $attr));
    }
}

$cols = 64;
$rows = 24;

// A black "colour test card" so every colour is legible. fg supports all 16 colours;
// bg is the low 3 bits (0-7), faithful to the CGA attribute byte.
$on = static fn (Color $fg): Attribute => new Attribute($fg, Color::Black);
$buffer = new Buffer($cols, $rows, Cell::of(' ', $on(Color::LightGray)));

// A Turbo-Vision-style window frame (box-drawing glyphs map straight through to UTF-8).
$frame = $on(Color::White);
paint($buffer, 0, 0, '┌' . str_repeat('─', $cols - 2) . '┐', $frame);
paint($buffer, 0, $rows - 1, '└' . str_repeat('─', $cols - 2) . '┘', $frame);
for ($y = 1; $y < $rows - 1; $y++) {
    paint($buffer, 0, $y, '│', $frame);
    paint($buffer, $cols - 1, $y, '│', $frame);
}
paint($buffer, 3, 0, ' HtmlRenderer demo ', $on(Color::Yellow));

$names = [
    'Black', 'Blue', 'Green', 'Cyan', 'Red', 'Magenta', 'Brown', 'LightGray',
    'DarkGray', 'LightBlue', 'LightGreen', 'LightCyan', 'LightRed', 'LightMagenta', 'Yellow', 'White',
];

paint($buffer, 3, 2, 'The 16 CGA foreground colours:', $on(Color::LightCyan));
for ($i = 0; $i < 8; $i++) {
    $y = 4 + $i;
    // Swatch in the colour itself; the label stays white so every row is readable.
    paint($buffer, 4, $y, '████', $on(Color::from($i)));
    paint($buffer, 9, $y, $names[$i], $on(Color::White));
    paint($buffer, 32, $y, '████', $on(Color::from($i + 8)));
    paint($buffer, 37, $y, $names[$i + 8], $on(Color::White));
}

paint($buffer, 3, 13, 'Background colours (bg, 0-7):', $on(Color::LightCyan));
for ($i = 0; $i < 8; $i++) {
    paint($buffer, 4 + $i * 7, 14, '  ' . $i . '  ', new Attribute(Color::White, Color::from($i)));
}

paint($buffer, 3, 16, 'Shades: ░ ▒ ▓ █     Box: ┌─┬─┐ ├─┼─┤ └─┴─┘', $on(Color::LightGreen));
paint($buffer, 3, 18, 'Each Cell is a char + a CGA attribute byte (fg | bg<<4).', $on(Color::LightGray));
paint($buffer, 3, 19, 'HtmlRenderer turns the whole Buffer into coloured HTML.', $on(Color::LightGray));
paint($buffer, 3, 21, 'Tip: bin/tv-render <example.php> out.html renders a real app.', $on(Color::Brown));

$out = $argv[1] ?? sys_get_temp_dir() . '/turbovision-frame.html';
file_put_contents($out, (new HtmlRenderer())->render($buffer));
fwrite(STDERR, "Wrote {$out}\n");

// Best-effort: open it in the default browser.
$opener = match (PHP_OS_FAMILY) {
    'Darwin' => 'open',
    'Linux' => 'xdg-open',
    default => null,
};
if ($opener !== null) {
    exec($opener . ' ' . escapeshellarg($out) . ' >/dev/null 2>&1 &');
    fwrite(STDERR, "Opening it in your browser…\n");
}
