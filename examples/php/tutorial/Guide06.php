<?php

declare(strict_types=1);

/*
 * Guide06 — PHP port of tvguid06.cc. The window interior writes file lines straight via
 * writeStr (the "imperfect" version; Guide07 fixes per-line rendering). Lines come from
 * docs/fixtures/lorem.txt for deterministic snapshots.
 */

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/Guide04.php';

/** @return list<string> */
function g4LoadLines(): array
{
    $path = __DIR__ . '/../../../docs/fixtures/lorem.txt';
    $raw = is_file($path) ? (string) file_get_contents($path) : '';

    return array_values(array_filter(explode("\n", $raw), static fn (string $l): bool => $l !== ''));
}

final class Guide06Interior extends View
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
        parent::draw();
        for ($i = 0; $i < $this->bounds->height(); $i++) {
            $line = $this->lines[$i] ?? '';
            if ($line !== '') {
                $this->writeStr(0, $i, mb_substr($line, 0, $this->bounds->width()), $this->mapColor(1));
            }
        }
    }
}

final class Guide06Window extends Window
{
    /** @param list<string> $lines */
    public function __construct(Rect $bounds, string $title, int $number, array $lines)
    {
        parent::__construct($bounds, $title, $number);
        $r = $this->getClipRect()->grow(-1, -1);
        $this->insert(new Guide06Interior($r, $lines));
    }
}

final class Guide06App extends Guide04App
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
        return new Guide06Window($bounds, 'Demo Window', $number, $this->lines);
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide06App())->run());
}
