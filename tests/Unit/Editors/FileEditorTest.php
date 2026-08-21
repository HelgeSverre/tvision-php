<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Editors\EditWindow;
use HelgeSverre\TurboVision\Editors\EditorDialogKind;
use HelgeSverre\TurboVision\Editors\FileEditor;
use HelgeSverre\TurboVision\Editors\SearchOptions;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

test('file editor loads, saves, backs up and reports a missing save target', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'tv-editor-');
    file_put_contents($path, 'before');

    try {
        $editor = new FileEditor(Rect::of(0, 0, 20, 5), fileName: $path);
        expect($editor->text())->toBe('before')
            ->and($editor->modified)->toBeFalse();

        $editor->insertText('!');
        $editor->editorFlags |= SearchOptions::BackupFiles;
        expect($editor->save())->toBeTrue()
            ->and(file_get_contents($path))->toBe('before!')
            ->and(file_get_contents($path . '~'))->toBe('before')
            ->and($editor->modified)->toBeFalse();

        $untitled = new FileEditor(Rect::of(0, 0, 20, 5));
        $seen = null;
        $untitled->setDialogHandler(function ($request) use (&$seen): int {
            $seen = $request;

            return 11;
        });
        expect($untitled->save())->toBeFalse()
            ->and($untitled->lastError)->toContain('untitled')
            ->and($seen?->kind)->toBe(EditorDialogKind::SaveUntitled);
    } finally {
        @unlink($path);
        @unlink($path . '~');
    }
});

test('file editor save as changes its target and edit window composes editor chrome', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'tv-editor-as-');
    @unlink($path);
    try {
        $editor = new FileEditor(Rect::of(0, 0, 20, 5));
        $editor->insertText('new');
        expect($editor->saveAs($path))->toBeTrue()
            ->and($editor->fileName)->toBe($path)
            ->and(file_get_contents($path))->toBe('new');

        $window = new EditWindow(Rect::of(0, 0, 30, 10), $path, 1);
        expect($window->frameTitle())->toBe(basename($path))
            ->and($window->editor->text())->toBe('new');
    } finally {
        @unlink($path);
    }
});

test('edit window renders a loaded document with its standard chrome', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'tv-editor-window-');
    file_put_contents($path, 'rendered document');
    try {
        $screen = new Screen(new HeadlessDriver(50, 16));
        $screen->init();
        $root = new class($screen) extends Group
        {
            public function __construct(private readonly Screen $screenRef)
            {
                parent::__construct(Rect::of(0, 0, $screenRef->cols(), $screenRef->rows()));
            }

            public function screen(): Screen
            {
                return $this->screenRef;
            }
        };
        $window = new EditWindow(Rect::of(2, 2, 38, 13), $path);
        $root->insert($window);
        $root->draw();

        expect(implode("\n", $screen->back()->rows()))->toContain('rendered document');
    } finally {
        @unlink($path);
    }
});
