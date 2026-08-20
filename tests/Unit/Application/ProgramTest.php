<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Program;
use HelgeSverre\TurboVision\Drivers\Driver;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
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

/** A driver that reports a resize and a click together on its first poll. */
final class ResizeAndClickDriver implements Driver
{
    private bool $resizePending = true;

    private bool $inputPending = true;

    public function init(): void {}

    public function shutdown(): void {}

    public function size(): array
    {
        return $this->resizePending ? [20, 5] : [30, 8];
    }

    public function write(string $bytes): void {}

    public function pollInput(int $timeoutMs): string
    {
        if (! $this->inputPending) {
            return '';
        }

        $this->inputPending = false;

        return "\e[<0;25;4M";
    }

    public function resized(): bool
    {
        if (! $this->resizePending) {
            return false;
        }

        $this->resizePending = false;

        return true;
    }
}

/** Records the root width at the instant the first mouse event is dispatched. */
final class ResizeDispatchProgram extends Program
{
    public ?int $widthAtDispatch = null;

    public function __construct(private readonly Screen $injected)
    {
        parent::__construct();
    }

    protected function initScreen(): Screen
    {
        return $this->injected;
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::MouseDown) {
            $this->widthAtDispatch = $this->getBounds()->width();
            $this->endModal(Cmd::Quit);
            $this->clearEvent($event);

            return;
        }

        parent::handleEvent($event);
    }
}

final class FailingLayoutProgram extends Program
{
    public function __construct(private readonly Screen $injected)
    {
        parent::__construct();
    }

    protected function initScreen(): Screen
    {
        return $this->injected;
    }

    protected function initDeskTop(Rect $bounds): Desktop
    {
        throw new RuntimeException('layout failed');
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

test('layout makes the desktop the focused root event target', function (): void {
    $program = new QuitProgram(new Screen(new HeadlessDriver(40, 12)));
    $program->bootForTest();

    expect($program->current())->toBe($program->desktopForTest());
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

test('a disabled status-line command is not generated by its shortcut', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();
    $program->disableCommand(Cmd::Quit);
    $driver->feedInput("\ex");

    $event = $program->getEvent();
    $program->handleEvent($event);

    expect($event->isCommand(Cmd::Quit))->toBeFalse()
        ->and($program->ended())->toBeFalse();
});

test('run reflows a resize before dispatching an event from the same poll', function (): void {
    $program = new ResizeDispatchProgram(new Screen(new ResizeAndClickDriver()));

    $program->run();

    expect($program->widthAtDispatch)->toBe(30);
});

test('bootForTest restores the driver when layout fails', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new FailingLayoutProgram(new Screen($driver));

    expect(fn () => $program->bootForTest())
        ->toThrow(RuntimeException::class, 'layout failed')
        ->and($driver->isInitialised())->toBeFalse();
});

test('layout keeps regions non-negative on a one-row terminal', function (): void {
    $program = new QuitProgram(new Screen(new HeadlessDriver(5, 1)));
    $program->bootForTest();

    expect($program->desktopForTest()?->getBounds()->height())->toBe(0);
});
