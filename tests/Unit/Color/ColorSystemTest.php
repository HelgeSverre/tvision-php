<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Color\ColorDialog;
use HelgeSverre\TurboVision\Color\ColorDisplay;
use HelgeSverre\TurboVision\Color\ColorGroup;
use HelgeSverre\TurboVision\Color\ColorItem;
use HelgeSverre\TurboVision\Color\ColorSelector;
use HelgeSverre\TurboVision\Color\ColorSelectorType;
use HelgeSverre\TurboVision\Color\MonoSelector;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;

test('colour groups retain named items and clamp their selection', function (): void {
    $group = new ColorGroup('Window', [new ColorItem('Frame', 1), new ColorItem('Title', 2)]);
    $group->select(99);

    expect($group->count())->toBe(2)
        ->and($group->item(1)?->name)->toBe('Title')
        ->and($group->selectedIndex)->toBe(1);
});

test('foreground and background selectors navigate their respective ranges', function (): void {
    $foreground = new ColorSelector(Rect::of(0, 0, 12, 4), ColorSelectorType::Foreground);
    $background = new ColorSelector(Rect::of(0, 0, 12, 2), ColorSelectorType::Background);

    $foreground->handleEvent(Event::keyDown(new KeyDownEvent(Key::Left->value)));
    $background->handleEvent(Event::keyDown(new KeyDownEvent(Key::Up->value)));

    expect($foreground->color)->toBe(15)
        ->and($background->color)->toBe(7);
});

test('a color selector maps pointer cells onto its four-column colour grid', function (): void {
    $selector = new ColorSelector(Rect::of(4, 3, 16, 7), ColorSelectorType::Foreground);
    $event = Event::mouse(EventType::MouseDown, new MouseEvent(new Point(11, 5)));

    $selector->handleEvent($event);

    // Local (7, 2) maps to row 2, column floor(7 / 3): 2 * 4 + 2 = 10.
    expect($selector->color)->toBe(10)
        ->and($event->isNothing())->toBeTrue();
});

test('a preview recombines foreground and background broadcasts', function (): void {
    $display = new ColorDisplay(Rect::of(0, 0, 10, 1), color: 0x17);
    $display->handleEvent(Event::broadcast(71, 0x0E));
    $display->handleEvent(Event::broadcast(72, 0x03));

    expect($display->color)->toBe(0x3E);
});

test('mono selector publishes its selected classic attribute', function (): void {
    $seen = [];
    $selector = new MonoSelector(Rect::of(0, 0, 14, 4), onChanged: static function (int $value) use (&$seen): void {
        $seen[] = $value;
    });
    $selector->press(3);

    expect($selector->mark(3))->toBeTrue()
        ->and($selector->value)->toBe(0x70)
        ->and($seen)->toBe([0x70]);
});

test('color dialog edits only its working palette until commit', function (): void {
    $source = Palette::fromBytes("\x17\x2E");
    $committed = null;
    $dialog = new ColorDialog(
        $source,
        [new ColorGroup('Main', [new ColorItem('Text', 1), new ColorItem('Accent', 2)])],
        onCommit: static function (Palette $palette) use (&$committed): void {
            $committed = $palette;
        },
    );

    $dialog->foregroundSelector?->setColor(0x0C, true);

    expect($source->get(1))->toBe(0x17)
        ->and($dialog->getData()->get(1))->toBe(0x1C);

    $result = $dialog->commit();
    expect($result->get(1))->toBe(0x1C)
        ->and($committed)->toBeInstanceOf(Palette::class)
        ->and($committed?->get(1))->toBe(0x1C);
});

test('color dialog cancel restores its initial working copy', function (): void {
    $source = Palette::fromBytes("\x17");
    $dialog = new ColorDialog($source, [new ColorGroup('Main', [new ColorItem('Text', 1)])]);

    $dialog->backgroundSelector?->setColor(4, true);
    expect($dialog->getData()->get(1))->toBe(0x47);

    $dialog->cancel();
    expect($dialog->getData()->get(1))->toBe(0x17);
});

test('switching groups restores each group item selection and preview', function (): void {
    $dialog = new ColorDialog(
        Palette::fromBytes("\x17\x2E"),
        [
            new ColorGroup('First', [new ColorItem('Text', 1)]),
            new ColorGroup('Second', [new ColorItem('Accent', 2)]),
        ],
    );

    $dialog->groups->focusItem(1);

    expect($dialog->items->currentGroup()?->name)->toBe('Second')
        ->and($dialog->display->color)->toBe(0x2E)
        ->and($dialog->getIndexes()->groupIndex)->toBe(1);
});
