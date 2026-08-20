<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Rendering;

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use InvalidArgumentException;

/**
 * Pure double-buffer presenter: diff $front (on screen) vs $back (desired) and emit
 * the minimal ANSI to make $front look like $back. Coalesces consecutive changed
 * cells into runs; one cursor move per run; re-emits the SGR style only when the
 * attribute byte changes within a run. Mutates nothing.
 */
final class DiffPresenter
{
    public function present(Buffer $front, Buffer $back, AnsiEncoder $enc): string
    {
        if ($front->width !== $back->width || $front->height !== $back->height) {
            throw new InvalidArgumentException('Front and back buffers must have the same dimensions.');
        }

        $out = '';
        $rows = $back->height;
        $cols = $back->width;
        $frontCells = $front->cells();
        $backCells = $back->cells();

        for ($y = 0; $y < $rows; $y++) {
            $rowOffset = $y * $cols;
            $x = 0;
            while ($x < $cols) {
                $cur = $backCells[$rowOffset + $x];
                $old = $frontCells[$rowOffset + $x];

                if ($cur->equals($old)) {
                    $x++;

                    continue;
                }

                // Start a run at this changed cell.
                $out .= $enc->moveCursor($x, $y);
                $runAttr = $cur->attr;
                $out .= $enc->style($runAttr);

                while ($x < $cols) {
                    $cell = $backCells[$rowOffset + $x];
                    if ($cell->equals($frontCells[$rowOffset + $x])) {
                        break; // unchanged cell ends the run
                    }
                    if ($cell->attr !== $runAttr) {
                        $runAttr = $cell->attr;
                        $out .= $enc->style($runAttr); // re-style, no move
                    }
                    $out .= $cell->char;
                    $x++;
                }
            }
        }

        return $out;
    }
}
