<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Program;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;

/** A Program wired with an injected headless Screen and a quit status line. */
final class QuitProgram extends Program
{
    public function __construct(private readonly Screen $injected)
    {
        parent::__construct();
    }

    protected function initScreen(): Screen
    {
        return $this->injected;
    }

    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }

    protected function initDeskTop(Rect $bounds): Desktop
    {
        return new Desktop($bounds);
    }
}

test('run builds children, draws, and exits cleanly on a fed quit key', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));

    $driver->feedInput("\e" . 'x'); // Alt-X -> EscapeDecoder yields Key::AltX

    $code = $program->run();

    expect($code)->toBe(0)
        ->and($driver->isInitialised())->toBeFalse(); // shutdown ran
});

test('putEvent enqueues an event that getEvent returns first', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();

    $program->putEvent(Event::command(Cmd::FirstUser));
    $ev = $program->getEvent();

    expect($ev->isCommand(Cmd::FirstUser))->toBeTrue();
});

test('a cmQuit command ends the program (sets endState)', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();

    $program->handleEvent(Event::command(Cmd::Quit));

    expect($program->ended())->toBeTrue();
});

test('command enable/disable is tracked', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));

    expect($program->commandEnabled(Cmd::Quit))->toBeTrue(); // enabled by default
    $program->disableCommand(Cmd::Quit);
    expect($program->commandEnabled(Cmd::Quit))->toBeFalse();
    $program->enableCommand(Cmd::Quit);
    expect($program->commandEnabled(Cmd::Quit))->toBeTrue();
});
