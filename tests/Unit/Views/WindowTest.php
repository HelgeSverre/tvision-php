<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Frame;
use HelgeSverre\TurboVision\Views\FrameOwner;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

test('the constructor inserts a Frame and stores title/number/flags', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 2);

    expect($w->subviews())->toHaveCount(1)
        ->and($w->subviews()[0])->toBeInstanceOf(Frame::class)
        ->and($w->frameTitle())->toBe('Demo')
        ->and($w->frameNumber())->toBe(2)
        ->and($w->frameFlags())->toBe(WindowFlags::Default)
        ->and($w)->toBeInstanceOf(FrameOwner::class);
});

test('a window is a selectable, top-selecting, shadowed group with grow-all-rel', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);

    expect(($w->options & State::Selectable) !== 0)->toBeTrue()
        ->and(($w->options & State::TopSelect) !== 0)->toBeTrue()
        ->and($w->getState(State::Shadow))->toBeTrue()
        ->and($w->growMode)->toBe(State::GrowAll | State::GrowRel);
});

test('getPalette returns the blue window palette by default', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $palette = $w->getPalette();

    expect($palette)->toBeInstanceOf(Palette::class)
        ->and($palette?->get(1))->toBe(0x08);  // first byte of cpBlueWindow
});

test('the palette can be switched to gray', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $w->setPalette(WindowPalette::Gray);

    expect($w->getPalette()?->get(1))->toBe(0x18); // first byte of cpGrayWindow
});

test('sizeLimits enforces the 16x6 minimum window size', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    [$minW, $minH, $maxW, $maxH] = $w->sizeLimits();

    expect($minW)->toBe(16)
        ->and($minH)->toBe(6);
});

test('standardScrollBar(vertical) inserts a 1-wide bar on the right edge', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(options: ScrollBarPart::Vertical);

    expect($bar)->toBeInstanceOf(ScrollBar::class)
        ->and($bar->isVertical())->toBeTrue()
        ->and($bar->getBounds())->toEqual(Rect::of(25, 1, 26, 6))
        ->and($w->subviews())->toContain($bar);
});

test('standardScrollBar(horizontal) inserts a 1-tall bar on the bottom edge', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(ScrollBarPart::Horizontal);

    expect($bar->isVertical())->toBeFalse()
        ->and($bar->getBounds())->toEqual(Rect::of(2, 6, 24, 7));
});

test('standardScrollBar with sbHandleKeyboard sets ofPostProcess', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(ScrollBarPart::Vertical | ScrollBarPart::HandleKeyboard);

    expect(($bar->options & State::PostProcess) !== 0)->toBeTrue();
});

test('standardScrollBar accepts a typed orientation and keyboard option', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    $bar = $w->standardScrollBar(
        ScrollBarOrientation::Horizontal,
        handleKeyboard: true,
    );

    expect($bar->isVertical())->toBeFalse()
        ->and($bar->getBounds())->toEqual(Rect::of(2, 6, 24, 7))
        ->and(($bar->options & State::PostProcess) !== 0)->toBeTrue();
});

test('frameIsZoomed reflects whether the window fills its max extent', function (): void {
    $w = new Window(Rect::of(0, 0, 26, 7), 'Demo', 1);
    expect($w->frameIsZoomed())->toBeFalse();
});
