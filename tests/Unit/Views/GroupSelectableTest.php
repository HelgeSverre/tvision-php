<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/**
 * Regression: "selectable" is the ofSelectable OPTION flag (in $options), not a
 * state flag. The group must consult $options, not getState(). The bug was masked
 * because ofSelectable (0x001) coincidentally equals sfVisible (0x001), so a plain
 * visible-but-not-selectable view was wrongly auto-focused.
 */
test('a non-selectable view is NOT auto-focused on insert', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 5));
    $group->insert(new View(Rect::of(0, 0, 5, 1))); // plain view: visible, options = 0

    expect($group->current())->toBeNull();
});

test('a selectable view (ofSelectable in options) IS focused', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 5));

    $selectable = new View(Rect::of(0, 0, 5, 1));
    $selectable->options |= State::Selectable;
    $group->insert($selectable);

    expect($group->current())->toBe($selectable);
});

test('selectNext skips non-selectable views', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 5));

    $plain = new View(Rect::of(0, 0, 5, 1));                 // not selectable
    $a = new View(Rect::of(0, 1, 5, 2));
    $a->options |= State::Selectable;
    $b = new View(Rect::of(0, 2, 5, 3));
    $b->options |= State::Selectable;

    $group->insert($plain);
    $group->insert($a);
    $group->insert($b);

    expect($group->current())->toBe($a); // first selectable, not $plain
    $group->selectNext();
    expect($group->current())->toBe($b);
    $group->selectNext();
    expect($group->current())->toBe($a); // wraps, never lands on $plain
});

test('automatic focus and traversal skip hidden and disabled selectable views', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 5));
    $hidden = new View(Rect::of(0, 0, 5, 1));
    $hidden->options |= State::Selectable;
    $hidden->setState(State::Visible, false);
    $disabled = new View(Rect::of(0, 1, 5, 2));
    $disabled->options |= State::Selectable;
    $disabled->setState(State::Disabled, true);
    $enabled = new View(Rect::of(0, 2, 5, 3));
    $enabled->options |= State::Selectable;

    $group->insert($hidden);
    $group->insert($disabled);
    $group->insert($enabled);

    expect($group->current())->toBe($enabled);
    $group->selectNext();
    expect($group->current())->toBe($enabled);
});

test('removing the current view transfers focus to a remaining selectable view', function (): void {
    $group = new Group(Rect::of(0, 0, 10, 5));
    $first = new View(Rect::of(0, 0, 5, 1));
    $second = new View(Rect::of(0, 1, 5, 2));
    $first->options |= State::Selectable;
    $second->options |= State::Selectable;
    $group->insert($first);
    $group->insert($second);

    $group->remove($first);

    expect($group->current())->toBe($second)
        ->and($first->getState(State::Focused))->toBeFalse()
        ->and($second->getState(State::Focused))->toBeTrue();
});
