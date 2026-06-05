<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

/** A root group that exposes a real Screen so StatusLine compositing is testable. */
final class StatusLineRoot extends Group
{
    public function __construct(Rect $bounds, private readonly Screen $rootScreen)
    {
        parent::__construct($bounds);
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
}

/** @return array{StatusLineRoot, Screen} */
function statusRoot(int $w): array
{
    $screen = new Screen(new HeadlessDriver($w, 1));
    $screen->init();
    $g = new StatusLineRoot(Rect::of(0, 0, $w, 1), $screen);

    return [$g, $screen];
}

test('status line draws its item hints with hotkeys stripped', function (): void {
    [$root, $screen] = statusRoot(24);
    $line = new StatusLine(
        Rect::of(0, 0, 24, 1),
        new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ),
    );
    $root->insert($line);

    $line->draw();

    expect($screen->back()->rows()[0])->toContain('Alt-X Exit');
});

test('a matching key press is rewritten into a Command event in place', function (): void {
    [$root, $screen] = statusRoot(24);
    $line = new StatusLine(
        Rect::of(0, 0, 24, 1),
        new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ),
    );
    $root->insert($line);

    $ev = Event::keyDown(new KeyDownEvent(Key::AltX->value));
    $line->handleEvent($ev);

    expect($ev->what)->toBe(EventType::Command)
        ->and($ev->asMessage()?->command)->toBe(Cmd::Quit);
});

test('a non-matching key press is left untouched', function (): void {
    [$root, $screen] = statusRoot(24);
    $line = new StatusLine(
        Rect::of(0, 0, 24, 1),
        new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ),
    );
    $root->insert($line);

    $ev = Event::keyDown(new KeyDownEvent(Key::Enter->value));
    $line->handleEvent($ev);

    expect($ev->what)->toBe(EventType::KeyDown)
        ->and($ev->asKey()?->is(Key::Enter))->toBeTrue();
});
