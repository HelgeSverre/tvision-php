<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Background;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

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

test('desktop tiles only visible tileable views in the faithful reverse Z-order', function (): void {
    $desk = new Desktop(Rect::of(0, 0, 12, 8));
    $windows = [];
    for ($i = 0; $i < 4; $i++) {
        $view = new View(Rect::of(0, 0, 2, 2));
        $view->options |= State::Tileable;
        $desk->insert($view);
        $windows[] = $view;
    }

    $desk->tile(Rect::of(0, 0, 12, 8));

    expect($windows[0]->getBounds())->toEqual(Rect::of(6, 4, 12, 8))
        ->and($windows[1]->getBounds())->toEqual(Rect::of(6, 0, 12, 4))
        ->and($windows[2]->getBounds())->toEqual(Rect::of(0, 4, 6, 8))
        ->and($windows[3]->getBounds())->toEqual(Rect::of(0, 0, 6, 4));
});

test('desktop cascades tileable views and reports arrangements that cannot fit', function (): void {
    $desk = new class(Rect::of(0, 0, 10, 6)) extends Desktop {
        public bool $failed = false;

        protected function tileError(): void
        {
            $this->failed = true;
        }
    };
    $first = new View(Rect::of(0, 0, 2, 2));
    $second = new View(Rect::of(0, 0, 2, 2));
    $first->options |= State::Tileable;
    $second->options |= State::Tileable;
    $desk->insert($first);
    $desk->insert($second);

    $desk->cascade(Rect::of(0, 0, 10, 6));

    expect($first->getBounds())->toEqual(Rect::of(1, 1, 10, 6))
        ->and($second->getBounds())->toEqual(Rect::of(0, 0, 10, 6));

    $desk->tile(Rect::of(0, 0, 10, 1));
    expect($desk->failed)->toBeTrue();
});

test('desktop arrangements preserve every window minimum and stay inside their bounds', function (): void {
    $desk = new class(Rect::of(0, 0, 20, 8)) extends Desktop {
        public bool $failed = false;

        protected function tileError(): void
        {
            $this->failed = true;
        }
    };
    $first = new Window(Rect::of(0, 0, 16, 6), 'First');
    $second = new Window(Rect::of(2, 1, 18, 7), 'Second');
    $first->options |= State::Tileable;
    $second->options |= State::Tileable;
    $desk->insert($first);
    $desk->insert($second);
    $original = [$first->getBounds(), $second->getBounds()];

    $desk->tile($desk->getExtent());
    expect($desk->failed)->toBeTrue()
        ->and([$first->getBounds(), $second->getBounds()])->toEqual($original);

    $desk->failed = false;
    $desk->cascade($desk->getExtent());
    expect($desk->failed)->toBeFalse();
    foreach ([$first, $second] as $window) {
        expect($window->getBounds()->b->x)->toBeLessThanOrEqual(20)
            ->and($window->getBounds()->b->y)->toBeLessThanOrEqual(8)
            ->and($window->getBounds()->width())->toBeGreaterThanOrEqual(16)
            ->and($window->getBounds()->height())->toBeGreaterThanOrEqual(6);
    }
});
