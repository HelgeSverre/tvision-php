<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Group;

/** Root group exposing a Screen so Background can composite somewhere assertable. */
final class BackgroundRoot extends Group
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

test('default background fills its extent with the shade glyph', function (): void {
    $screen = new Screen(new HeadlessDriver(4, 2));
    $screen->init();
    $root = new BackgroundRoot($screen);

    $bg = new Background(Rect::of(0, 0, 4, 2));
    $root->insert($bg);

    $bg->draw();

    expect($screen->back()->rows())->toBe(['▓▓▓▓', '▓▓▓▓']);
});

test('a custom pattern char is used', function (): void {
    $screen = new Screen(new HeadlessDriver(3, 1));
    $screen->init();
    $root = new BackgroundRoot($screen);

    $bg = new Background(Rect::of(0, 0, 3, 1), '.');
    $root->insert($bg);

    $bg->draw();

    expect($screen->back()->rows())->toBe(['...']);
});
