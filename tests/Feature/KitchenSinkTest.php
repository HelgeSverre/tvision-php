<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\PaletteMode;
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\FileCommand;
use HelgeSverre\TurboVision\Dialogs\History;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\MultiCheckBoxes;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Editors\EditWindow;
use HelgeSverre\TurboVision\Editors\FileEditor;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Examples\KitchenSink\KitchenSinkApp;
use HelgeSverre\TurboVision\Examples\KitchenSink\KitchenSinkCommand;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Text\Terminal;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

require_once __DIR__ . '/../../examples/php/kitchensink.php';

/** @return array{KitchenSinkApp,HeadlessDriver} */
function bootKitchenSink(int $width = 120, int $height = 36): array
{
    $driver = new HeadlessDriver($width, $height);
    $app = new KitchenSinkApp(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    return [$app, $driver];
}

function kitchenSinkText(KitchenSinkApp $app): string
{
    return implode("\n", $app->backRowsForTest());
}

/** @return list<Window> */
function kitchenSinkWindows(KitchenSinkApp $app): array
{
    return array_values(array_filter(
        $app->desktopForTest()?->subviews() ?? [],
        static fn (View $view): bool => $view instanceof Window,
    ));
}

function kitchenSinkEditor(KitchenSinkApp $app): FileEditor
{
    foreach (kitchenSinkWindows($app) as $window) {
        if ($window instanceof EditWindow) {
            return $window->editor;
        }
    }

    throw new RuntimeException('The Kitchen Sink has no open EditWindow.');
}

/** @return list<FileEditor> */
function kitchenSinkEditors(KitchenSinkApp $app): array
{
    return array_values(array_map(
        static fn (EditWindow $window): FileEditor => $window->editor,
        array_filter(
            kitchenSinkWindows($app),
            static fn (Window $window): bool => $window instanceof EditWindow,
        ),
    ));
}

function kitchenSinkCurrentWindowNumber(KitchenSinkApp $app): int
{
    $current = $app->desktopForTest()?->current();

    return $current instanceof Window ? $current->frameNumber() : 0;
}

function queueKitchenSinkInput(KitchenSinkApp $app, string $value, int $accept = Cmd::Ok): void
{
    $app->putEvent(Event::keyDown(new KeyDownEvent(0x01)));
    foreach (str_split($value) as $character) {
        $app->putEvent(Event::keyDown(new KeyDownEvent(ord($character), $character)));
    }
    $app->putEvent(Event::command($accept));
}

test('Kitchen Sink boots as a comprehensive interactive desktop', function (): void {
    [$app] = bootKitchenSink();
    $screen = kitchenSinkText($app);

    expect($app->windowCount())->toBe(3)
        ->and($screen)->toContain('Labs  Theme  Window  Tools  Help')
        ->and($screen)->toContain('TURBOVISION // KITCHEN SINK')
        ->and($screen)->toContain('Feature Navigator')
        ->and($screen)->toContain('Forms + validators')
        ->and($screen)->toContain('Live Event Terminal')
        ->and($screen)->toContain('F10 Menu');
});

test('deep menus remain compact and leave the application visible beneath them', function (): void {
    [$app] = bootKitchenSink();

    $app->dispatchForTest(Event::key(Key::AltL));
    $screen = kitchenSinkText($app);

    expect($screen)->toContain('Control gallery')
        ->and($screen)->toContain('Data views')
        ->and($screen)->toContain('Files + data')
        ->and($screen)->toContain('Feature Navigator')
        ->and($screen)->toContain('Live Event Terminal');
});

test('controls laboratory composes every control family and all validator inputs', function (): void {
    [$app] = bootKitchenSink();
    $dialog = $app->controlsDialogForTest();
    $children = $dialog->subviews();

    expect(array_filter($children, static fn ($view): bool => $view instanceof InputLine))->toHaveCount(4)
        ->and(array_filter($children, static fn ($view): bool => $view instanceof History))->toHaveCount(1)
        ->and(array_filter($children, static fn ($view): bool => $view instanceof CheckBoxes))->toHaveCount(1)
        ->and(array_filter($children, static fn ($view): bool => $view instanceof RadioButtons))->toHaveCount(1)
        ->and(array_filter($children, static fn ($view): bool => $view instanceof MultiCheckBoxes))->toHaveCount(1)
        ->and($dialog->valid(Cmd::Ok))->toBeTrue();
});

test('blocking labs are physically presented and transfer accepted form data', function (): void {
    [$app, $driver] = bootKitchenSink();
    $driver->takeOutput();
    $app->putEvent(Event::command(Cmd::Ok));

    $app->dispatchForTest(Event::command(KitchenSinkCommand::Controls));

    expect($driver->output())->toContain('Controls + Validation Laboratory')
        ->and($app->controlsDataForTest())->toContain('Ada Lovelace', 36, '824', 'dark');
});

test('modeless data labs open real editor scroller outline terminal and resource windows', function (): void {
    [$app] = bootKitchenSink();

    foreach ([
        KitchenSinkCommand::Editor,
        KitchenSinkCommand::Canvas,
        KitchenSinkCommand::Outline,
        KitchenSinkCommand::Terminal,
        KitchenSinkCommand::Resources,
    ] as $command) {
        $app->dispatchForTest(Event::command($command));
    }
    $screen = kitchenSinkText($app);

    expect($app->windowCount())->toBe(8)
        ->and($screen)->toContain('Rebuilt Resource Tree')
        ->and($screen)->toContain('RESOURCEFILE')
        ->and($screen)->toContain('Fresh runtime owner');
});

test('every modal lab enters and leaves a real modal loop safely', function (): void {
    [$app] = bootKitchenSink();

    foreach ([
        KitchenSinkCommand::Controls,
        KitchenSinkCommand::MessageBoxes,
        KitchenSinkCommand::Memo,
        KitchenSinkCommand::FileDialog,
        KitchenSinkCommand::ChangeDirectory,
        KitchenSinkCommand::Colors,
        KitchenSinkCommand::About,
    ] as $command) {
        $app->putEvent(Event::command(Cmd::Cancel));
        $app->dispatchForTest(Event::command($command));
    }
    $app->putEvent(Event::key(Key::Esc));
    $app->dispatchForTest(Event::command(KitchenSinkCommand::ContextMenu));
    $app->putEvent(Event::key(Key::Esc));
    $app->dispatchForTest(Event::command(Cmd::Help));

    expect($app->windowCount())->toBe(3)
        ->and(kitchenSinkText($app))->toContain('Feature Navigator');
});

test('context help opens from inside a file dialog', function (): void {
    [$app, $driver] = bootKitchenSink();
    $driver->takeOutput();
    $app->putEvent(Event::command(Cmd::Help));
    $app->putEvent(Event::key(Key::Esc));
    $app->putEvent(Event::command(Cmd::Cancel));

    $app->dispatchForTest(Event::command(KitchenSinkCommand::FileDialog));

    expect($driver->output())->toContain('Help')
        ->and($driver->output())->toContain('EditWindow combines');
});

test('CommandSet integration disables advanced launches and redraws command state', function (): void {
    [$app] = bootKitchenSink();

    $app->dispatchForTest(Event::command(KitchenSinkCommand::ToggleAdvanced));

    expect($app->advancedLabsEnabled())->toBeFalse()
        ->and($app->commandEnabled(KitchenSinkCommand::FileDialog))->toBeFalse()
        ->and($app->commandEnabled(KitchenSinkCommand::Resources))->toBeFalse();

    $before = $app->windowCount();
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Resources));
    expect($app->windowCount())->toBe($before);

    $app->dispatchForTest(Event::command(KitchenSinkCommand::ToggleAdvanced));
    expect($app->advancedLabsEnabled())->toBeTrue()
        ->and($app->commandEnabled(KitchenSinkCommand::Resources))->toBeTrue();
});

