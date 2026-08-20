<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Examples\Studio\StudioApp;
use HelgeSverre\TurboVision\Examples\Studio\StudioFocus;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Terminal\Screen;

/** @return array{StudioApp, HeadlessDriver, string, string} */
function studioAppForTest(): array
{
    $driver = new HeadlessDriver(120, 34);
    $suffix = bin2hex(random_bytes(6));
    $projectPath = sys_get_temp_dir() . '/tvision-studio-feature-' . $suffix . '.json';
    $exportPath = sys_get_temp_dir() . '/tvision-studio-feature-' . $suffix . '.php';
    $app = new StudioApp(new Screen($driver), $projectPath, $exportPath);
    $app->bootForTest();

    return [$app, $driver, $projectPath, $exportPath];
}

test('studio renders a polished toolbox, design canvas, inspector, layers, and activity pane', function (): void {
    [$app] = studioAppForTest();
    $app->drawAndFlushForTest();
    $screen = implode("\n", $app->backRowsForTest());
    $buffer = $app->screen()?->back();
    $explicitBackgroundCells = 0;
    if ($buffer !== null) {
        for ($y = 0; $y < $buffer->height; $y++) {
            for ($x = 0; $x < $buffer->width; $x++) {
                $explicitBackgroundCells += (($buffer->at($x, $y)->attr >> 4) & 0x07) === 0 ? 0 : 1;
            }
        }
    }

    expect($screen)->toContain('Turbo Studio')
        ->and($screen)->toContain('Components')
        ->and($screen)->toContain('Welcome Dashboard')
        ->and($screen)->toContain('Inspector')
        ->and($screen)->toContain('Direct manipulation')
        ->and($screen)->toContain('[R]  Radio')
        ->and($screen)->toContain('[=]  Progress')
        ->and($screen)->toContain('[A]  Text Area')
        ->and($screen)->toContain('(o) Fast mode')
        ->and($screen)->toContain('65%')
        ->and($screen)->toContain('Build notes')
        ->and($screen)->toContain('[Grid+]')
        ->and($screen)->toContain('[Snap+]')
        ->and($screen)->toContain('● Unsaved')
        ->and($buffer?->at(22, 30)->char)->toBe('┼')
        ->and($buffer?->at(89, 30)->char)->toBe('┼')
        ->and($explicitBackgroundCells)->toBe(0);
});

test('a malformed existing project cannot be overwritten by the starter fallback', function (): void {
    $suffix = bin2hex(random_bytes(6));
    $projectPath = sys_get_temp_dir() . '/tvision-studio-invalid-' . $suffix . '.json';
    $exportPath = sys_get_temp_dir() . '/tvision-studio-invalid-' . $suffix . '.php';
    $original = '{"version":1,"components":"broken"}';
    file_put_contents($projectPath, $original);
    $driver = new HeadlessDriver(120, 34);
    $app = new StudioApp(new Screen($driver), $projectPath, $exportPath);

    try {
        $app->bootForTest();
        $app->handleEvent(Event::keyDown(new KeyDownEvent(0x13)));

        expect(file_get_contents($projectPath))->toBe($original)
            ->and($app->studioView()->statusMessage())->toContain('Saving is disabled');
    } finally {
        $driver->shutdown();
        if (is_file($projectPath)) {
            unlink($projectPath);
        }
        if (is_file($exportPath)) {
            unlink($exportPath);
        }
    }
});

test('toolbox double-click adds a component and undo redo restore the design', function (): void {
    [$app] = studioAppForTest();
    $view = $app->studioView();
    $initialCount = count($view->project()->components());

    // Button is the third toolbox row, at y=5.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(5, 5), 1, true),
    ));
    $addedId = $view->selectedComponentId();

    expect($view->focusArea())->toBe(StudioFocus::Canvas)
        ->and($view->project()->components())->toHaveCount($initialCount + 1)
        ->and($view->selectedComponent()?->type->value)->toBe('button');

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(0x1A)));
    expect($view->project()->components())->toHaveCount($initialCount);

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(0x19)));
    expect($view->project()->components())->toHaveCount($initialCount + 1)
        ->and($view->project()->component($addedId ?? 0))->not->toBeNull();
});

test('canvas components can be dragged, resized, and edited through the inspector', function (): void {
    [$app] = studioAppForTest();
    $view = $app->studioView();
    $button = $view->selectedComponent();
    if ($button === null) {
        throw new RuntimeException('Expected the starter project button to be selected.');
    }

    expect($button->x)->toBe(34)->and($button->y)->toBe(5);

    // The starter button spans screen columns 63..74 on row 12.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(67, 12), 1),
    ));
    $app->dispatchForTest(Event::mouse(
        EventType::MouseMove,
        new MouseEvent(new Point(69, 13), 1),
    ));
    $app->dispatchForTest(Event::mouse(
        EventType::MouseUp,
        new MouseEvent(new Point(69, 13), 1),
    ));

    expect($button->x)->toBe(36)->and($button->y)->toBe(6);

    // The lower-right themed marker is the resize handle after the move.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(77, 13), 1),
    ));
    $app->dispatchForTest(Event::mouse(
        EventType::MouseMove,
        new MouseEvent(new Point(80, 13), 1),
    ));
    $app->dispatchForTest(Event::mouse(
        EventType::MouseUp,
        new MouseEvent(new Point(80, 13), 1),
    ));
    expect($button->width)->toBe(16)
        ->and($button->height)->toBe(1);

    // Double-click the Text property and append to its current value.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(102, 6), 1, true),
    ));
    foreach (mb_str_split(' now') as $character) {
        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord($character), $character)));
    }
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Enter->value)));

    expect($button->text)->toBe('Run now')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Run now');
});

