<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Glyphs;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** A Group rooted at a real Screen so child writes hit the back buffer. */
final class SbRootGroup extends Group
{
    public function __construct(private readonly Screen $rootScreen)
    {
        parent::__construct(Rect::of(0, 0, $rootScreen->cols(), $rootScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
}

test('a horizontal scroll bar draws arrows, track and thumb', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 1));
    $screen->init();
    $g = new SbRootGroup($screen);
    $bar = new ScrollBar(Rect::of(0, 0, 10, 1));
    $g->insert($bar);
    $bar->setRange(0, 100);
    $bar->setValue(0);
    $bar->draw();

    $row = $screen->back()->rows()[0];

    expect(mb_substr($row, 0, 1))->toBe(Glyphs::ARROW_LEFT)
        ->and(mb_substr($row, 9, 1))->toBe(Glyphs::ARROW_RIGHT)
        ->and(mb_substr($row, 1, 1))->toBe(Glyphs::SCROLL_THUMB)        // pos 1 at value 0
        ->and(mb_substr($row, 5, 1))->toBe(Glyphs::SCROLL_TRACK);
});

test('a vertical scroll bar draws arrows top and bottom', function (): void {
    $screen = new Screen(new HeadlessDriver(1, 10));
    $screen->init();
    $g = new SbRootGroup($screen);
    $bar = new ScrollBar(Rect::of(0, 0, 1, 10));
    $g->insert($bar);
    $bar->setRange(0, 100);
    $bar->setValue(100);
    $bar->draw();

    $rows = $screen->back()->rows();

    expect($rows[0])->toBe(Glyphs::ARROW_UP)
        ->and($rows[9])->toBe(Glyphs::ARROW_DOWN)
        ->and($rows[8])->toBe(Glyphs::SCROLL_THUMB);   // pos 8 at value 100
});

test('a zero-range bar fills the whole track (no thumb)', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 1));
    $screen->init();
    $g = new SbRootGroup($screen);
    $bar = new ScrollBar(Rect::of(0, 0, 8, 1));
    $g->insert($bar);
    $bar->draw();

    $row = $screen->back()->rows()[0];
    // arrows at ends, track everywhere between, no thumb glyph.
    expect($row)->not->toContain(Glyphs::SCROLL_THUMB)
        ->and(mb_substr($row, 0, 1))->toBe(Glyphs::ARROW_LEFT)
        ->and(mb_substr($row, 7, 1))->toBe(Glyphs::ARROW_RIGHT);
});
