<?php

declare(strict_types=1);

/*
 * Guide08 — PHP port of tvguid08.cc. The interior is a Scroller with vertical+horizontal
 * keyboard-aware scroll bars. setLimit sizes the logical area; draw() paints
 * delta-offset lines from the fixture.
 */

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide06.php'; // g4LoadLines()

final class Guide08Interior extends Scroller
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, ?ScrollBar $h, ?ScrollBar $v, private readonly array $lines)
    {
        parent::__construct($bounds, $h, $v);
        $this->growMode = State::GrowHiX | State::GrowHiY;
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

final class Guide08Window extends Window
{
    public ?Guide08Interior $interior = null;

    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $v = $this->standardScrollBar(ScrollBarOrientation::Vertical, handleKeyboard: true);
        $h = $this->standardScrollBar(ScrollBarOrientation::Horizontal, handleKeyboard: true);
        $r = $this->getClipRect()->grow(-1, -1);
        $this->interior = new Guide08Interior($r, $h, $v, $lines);
        $this->insert($this->interior);
    }
}

final class Guide08App extends Guide04App
{
    /** @var list<string> */
    private array $lines;

    public ?Guide08Window $lastWindow = null;

    public function __construct(?\HelgeSverre\TurboVision\Terminal\Screen $screen = null)
    {
        parent::__construct($screen);
        $this->lines = g4LoadLines();
    }

    protected function makeWindow(Rect $bounds, int $number): Window
    {
        $this->lastWindow = new Guide08Window($bounds, 'Demo Window', $number, $this->lines);

        return $this->lastWindow;
    }

    public function scrollTopWindowTo(int $x, int $y): void
    {
        $this->lastWindow?->interior?->scrollTo($x, $y);
    }
}

if (Guide08App::runningAsMain(__FILE__)) {
    exit((new Guide08App())->run());
}