test('themes restyle the full interface while retaining the terminal background', function (): void {
    [$app] = studioAppForTest();
    $app->drawAndFlushForTest();
    $before = $app->screen()?->back();
    $beforeAttributes = [];
    if ($before !== null) {
        for ($y = 0; $y < $before->height; $y++) {
            for ($x = 0; $x < $before->width; $x++) {
                $beforeAttributes[] = $before->at($x, $y)->attr;
            }
        }
    }

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::F2->value)));
    $after = $app->screen()?->back();
    $changedAttributes = 0;
    $explicitBackgroundCells = 0;
    if ($after !== null) {
        $index = 0;
        for ($y = 0; $y < $after->height; $y++) {
            for ($x = 0; $x < $after->width; $x++) {
                $attribute = $after->at($x, $y)->attr;
                $changedAttributes += ($beforeAttributes[$index] ?? $attribute) === $attribute ? 0 : 1;
                $explicitBackgroundCells += ($attribute & 0xF0) === 0 ? 0 : 1;
                $index++;
            }
        }
    }

    expect($app->studioView()->themeName())->toBe('Ultraviolet')
        ->and($changedAttributes)->toBeGreaterThan(500)
        ->and($explicitBackgroundCells)->toBe(0)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('* Design')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('▒');

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::F2->value)));
    expect($app->studioView()->themeName())->toBe('Amber')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('# Design')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('▓');
});

test('grid snapping and alignment controls support precise canvas layout', function (): void {
    [$app] = studioAppForTest();
    $view = $app->studioView();
    $button = $view->selectedComponent();
    if ($button === null) {
        throw new RuntimeException('Expected the starter project button to be selected.');
    }

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord('g'), 'g')));
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord('s'), 's')));
    expect($view->gridVisible())->toBeFalse()
        ->and($view->snapEnabled())->toBeFalse()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('[Grid-]')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('[Snap-]');

    $startX = $button->x;
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Right->value)));
    expect($button->x)->toBe($startX + 1);

    // The third context action aligns the selected component to the left edge.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(68, 12), 4),
    ));
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Down->value)));
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Down->value)));
    expect(implode("\n", $app->backRowsForTest()))->toContain('Align Left');
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Enter->value)));

    expect($button->x)->toBe(0);
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord('h'), 'h')));
    expect($button->x)->toBe(intdiv($view->project()->width() - $button->width, 2));
});

test('component context actions, preview, code view, project save, and PHP export work', function (): void {
    [$app, , $projectPath, $exportPath] = studioAppForTest();
    $view = $app->studioView();

    try {
        $initialCount = count($view->project()->components());
        $app->dispatchForTest(Event::mouse(
            EventType::MouseDown,
            new MouseEvent(new Point(67, 12), 4),
        ));
        expect(implode("\n", $app->backRowsForTest()))->toContain('Bring Forward');

        // First context action duplicates the selected button.
        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Enter->value)));
        expect($view->project()->components())->toHaveCount($initialCount + 1);

        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::F5->value)));
        expect($view->previewOpen())->toBeTrue()
            ->and(implode("\n", $app->backRowsForTest()))->toContain('PREVIEW');
        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Esc->value)));

        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(0x13)));
        expect(is_file($projectPath))->toBeTrue();

        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::F9->value)));
        expect($view->codeOpen())->toBeTrue()
            ->and(implode("\n", $app->backRowsForTest()))->toContain('Generated PHP');
        $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord('e'), 'e')));

        expect(is_file($exportPath))->toBeTrue()
            ->and((string) file_get_contents($exportPath))->toContain('GeneratedStudioApp');
    } finally {
        if (is_file($projectPath)) {
            unlink($projectPath);
        }
        if (is_file($exportPath)) {
            unlink($exportPath);
        }
    }
});

test('studio reflows and shows a clear compact warning below its minimum size', function (): void {
    [$app, $driver] = studioAppForTest();
    $driver->resizeTo(90, 24);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();

    expect($app->studioView()->getBounds()->width())->toBe(90)
        ->and($app->studioView()->getBounds()->height())->toBe(24)
        ->and(implode("\n", $app->backRowsForTest()))->toContain('Turbo Studio needs at least 100 × 26');
});
