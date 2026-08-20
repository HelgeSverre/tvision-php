<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Workbench;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarPart;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;

final class WorkbenchApp extends Application
{
    private int $windowNumber = 0;

    private int $paletteIndex = WindowPalette::Blue;

    private ?WorkbenchDialog $dialog = null;

    private int $activitySequence = 11;

    /** @var list<string> */
    private array $activity = [
        '22:48:01  Workbench booted with the modern dark palette.',
        '22:48:02  Menu, window, mouse, and keyboard services ready.',
        '22:48:03  Headless renderer available for deterministic snapshots.',
        '22:48:04  Try Alt-D, F3, or the dashboard buttons.',
    ];

    /** @var list<string> */
    private const array TASKS = [
        '● Ship the interactive menu system',
        '◐ Verify modal keyboard trapping',
        '○ Drag and resize the dashboard',
        '○ Scroll the activity inspector',
        '○ Cycle all window palettes',
        '✓ Exercise headless snapshots',
        '✓ Restore terminal state on exit',
        '✓ Handle Unicode cell geometry',
        '○ Open task details with Enter',
        '○ Explore status-line shortcuts',
    ];

    public function __construct(?Screen $screen = null)
    {
        parent::__construct($screen ?? new Screen(new AnsiDriver(trackMouseMotion: true)));
    }

    public function dialogOpen(): bool
    {
        return $this->dialog !== null;
    }

    public function windowCount(): int
    {
        return count(array_filter(
            $this->desktopForTest()?->subviews() ?? [],
            static fn ($view): bool => $view instanceof Window,
        ));
    }

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = new Desktop($bounds);
        $this->openInitialWindows($desktop);

