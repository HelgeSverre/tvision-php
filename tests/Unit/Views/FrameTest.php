<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\FrameOwner;
use HelgeSverre\TurboVision\Views\Glyphs;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/** A Group that satisfies FrameOwner so a Frame can draw against a stub window. */
final class StubWindow extends Group implements FrameOwner
{
    public string $title = 'Demo';

    public int $flags = WindowFlags::Default;

    public int $number = 1;

    public bool $zoomed = false;

    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }

    public function frameTitle(): string
    {
        return $this->title;
    }

    public function frameFlags(): int
    {
        return $this->flags;
    }

    public function frameNumber(): int
    {
        return $this->number;
    }

    public function frameIsZoomed(): bool
    {
        return $this->zoomed;
    }
}

test('an inactive frame draws single-line box corners', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);                 // frame is last subview
    $frame->setState(State::Active, false);
    $frame->draw();

    $rows = $screen->back()->rows();
    expect(mb_substr($rows[0], 0, 1))->toBe(Glyphs::SINGLE_TOP_LEFT)
        ->and(mb_substr($rows[0], 19, 1))->toBe(Glyphs::SINGLE_TOP_RIGHT)
        ->and(mb_substr($rows[5], 0, 1))->toBe(Glyphs::SINGLE_BOTTOM_LEFT)
        ->and(mb_substr($rows[5], 19, 1))->toBe(Glyphs::SINGLE_BOTTOM_RIGHT);
});

test('an active frame draws double-line box corners and the title', function (): void {
    $screen = new Screen(new HeadlessDriver(30, 6));
    $screen->init();
    $win = new StubWindow(Rect::of(0, 0, 30, 6), $screen);
    $win->title = 'Demo';
    $frame = new Frame(Rect::of(0, 0, 30, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    $rows = $screen->back()->rows();
    expect(mb_substr($rows[0], 0, 1))->toBe(Glyphs::DOUBLE_TOP_LEFT)
        ->and(mb_substr($rows[0], 29, 1))->toBe(Glyphs::DOUBLE_TOP_RIGHT)
        ->and($rows[0])->toContain('Demo');
});

test('an active frame draws the close and zoom icons', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    $top = $screen->back()->rows()[0];
    // close icon body '■' sits at column 3 (after the box corner + '[').
    expect($top)->toContain('■')   // close glyph
        ->and($top)->toContain('↑'); // zoom glyph (not zoomed)
});

test('a zoomed active frame shows the un-zoom icon', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $win->zoomed = true;
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    expect($screen->back()->rows()[0])->toContain('↓');
});

test('the window number is drawn on the frame', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 6));
    $screen->init();
    $win = new StubWindow(Rect::of(0, 0, 20, 6), $screen);
    $win->number = 3;
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    expect($screen->back()->rows()[0])->toContain('3');
});

test('a narrow frame keeps its title out of the number and zoom controls', function (): void {
    $screen = new Screen(new HeadlessDriver(28, 6));
    $screen->init();
    $win = new StubWindow(Rect::of(0, 0, 28, 6), $screen);
    $win->title = 'Feature Navigator';
    $frame = new Frame(Rect::of(0, 0, 28, 6));
    $win->insert($frame);
    $frame->setState(State::Active, true);
    $frame->draw();

    $top = $screen->back()->rows()[0];
    expect($top)->toContain('Feature Naviga')
        ->and(mb_substr($top, 21, 1))->toBe('1')
        ->and(mb_substr($top, 23, 3))->toBe('[↑]');
});

test('Frame growMode is gfGrowHiX|gfGrowHiY', function (): void {
    $frame = new Frame(Rect::of(0, 0, 20, 6));
    expect($frame->growMode)->toBe(State::GrowHiX | State::GrowHiY);
});
