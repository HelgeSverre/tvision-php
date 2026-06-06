<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

test('Group::changeBounds reflows each subview by its growMode', function (): void {
    $g = new Group(Rect::of(0, 0, 20, 10));

    $fixed = new View(Rect::of(1, 1, 5, 3));            // no growMode -> stays
    $stretchX = new View(Rect::of(0, 0, 20, 1));
    $stretchX->growMode = State::GrowHiX;               // follows width
    $stretchAll = new View(Rect::of(2, 2, 18, 8));
    $stretchAll->growMode = State::GrowHiX | State::GrowHiY;

    $g->insert($fixed);
    $g->insert($stretchX);
    $g->insert($stretchAll);

    // Grow the group by (10, 4): 20x10 -> 30x14.
    $g->changeBounds(Rect::of(0, 0, 30, 14));

    expect($fixed->getBounds())->toEqual(Rect::of(1, 1, 5, 3))
        ->and($stretchX->getBounds())->toEqual(Rect::of(0, 0, 30, 1))
        ->and($stretchAll->getBounds())->toEqual(Rect::of(2, 2, 28, 12));
});