        return $desktop;
    }

    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar(
            $bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('~N~ew workspace…', WorkbenchCommand::NewWorkspace, Key::F4, 'F4'),
                new MenuItem('Open ~t~ask board', WorkbenchCommand::OpenTasks, Key::F2, 'F2'),
                new MenuItem('Open ~a~ctivity log', WorkbenchCommand::OpenActivity, Key::F3, 'F3'),
                new MenuItem('', 0),
                new MenuItem('~S~ave snapshot', WorkbenchCommand::SaveSnapshot, null, 'Ctrl-S'),
                new MenuItem('', 0),
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
            new SubMenu('~E~dit', Key::AltE)->items(
                new MenuItem('~U~ndo', WorkbenchCommand::Undo, null, 'Ctrl-Z'),
                new MenuItem('~R~edo', WorkbenchCommand::Redo, null, 'Ctrl-Y'),
                new MenuItem('', 0),
                new MenuItem('Command ~p~alette…', WorkbenchCommand::CommandPalette, null, 'Ctrl-P'),
            ),
            new SubMenu('~V~iew', Key::AltV)->items(
                new MenuItem('Cycle window ~p~alette', WorkbenchCommand::CyclePalette, Key::F8, 'F8'),
                new MenuItem('Open ~t~ask board', WorkbenchCommand::OpenTasks, Key::F2, 'F2'),
                new MenuItem('Open activity ~l~og', WorkbenchCommand::OpenActivity, Key::F3, 'F3'),
            ),
            new SubMenu('~D~emos', Key::AltD)->items(
                new MenuItem('Interactive ~d~ashboard', WorkbenchCommand::NewWorkspace, Key::F4, 'F4'),
                new MenuItem('Scrollable ~l~og', WorkbenchCommand::OpenActivity, Key::F3, 'F3'),
                new MenuItem('Modal ~d~ialog', WorkbenchCommand::About),
            ),
            new SubMenu('~W~indow', Key::AltW)->items(
                new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
                new MenuItem('~Z~oom', Cmd::Zoom, Key::F5, 'F5'),
                new MenuItem('~C~lose', Cmd::Close, null, 'Esc'),
            ),
            new SubMenu('~H~elp', Key::AltH)->items(
                new MenuItem('~K~eyboard reference', WorkbenchCommand::KeyboardHelp, Key::F1, 'F1'),
                new MenuItem('~A~bout TurboVision', WorkbenchCommand::About),
            ),
        );
    }

    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, new StatusDef(0, 0xFFFF)->items(
            new StatusItem('~F10~ Menu', Key::F10, Cmd::Menu),
            new StatusItem('~F1~ Help', Key::F1, WorkbenchCommand::KeyboardHelp),
            new StatusItem('~F2~ Tasks', Key::F2, WorkbenchCommand::OpenTasks),
            new StatusItem('~F3~ Activity', Key::F3, WorkbenchCommand::OpenActivity),
            new StatusItem('~F4~ New', Key::F4, WorkbenchCommand::NewWorkspace),
            new StatusItem('~F5~ Zoom', Key::F5, Cmd::Zoom),
            new StatusItem('~F6~ Next', Key::F6, Cmd::Next),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown && $event->asKey()?->keyCode === 0x03) {
            parent::handleEvent($event);

            return;
        }

        if ($this->dialog === null && $event->what === EventType::KeyDown) {
            $shortcut = match ($event->asKey()?->keyCode) {
                0x13 => WorkbenchCommand::SaveSnapshot, // Ctrl-S
                0x10 => WorkbenchCommand::CommandPalette, // Ctrl-P
                0x1A => WorkbenchCommand::Undo, // Ctrl-Z
                0x19 => WorkbenchCommand::Redo, // Ctrl-Y
                Key::Esc->value => Cmd::Close,
                default => null,
            };
            if ($shortcut !== null) {
                $this->putEvent(Event::command($shortcut));
                $this->clearEvent($event);

                return;
            }
        }

        if ($event->what === EventType::Command) {
            $command = $event->asMessage()?->command;
            if ($command === Cmd::Quit) {
                parent::handleEvent($event);

                return;
            }
            if ($command === WorkbenchCommand::ConfirmNew) {
                $this->closeDialog();
                $this->resetWorkspace();
                $this->clearEvent($event);

                return;
            }
            if ($command === WorkbenchCommand::CancelDialog) {
                $this->closeDialog();
                $this->clearEvent($event);

                return;
            }
        }

        if ($this->dialog !== null) {
            $this->dialog->handleEvent($event);

            return;
        }

        parent::handleEvent($event);
        if ($event->what !== EventType::Command) {
            return;
        }

        $message = $event->asMessage();
        if ($message === null) {
            return;
        }
        match ($message->command) {
            WorkbenchCommand::NewWorkspace => $this->confirmNewWorkspace(),
            WorkbenchCommand::OpenTasks => $this->openTaskWindow(),
            WorkbenchCommand::OpenActivity => $this->openActivityWindow(),
            WorkbenchCommand::SaveSnapshot => $this->showSnapshotDialog(),
            WorkbenchCommand::CyclePalette => $this->cycleWindowPalette(),
            WorkbenchCommand::KeyboardHelp => $this->showKeyboardHelp(),
            WorkbenchCommand::CommandPalette => $this->showCommandPalette(),
            WorkbenchCommand::About => $this->showAbout(),
            WorkbenchCommand::TaskDetails => $this->handleTaskDetails($message->info),
            WorkbenchCommand::Undo => $this->showNotice('Undo', 'The showcase history is already at its oldest state.'),
            WorkbenchCommand::Redo => $this->showNotice('Redo', 'There is no newer showcase state to restore.'),
            default => null,
        };
        $this->clearEvent($event);
    }

    public function dispatchForTest(Event $event): void
    {
        $this->handleEvent($event);
        $this->drawAndFlushForTest();
    }

    private function openInitialWindows(Desktop $desktop): void
    {
        $extent = $desktop->getExtent();
        if ($extent->width() >= 66) {
            $desktop->insertWindow($this->makeTaskWindow($extent));
        }
        $desktop->insertWindow($this->makeDashboardWindow($extent));
    }

    private function makeDashboardWindow(?Rect $extent = null): Window
    {
        $extent ??= $this->desktopForTest()?->getExtent() ?? $this->getExtent();
        $width = min(74, max(30, $extent->width() - 26));
        $height = min(23, max(12, $extent->height() - 2));
        $window = new Window(Rect::of(2, 1, 2 + $width, 1 + $height), 'Turbo Workbench', ++$this->windowNumber);
        $interior = $window->getExtent()->grow(-1, -1);
        $window->insert(new WorkbenchDashboard($interior));
        $window->setPalette($this->paletteIndex);

        return $window;
    }

    private function makeTaskWindow(?Rect $extent = null): Window
    {
        $extent ??= $this->desktopForTest()?->getExtent() ?? $this->getExtent();
        $width = min(34, max(20, intdiv($extent->width(), 3)));
        $height = min(18, max(9, $extent->height() - 5));
        $x = max(0, $extent->width() - $width - 2);
        $window = new Window(Rect::of($x, 3, $x + $width, 3 + $height), 'Task Board', ++$this->windowNumber);
        $vertical = $window->standardScrollBar(ScrollBarPart::Vertical | ScrollBarPart::HandleKeyboard);
        $interior = $window->getExtent()->grow(-1, -1);
        $list = new WorkbenchTaskList(Rect::of(1, 1, $interior->b->x - 1, $interior->b->y), $vertical, self::TASKS);
        $list->growMode = State::GrowHiX | State::GrowHiY;
        $window->insert($list);
        $window->setPalette($this->paletteIndex);

        return $window;
    }

    private function openTaskWindow(): void
    {
        $this->desktopForTest()?->insertWindow($this->makeTaskWindow());
        $this->log('Opened the interactive task board.');
    }

    private function openActivityWindow(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        $extent = $desktop->getExtent();
        $width = min(72, max(28, $extent->width() - 12));
        $height = min(18, max(8, $extent->height() - 6));
        $offset = ($this->windowNumber * 2) % 8;
        $x = min(max(0, 5 + $offset), max(0, $extent->width() - $width));
        $y = min(max(0, 2 + intdiv($offset, 2)), max(0, $extent->height() - $height));
        $window = new Window(Rect::of($x, $y, $x + $width, $y + $height), 'Activity Inspector', ++$this->windowNumber);
        $vertical = $window->standardScrollBar(ScrollBarPart::Vertical | ScrollBarPart::HandleKeyboard);
        $horizontal = $window->standardScrollBar(ScrollBarPart::Horizontal | ScrollBarPart::HandleKeyboard);
        $interior = $window->getExtent()->grow(-1, -1);
        $log = new WorkbenchLogView(
            Rect::of(1, 1, $interior->b->x - 1, $interior->b->y - 1),
            $horizontal,
            $vertical,
            array_merge($this->activity, [
                '22:48:05  Arrow keys and scrollbars move this independent viewport.',
                '22:48:06  PageUp/PageDown jump through longer application logs.',
                '22:48:07  Window chrome supports mouse move, resize, zoom, and close.',
                '22:48:08  F6 cycles focus through every open window.',
                '22:48:09  Menu commands and dashboard actions share one event bus.',
                '22:48:10  Modal dialogs trap input until confirmed or cancelled.',
            ]),
        );
        $log->growMode = State::GrowHiX | State::GrowHiY;
        $window->insert($log);
        $window->setPalette($this->paletteIndex);
        $desktop->insertWindow($window);
        $this->log('Opened the scrollable activity inspector.');
    }

    private function confirmNewWorkspace(): void
    {
        $this->showDialog(
            'Create a fresh workspace?',
            'This closes the current showcase windows and restores the dashboard and task board.',
            [
                ['label' => 'Create', 'command' => WorkbenchCommand::ConfirmNew],
                ['label' => 'Cancel', 'command' => WorkbenchCommand::CancelDialog],
            ],
        );
    }

    private function showSnapshotDialog(): void
    {
        $this->showNotice('Snapshot captured', 'The headless back buffer is ready for HTML rendering or pixel-diff screenshots.');
    }

    private function showKeyboardHelp(): void
    {
        $this->showNotice(
            'Keyboard reference',
            "F10 opens menus; arrows navigate; Enter selects; Esc closes. F2 tasks, F3 activity, F4 new, F5 zoom, F6 next, F8 palette. Tab moves focus inside a window.",
        );
    }

    private function showCommandPalette(): void
    {
        $this->showNotice(
            'Command palette',
            'F2 Open task board  ·  F3 Open activity inspector  ·  F4 New workspace  ·  F5 Zoom  ·  F6 Next window  ·  F8 Cycle palette',
        );
    }

    private function showAbout(): void
    {
        $this->showNotice(
            'TurboVision for PHP',
            'Classic windowing ideas, modern PHP 8.5 internals: typed events, Unicode cells, mouse capture, responsive geometry, deterministic rendering, and a testable terminal boundary.',
        );
    }

    private function showTaskDetails(string $task): void
    {
        $this->showNotice('Task details', $task . ' — selected from a real ListViewer with scrollbar synchronization.');
    }

    private function handleTaskDetails(mixed $task): void
    {
        if (is_string($task)) {
            $this->showTaskDetails($task);
        }
    }

    private function showNotice(string $title, string $message): void
    {
        $this->showDialog($title, $message, [
            ['label' => 'OK', 'command' => WorkbenchCommand::CancelDialog],
        ]);
    }

    /** @param list<array{label:string,command:int}> $actions */
    private function showDialog(string $title, string $message, array $actions): void
    {
        $this->closeDialog();
        $this->dialog = new WorkbenchDialog($this->getExtent(), $title, $message, $actions);
        $this->insert($this->dialog);
    }

    private function closeDialog(): void
    {
        if ($this->dialog !== null) {
            $this->remove($this->dialog);
            $this->dialog = null;
        }
    }

    private function resetWorkspace(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        foreach ($desktop->subviews() as $view) {
            if ($view instanceof Window) {
                $desktop->remove($view);
            }
        }
        $this->openInitialWindows($desktop);
        $this->log('Created a fresh showcase workspace.');
    }

    private function cycleWindowPalette(): void
    {
        $this->paletteIndex = ($this->paletteIndex + 1) % 3;
        foreach ($this->desktopForTest()?->subviews() ?? [] as $view) {
            if ($view instanceof Window) {
                $view->setPalette($this->paletteIndex);
            }
        }
        $this->log('Cycled all windows to palette ' . ['blue', 'cyan', 'gray'][$this->paletteIndex] . '.');
    }

    private function log(string $message): void
    {
        $this->activity[] = sprintf('22:48:%02d  %s', $this->activitySequence++, $message);
        if (count($this->activity) > 40) {
            array_shift($this->activity);
        }
    }
}
