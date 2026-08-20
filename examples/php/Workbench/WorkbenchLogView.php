<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Workbench;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;

final class WorkbenchLogView extends Scroller
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, ?ScrollBar $horizontal, ?ScrollBar $vertical, private readonly array $lines)
    {
        parent::__construct($bounds, $horizontal, $vertical);
        $longest = max(1, ...array_map('mb_strlen', $lines));
        $this->setLimit($longest, count($lines));
    }

    public function draw(): void
    {
        $attr = $this->mapColor(1);
        for ($row = 0; $row < $this->bounds->height(); $row++) {
            $buffer = new DrawBuffer($this->bounds->width());
            $buffer->moveChar(0, ' ', $attr, $this->bounds->width());
            $line = $this->lines[$this->delta->y + $row] ?? '';
            $buffer->moveStr(0, mb_substr($line, $this->delta->x, $this->bounds->width()), $attr);
            $this->writeLine(0, $row, $this->bounds->width(), 1, $buffer);
        }
    }
}