test('palette modes and desktop layout commands work across the live view tree', function (): void {
    [$app] = bootKitchenSink();

    $app->dispatchForTest(Event::command(KitchenSinkCommand::ThemeClassic));
    expect($app->paletteMode())->toBe(PaletteMode::ClassicColor);
    $app->dispatchForTest(Event::command(KitchenSinkCommand::CycleTheme));
    expect($app->paletteMode())->toBe(PaletteMode::BlackWhite);

    $app->dispatchForTest(Event::command(Cmd::Tile));
    $app->dispatchForTest(Event::command(Cmd::Cascade));
    expect($app->windowCount())->toBe(3);
});

test('Kitchen Sink reflows its complete desktop after terminal resize', function (): void {
    [$app, $driver] = bootKitchenSink();
    $navigator = null;
    foreach (kitchenSinkWindows($app) as $window) {
        if ($window->frameTitle() === 'Feature Navigator') {
            $navigator = $window;
            $navigator->moveTo($window->getBounds()->a->x, 10);
            break;
        }
    }

    $driver->resizeTo(86, 27);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();

    expect($app->backRowsForTest())->toHaveCount(27)
        ->and(kitchenSinkText($app))->toContain('KITCHEN SINK')
        ->and(kitchenSinkText($app))->toContain('Feature Navigator')
        ->and(kitchenSinkText($app))->toContain('11  Resource round-trip')
        ->and(kitchenSinkText($app))->toContain('12  Context menu')
        ->and(kitchenSinkText($app))->not->toContain('Feature Naviga3o')
        ->and(kitchenSinkText($app))->toContain('F10 Menu')
        ->and($navigator?->getBounds()->b->y)->toBeLessThanOrEqual($app->desktopForTest()?->getExtent()->b->y ?? 0);
});

