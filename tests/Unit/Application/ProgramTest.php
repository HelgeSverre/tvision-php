<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Program;
use HelgeSverre\TurboVision\Application\PaletteMode;
use HelgeSverre\TurboVision\Application\Palettes;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Drivers\Driver;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Exceptions\DriverException;
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
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

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

    public function dirtyForTest(): bool
    {
        return $this->dirty;
    }
}

/** Exercises application-level commands while a Desktop owns a blocking modal. */
final class ModalProgram extends Program
{
    public int $helpViews = 0;

    public function __construct(private readonly Screen $injected)
    {
        parent::__construct();
    }

    protected function initScreen(): Screen
    {
        return $this->injected;
    }

    protected function createHelpView(int $context): View
    {
        $this->helpViews++;
        $dialog = new Dialog(Rect::of(0, 0, 24, 7), 'Modal Help');
        $dialog->options |= State::Centered;

        return $dialog;
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

/** Initialisation works, then the first terminal read throws the supplied failure. */
final class ThrowingInputDriver implements Driver
{
    public bool $initialised = false;

    public function __construct(private readonly DriverException $failure) {}

    public function init(): void
    {
        $this->initialised = true;
    }

    public function shutdown(): void
    {
        $this->initialised = false;
    }

    public function size(): array
    {
        return [20, 5];
    }

    public function write(string $bytes): void {}

    public function pollInput(int $timeoutMs): string
    {
        throw $this->failure;
    }

    public function resized(): bool
    {
        return false;
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

test('Kitty CSI-u Ctrl-C and Alt-X retain the programs legacy quit paths', function (): void {
    $ctrlDriver = new HeadlessDriver(20, 5);
    $ctrlProgram = new QuitProgram(new Screen($ctrlDriver));
    $ctrlProgram->bootForTest();
    $ctrlDriver->feedInput("\e[99;5u");

    $ctrlEvent = $ctrlProgram->getEvent();
    $ctrlProgram->handleEvent($ctrlEvent);

    $altDriver = new HeadlessDriver(20, 5);
    $altProgram = new QuitProgram(new Screen($altDriver));
    $altProgram->bootForTest();
    $altDriver->feedInput("\e[120;3u");

    $altEvent = $altProgram->getEvent();
    $altProgram->handleEvent($altEvent);

    expect($ctrlProgram->ended())->toBeTrue()
        ->and($altEvent->isNothing())->toBeTrue()
        ->and($altProgram->ended())->toBeTrue();
});

test('every key of a batched poll is preprocessed, not only the first', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();

    // One pollInput drains both keys in a single chunk; the decoder yields two
    // events. The second (Alt-X) must still pass through status-line preprocessing
    // and be rewritten into Cmd::Quit.
    $driver->feedInput('a' . "\e" . 'x');

    $first = $program->getEvent();
    $program->handleEvent($first);

    $second = $program->getEvent();
    $program->handleEvent($second);

    expect($second->isNothing())->toBeTrue()
        ->and($program->ended())->toBeTrue();
});

test('run treats a closed terminal input stream as a graceful shutdown', function (): void {
    $driver = new ThrowingInputDriver(DriverException::inputClosed());
    $program = new QuitProgram(new Screen($driver));

    expect($program->run())->toBe(0)
        ->and($program->ended())->toBeTrue()
        ->and($driver->initialised)->toBeFalse();
});

test('run propagates genuine read failures after restoring the terminal', function (): void {
    $driver = new ThrowingInputDriver(DriverException::readFailed());
    $program = new QuitProgram(new Screen($driver));

    expect(fn () => $program->run())
        ->toThrow(DriverException::class, 'Failed to read')
        ->and($driver->initialised)->toBeFalse();
});

test('a dialog treats a closed input stream as graceful shutdown like run()', function (): void {
    $driver = new ThrowingInputDriver(DriverException::inputClosed());
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();
    $dialog = new Dialog(Rect::of(0, 0, 16, 6), 'Modal');

    expect($program->executeDialog($dialog))->toBe(Cmd::Quit);
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

test('an explicit palette override takes precedence over the selected palette mode', function (): void {
    $program = new QuitProgram(new Screen(new HeadlessDriver(20, 5)));
    $program->setPaletteMode(PaletteMode::BlackWhite);
    $custom = Palette::fromBytes("\x1E\x2F");

    $program->setPalette($custom);
    $program->setPaletteMode(PaletteMode::Monochrome);

    expect($program->customPalette())->toBe($custom)
        ->and($program->getPalette())->toBe($custom)
        ->and($program->paletteMode())->toBe(PaletteMode::Monochrome);

    $program->setPalette(null);

    expect($program->customPalette())->toBeNull()
        ->and($program->getPalette()?->get(1))->toBe(ord(Palettes::MONOCHROME[0]));
});

test('changing an effective root palette schedules a redraw', function (): void {
    $program = new QuitProgram(new Screen(new HeadlessDriver(20, 5)));
    $program->bootForTest();
    $program->drawAndFlushForTest();

    expect($program->dirtyForTest())->toBeFalse();

    $program->setPalette(Palette::fromBytes("\x4E"));
    expect($program->dirtyForTest())->toBeTrue();

    $program->drawAndFlushForTest();
    $program->setPalette(null);

    expect($program->dirtyForTest())->toBeTrue();
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

test('program window helpers validate and select Alt-numbered windows', function (): void {
    $program = new QuitProgram(new Screen(new HeadlessDriver(80, 25)));
    $program->bootForTest();

    $one = new \HelgeSverre\TurboVision\Views\Window(Rect::of(0, 0, 20, 8), 'One', 1);
    $two = new \HelgeSverre\TurboVision\Views\Window(Rect::of(2, 1, 22, 9), 'Two', 2);
    expect($program->insertWindow($one))->toBe($one)
        ->and($program->insertWindow($two))->toBe($two);

    $program->handleEvent(Event::key(Key::Alt2));

    expect($program->desktopForTest()?->current())->toBe($two)
        ->and($program->validView(null))->toBeNull();
});

test('command state changes are broadcast through the view tree', function (): void {
    $program = new QuitProgram(new Screen(new HeadlessDriver(20, 5)));
    $program->bootForTest();
    $observer = new class(Rect::of(0, 0, 1, 1)) extends \HelgeSverre\TurboVision\Views\View {
        public int $changes = 0;

        public function __construct(Rect $bounds)
        {
            parent::__construct($bounds);
            $this->eventMask |= \HelgeSverre\TurboVision\Events\EventMask::Broadcast;
        }

        public function handleEvent(Event $event): void
        {
            if ($event->isCommand(Cmd::CommandSetChanged)) {
                $this->changes++;
            }
        }
    };
    $program->desktopForTest()?->insert($observer);

    $program->disableCommand(Cmd::Help);
    $program->enableCommand(Cmd::Help);

    expect($observer->changes)->toBe(2);
});

test('suspend and resume restore the screen without rebuilding the desktop', function (): void {
    $driver = new HeadlessDriver(20, 5);
    $program = new QuitProgram(new Screen($driver));
    $program->bootForTest();
    $desktop = $program->desktopForTest();

    $program->suspend();
    expect($driver->isInitialised())->toBeFalse();

    $program->resume();
    expect($driver->isInitialised())->toBeTrue()
        ->and($program->desktopForTest())->toBe($desktop);
});

test('application help remains available inside a blocking modal', function (): void {
    $driver = new HeadlessDriver(50, 18);
    $program = new ModalProgram(new Screen($driver));
    $program->bootForTest();
    $outer = new Dialog(Rect::of(5, 3, 40, 14), 'Outer Modal');
    $program->putEvent(Event::command(Cmd::Help));
    $program->putEvent(Event::command(Cmd::Cancel));
    $program->putEvent(Event::command(Cmd::Cancel));

    expect($program->executeDialog($outer))->toBe(Cmd::Cancel)
        ->and($program->helpViews)->toBe(1)
        ->and($driver->output())->toContain('Modal')
        ->and($driver->output())->toContain('Help');
});

test('Ctrl-C unwinds a blocking modal and ends the application', function (): void {
    $program = new ModalProgram(new Screen(new HeadlessDriver(50, 18)));
    $program->bootForTest();
    $program->putEvent(Event::keyDown(new \HelgeSverre\TurboVision\Events\KeyDownEvent(0x03)));

    expect($program->executeDialog(new Dialog(Rect::of(5, 3, 40, 14), 'Outer Modal')))->toBe(Cmd::Quit)
        ->and($program->ended())->toBeTrue();
});

test('a resize is reflowed before the next modal event is delivered', function (): void {
    $driver = new HeadlessDriver(40, 12);
    $program = new ModalProgram(new Screen($driver));
    $program->bootForTest();
    $dialog = new class(Rect::of(0, 0, 10, 4), 'Resize Modal') extends Dialog {
        public ?Rect $boundsAtEvent = null;

        public function handleEvent(Event $event): void
        {
            if ($event->what === EventType::KeyDown) {
                $this->boundsAtEvent = $this->getBounds();
                $this->endModal(Cmd::Cancel);
                $this->clearEvent($event);

                return;
            }
            parent::handleEvent($event);
        }
    };
    $dialog->options |= State::Centered;
    $driver->resizeTo(60, 20);
    $driver->feedInput('x');

    expect($program->executeDialog($dialog))->toBe(Cmd::Cancel)
        ->and($program->getBounds())->toEqual(Rect::of(0, 0, 60, 20))
        ->and($dialog->boundsAtEvent)->toEqual(Rect::of(25, 7, 35, 11));
});
