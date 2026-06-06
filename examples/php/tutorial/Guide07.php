<?php

declare(strict_types=1);

/*
 * Guide07 — PHP port of tvguid07.cc. Same as Guide06 but each interior line is painted
 * into its own blanked DrawBuffer first, so partial/short lines never leave stale cells.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide06.php'; // reuses g4LoadLines()

final class Guide07Interior extends View
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, private readonly array $lines)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->options |= State::Framed;
    }

    public function draw(): void
    {
        $color = $this->mapColor(1);
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $b = new DrawBuffer($this->bounds->width());
            $b->moveChar(0, ' ', $color, $this->bounds->width());
            $line = $this->lines[$i] ?? '';
            if ($line !== '') {
                $b->moveStr(0, mb_substr($line, 0, $this->bounds->width()), $color);
            }
            $this->writeLine(0, $i, $this->bounds->width(), 1, $b);
        }
    }
}

final class Guide07Window extends Window
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $r = $this->getClipRect()->grow(-1, -1);
        $this->insert(new Guide07Interior($r, $lines));
    }
}

final class Guide07App extends Guide04App
{
    /** @var list<string> */
    private array $lines;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Guide07Window($bounds, 'Demo Window', $number, $this->lines);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide07App())->run());
}