test('compact mode preserves live windows until the terminal is large enough again', function (): void {
    [$app, $driver] = bootKitchenSink();
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    $numbers = array_map(static fn (Window $window): int => $window->frameNumber(), kitchenSinkWindows($app));

    $driver->resizeTo(79, 24);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();
    expect($app->windowCount())->toBe(0)
        ->and(kitchenSinkText($app))->toContain('needs at least 80×25 terminal cells');

    $app->dispatchForTest(Event::command(KitchenSinkCommand::Canvas));
    expect($app->windowCount())->toBe(0);

    $driver->resizeTo(120, 36);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();
    expect($app->windowCount())->toBe(4)
        ->and(array_map(static fn (Window $window): int => $window->frameNumber(), kitchenSinkWindows($app)))->toBe($numbers)
        ->and(kitchenSinkText($app))->toContain('Untitled');
});

test('window numbers are reused and reset so Alt-number selection remains dependable', function (): void {
    [$app] = bootKitchenSink();
    expect(array_map(static fn (Window $window): int => $window->frameNumber(), kitchenSinkWindows($app)))->toBe([1, 2, 3]);

    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    expect($app->desktopForTest()?->current())->toBeInstanceOf(EditWindow::class)
        ->and(kitchenSinkCurrentWindowNumber($app))->toBe(4);
    $app->dispatchForTest(Event::command(Cmd::Close));
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    $app->dispatchForTest(Event::key(Key::Alt4));
    expect(kitchenSinkCurrentWindowNumber($app))->toBe(4);

    $app->dispatchForTest(Event::command(KitchenSinkCommand::ResetDesktop));
    expect(array_map(static fn (Window $window): int => $window->frameNumber(), kitchenSinkWindows($app)))->toBe([1, 2, 3]);
});

test('modified editors require an explicit discard before close or desktop reset', function (): void {
    [$app] = bootKitchenSink();
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    $editor = kitchenSinkEditor($app);
    $editor->setText('unsaved', true);

    $app->putEvent(Event::command(Cmd::Cancel));
    $app->dispatchForTest(Event::command(Cmd::Close));
    expect($app->windowCount())->toBe(4)
        ->and($editor->owner)->not->toBeNull();

    $app->putEvent(Event::command(Cmd::Cancel));
    $app->dispatchForTest(Event::command(KitchenSinkCommand::ResetDesktop));
    expect($app->windowCount())->toBe(4);

    $app->putEvent(Event::command(Cmd::No));
    $app->dispatchForTest(Event::command(KitchenSinkCommand::ResetDesktop));
    expect($app->windowCount())->toBe(3)
        ->and($editor->owner?->owner)->toBeNull();
});

