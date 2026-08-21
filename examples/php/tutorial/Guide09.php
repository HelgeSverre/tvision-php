<?php

declare(strict_types=1);

/*
 * Guide09 — PHP port of tvguid09.cc. A window with two side-by-side Scroller panes, each
 * with its own vertical+horizontal scroll bars (ofPostProcess). Split at extent.b.x/2.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide06.php'; // g4LoadLines()

final class Guide09Interior extends Scroller
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, ?ScrollBar $h, ?ScrollBar $v, private readonly array $lines)
    {
        parent::__construct($bounds, $h, $v);
        $this->options |= State::Framed;
        $this->setLimit(80, count($lines));
    }

    public function draw(): void
    {
        $color = $this->getColor(0x0301) & 0xFF;
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $b = new DrawBuffer($this->bounds->width());
            $b->moveChar(0, ' ', $color, $this->bounds->width());
            $j = $this->delta->y + $i;
            $line = $this->lines[$j] ?? '';
            if ($line !== '') {
                $clipped = $this->delta->x >= mb_strlen($line)
                    ? ''
                    : mb_substr($line, $this->delta->x, $this->bounds->width());
                $b->moveStr(0, $clipped, $color);
            }
            $this->writeLine(0, $i, $this->bounds->width(), 1, $b);
        }
    }
}

class Guide09Window extends Window
{
    public ?Guide09Interior $leftPane = null;

    public ?Guide09Interior $rightPane = null;

    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, protected array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $ext = $this->getExtent();

        $leftBounds = Rect::of($ext->a->x, $ext->a->y, intdiv($ext->b->x, 2) + 1, $ext->b->y);
        $this->leftPane = $this->makePane($leftBounds, true);
        $this->leftPane->growMode = State::GrowHiY;
        $this->insert($this->leftPane);

        $rightBounds = Rect::of(intdiv($ext->b->x, 2), $ext->a->y, $ext->b->x, $ext->b->y);
        $this->rightPane = $this->makePane($rightBounds, false);
        $this->rightPane->growMode = State::GrowHiX | State::GrowHiY;
        $this->insert($this->rightPane);
    }

    protected function makePane(Rect $bounds, bool $left): Guide09Interior
    {
        $vBar = ScrollBar::vertical(Rect::of($bounds->b->x - 1, $bounds->a->y + 1, $bounds->b->x, $bounds->b->y - 1));
        $vBar->options |= State::PostProcess;
        if ($left) {
            $vBar->growMode = State::GrowHiY;
        }
        $this->insert($vBar);

        $hBar = ScrollBar::horizontal(Rect::of($bounds->a->x + 2, $bounds->b->y - 1, $bounds->b->x - 2, $bounds->b->y));
        $hBar->options |= State::PostProcess;
        if ($left) {
            $hBar->growMode = State::GrowHiY | State::GrowLoY;
        }
        $this->insert($hBar);

        $interior = $bounds->grow(-1, -1);

        return new Guide09Interior($interior, $hBar, $vBar, $this->lines);
    }
}

class Guide09App extends Guide04App
{
    /** @var list<string> */
    protected array $lines;

    public ?Window $lastWindow = null;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $win = new Guide09Window($bounds, 'Demo Window', $number, $this->lines);
        $this->lastWindow = $win;

        return $win;
    }

    public function scrollLeftPaneTo(int $x, int $y): void
    {
        if ($this->lastWindow instanceof Guide09Window) {
            $this->lastWindow->leftPane?->scrollTo($x, $y);
        }
    }
}

if (Guide09App::runningAsMain(__FILE__)) {
    exit((new Guide09App())->run());
}
