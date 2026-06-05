<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Group;

test('desktop owns a Background sized to its extent', function (): void {
    $desk = new Desktop(Rect::of(0, 1, 8, 4)); // 8 wide, 3 tall

    expect($desk->subviews())->toHaveCount(1)
        ->and($desk->subviews()[0])->toBeInstanceOf(Background::class)
        ->and($desk->subviews()[0]->getBounds())->toEqual(Rect::of(0, 0, 8, 3));
});

test('drawing the desktop fills its region with the desk pattern', function (): void {
    $screen = new Screen(new HeadlessDriver(6, 4));
    $screen->init();

    // A root group that hosts the desktop and exposes the screen.
    $root = new class(Rect::of(0, 0, 6, 4), $screen) extends Group {
        public function __construct(Rect $b, private readonly Screen $s)
        {
            parent::__construct($b);
        }

        public function screen(): Screen
        {
            return $this->s;
        }
    };

    // Desktop occupies rows 1..2 (between a menu bar on row 0 and a status line on row 3).
    $desk = new Desktop(Rect::of(0, 1, 6, 3));
    $root->insert($desk);

    $desk->drawView();

    expect($screen->back()->rows())->toBe([
        '      ', // row 0 (menu bar — untouched here)
        '░░░░░░', // row 1
        '░░░░░░', // row 2
        '      ', // row 3 (status line — untouched here)
    ]);
});