test('batch window actions do not discard any editor when a later prompt is cancelled', function (): void {
    [$app] = bootKitchenSink();
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    $editors = kitchenSinkEditors($app);
    expect($editors)->toHaveCount(2);
    foreach ($editors as $index => $editor) {
        $editor->setText('unsaved ' . $index, true);
    }

    $app->putEvent(Event::command(Cmd::No));
    $app->putEvent(Event::command(Cmd::Cancel));
    $app->dispatchForTest(Event::command(Cmd::CloseAll));

    expect($app->windowCount())->toBe(5)
        ->and(array_map(static fn (FileEditor $editor): bool => $editor->modified, $editors))->toBe([true, true])
        ->and(array_map(static fn (FileEditor $editor): bool => $editor->owner !== null, $editors))->toBe([true, true]);
});

test('editor menu workflows find replace and atomically save an untitled buffer', function (): void {
    [$app] = bootKitchenSink();
    $app->dispatchForTest(Event::command(KitchenSinkCommand::Editor));
    $editor = kitchenSinkEditor($app);
    $editor->setText('alpha alpha', true);

    queueKitchenSinkInput($app, 'alpha');
    $app->dispatchForTest(Event::command(Cmd::Find));
    expect($editor->selectedText())->toBe('alpha');

    queueKitchenSinkInput($app, 'alpha');
    queueKitchenSinkInput($app, 'omega');
    $app->putEvent(Event::command(Cmd::Yes));
    $app->putEvent(Event::command(Cmd::Yes));
    $app->dispatchForTest(Event::command(Cmd::Replace));
    expect($editor->text())->toBe('omega omega');

    $path = tempnam(sys_get_temp_dir(), 'tvision-kitchen-save-');
    expect($path)->toBeString();
    if (! is_string($path)) {
        return;
    }
    unlink($path);
    try {
        queueKitchenSinkInput($app, $path, FileCommand::Open);
        $app->dispatchForTest(Event::command(Cmd::SaveAs));

        expect($editor->fileName)->toBe($path)
            ->and(file_get_contents($path))->toBe('omega omega')
            ->and($editor->modified)->toBeFalse();
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('activity logging promotes a live Terminal after its original window closes', function (): void {
    [$app] = bootKitchenSink();
    $oldTerminal = null;
    $oldWindow = null;
    foreach (kitchenSinkWindows($app) as $window) {
        foreach ($window->subviews() as $child) {
            if ($child instanceof Terminal) {
                $oldTerminal = $child;
                $oldWindow = $window;
                break 2;
            }
        }
    }
    expect($oldTerminal)->toBeInstanceOf(Terminal::class)
        ->and($oldWindow)->toBeInstanceOf(Window::class);
    if (! $oldTerminal instanceof Terminal || ! $oldWindow instanceof Window) {
        return;
    }
    $oldLines = $oldTerminal->scrollback();
    $oldWindow->close();

    $app->dispatchForTest(Event::command(KitchenSinkCommand::Terminal));
    $liveLogs = [];
    foreach (kitchenSinkWindows($app) as $window) {
        foreach ($window->subviews() as $child) {
            if ($child instanceof Terminal) {
                array_push($liveLogs, ...$child->scrollback());
            }
        }
    }

    expect($oldTerminal->scrollback())->toBe($oldLines)
        ->and(implode("\n", $liveLogs))->toContain('Opened another bounded, reflowing Terminal text device.');
});

test('the global Ctrl-C escape hatch survives the comprehensive menu bindings', function (): void {
    [$app, $driver] = bootKitchenSink();
    $driver->feedInput("\x03");
    $event = $app->pumpEvent();

    expect($event)->not->toBeNull();
    if ($event !== null) {
        $app->dispatchForTest($event);
    }

    expect($app->ended())->toBeTrue();
});

test('the real Alt-X terminal sequence exits from inside a blocking modal', function (): void {
    [$app, $driver] = bootKitchenSink();
    $driver->feedInput("\ex");

    $app->dispatchForTest(Event::command(KitchenSinkCommand::Controls));

    expect($app->ended())->toBeTrue();
});
