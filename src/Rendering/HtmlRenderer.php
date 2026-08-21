<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Rendering;

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Buffer;

/**
 * Renders a screen Buffer to a self-contained HTML document — a monospace grid of
 * coloured cells using the canonical CGA/VGA RGB palette. Companion to DiffPresenter
 * (which renders to ANSI): this one is for visual inspection and pixel-diff
 * snapshot tests. Foregrounds and explicit backgrounds use fixed RGB values; default
 * black backgrounds inherit the browser canvas unless classic rendering is requested.
 */
final class HtmlRenderer
{
    /** Canonical CGA/VGA RGB for indices 0-15. */
    private const array CGA = [
        0 => '#000000', 1 => '#0000aa', 2 => '#00aa00', 3 => '#00aaaa',
        4 => '#aa0000', 5 => '#aa00aa', 6 => '#aa5500', 7 => '#aaaaaa',
        8 => '#555555', 9 => '#5555ff', 10 => '#55ff55', 11 => '#55ffff',
        12 => '#ff5555', 13 => '#ff55ff', 14 => '#ffff55', 15 => '#ffffff',
    ];

    public function __construct(private readonly bool $useDefaultBackgroundForBlack = true) {}

    public function render(Buffer $buffer): string
    {
        $lines = '';
        $cells = $buffer->cells();

        for ($y = 0; $y < $buffer->height; $y++) {
            $line = '';
            $rowOffset = $y * $buffer->width;
            $x = 0;
            while ($x < $buffer->width) {
                $attr = $cells[$rowOffset + $x]->attr;
                $run = '';
                // Coalesce consecutive cells that share an attribute into one span.
                while ($x < $buffer->width && $cells[$rowOffset + $x]->attr === $attr) {
                    $run .= htmlspecialchars($cells[$rowOffset + $x]->char, ENT_QUOTES, 'UTF-8');
                    $x++;
                }
                $attribute = Attribute::fromCellValue($attr);
                $line .= sprintf(
                    '<span style="color:%s;background:%s">%s</span>',
                    self::CGA[$attribute->fg->value],
                    $this->useDefaultBackgroundForBlack && $attribute->bg->value === 0
                        ? 'transparent'
                        : self::CGA[$attribute->bg->value],
                    $run,
                );
            }
            $lines .= $line . "\n";
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'html{color-scheme:dark}'
            . 'html,body{margin:0;padding:0;background:#000;background:Canvas}'
            . 'pre{margin:0;font:16px/1.0 "Menlo","DejaVu Sans Mono",monospace;'
            . 'letter-spacing:0;white-space:pre;display:inline-block}'
            . 'span{display:inline}</style></head><body><pre>'
            . $lines
            . '</pre></body></html>';
    }
}
