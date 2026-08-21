<?php

declare(strict_types=1);

/*
 * Guide05 — PHP port of tvguid05.cc. A custom interior View ("Hello World!") fills the
 * inside of each demo window. Extends Guide04App, overriding the window factory.
 */

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide04.php';

final class Guide05Interior extends View
{
    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->options |= State::Framed;
    }

    public function draw(): void
    {
        parent::draw(); // blank the extent
        $color = $this->getColor(0x0301) & 0xFF;
        $this->writeStr(4, 2, 'Hello World!', $color);
    }
}

final class Guide05Window extends Window
{
    public function __construct(Rect $bounds, string $title, int $number)
    {
        parent::__construct($bounds, $title, $number);
        $interior = $this->getClipRect()->grow(-1, -1);
        $this->insert(new Guide05Interior($interior));
    }
}

final class Guide05App extends Guide04App
{
    protected function makeWindow(Rect $bounds, int $number): Window
    {
        return new Guide05Window($bounds, 'Demo Window', $number);
    }
}

if (Guide05App::runningAsMain(__FILE__)) {
    exit((new Guide05App())->run());
}
