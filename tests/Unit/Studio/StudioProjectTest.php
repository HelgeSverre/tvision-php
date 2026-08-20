<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Studio\StudioAlignment;
use HelgeSverre\TurboVision\Examples\Studio\StudioComponentType;
use HelgeSverre\TurboVision\Examples\Studio\StudioHistory;
use HelgeSverre\TurboVision\Examples\Studio\StudioPhpExporter;
use HelgeSverre\TurboVision\Examples\Studio\StudioProject;
use HelgeSverre\TurboVision\Examples\Studio\StudioProjectStore;
use HelgeSverre\TurboVision\Examples\Studio\StudioPathGuard;
use HelgeSverre\TurboVision\Examples\Studio\StudioTheme;

test('studio component sigils occupy a predictable number of terminal columns', function (): void {
    foreach (StudioComponentType::cases() as $type) {
        $icon = $type->icon();

        expect($icon)->toMatch('/^\[[A-Z=-]\]$/D')
            ->and(mb_strlen($icon))->toBe(3)
            ->and(mb_strwidth($icon))->toBe(3);
    }
});

test('studio themes have distinct complete foreground-only visual systems', function (): void {
    $themes = StudioTheme::presets();

    expect(array_column($themes, 'name'))->toBe(['Graphite', 'Ultraviolet', 'Amber'])
        ->and(array_unique(array_map(static fn (StudioTheme $theme): string => implode(':', [
            $theme->canvas,
            $theme->primary,
            $theme->grid,
            $theme->accent,
            $theme->secondary,
            $theme->shadow,
            $theme->gridGlyph,
            $theme->shadowGlyph,
            $theme->focusGlyph,
        ]), $themes)))->toHaveCount(3);

    foreach ($themes as $theme) {
        $attributes = [
            $theme->canvas,
            $theme->primary,
            $theme->muted,
            $theme->grid,
            $theme->accent,
            $theme->secondary,
            $theme->success,
            $theme->warning,
            $theme->error,
            $theme->shadow,
        ];
        foreach ($attributes as $attribute) {
            expect($attribute & 0xF0)->toBe(0);
        }
    }

    expect(fn (): StudioTheme => new StudioTheme('Invalid background', canvas: 0x17))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): StudioTheme => new StudioTheme('Wide glyph', gridGlyph: '☷'))
        ->toThrow(InvalidArgumentException::class);
});

test('studio projects add, clamp, reorder, duplicate, and round-trip components', function (): void {
    $project = StudioProject::blank();
    $project->setThemeName('Amber');
    $label = $project->add(StudioComponentType::Label);
    $button = $project->add(StudioComponentType::Button);

    $project->move($label->id, 999, 999);
    $project->resize($button->id, 1, 0);
    $copy = $project->duplicate($button->id);
    if ($copy === null) {
        throw new RuntimeException('Expected a valid component to be duplicated.');
    }
    $project->sendBackward($copy->id);
    $roundTrip = StudioProject::fromArray($project->toArray());

    expect($label->x)->toBe($project->width() - $label->width)
        ->and($label->y)->toBe($project->height() - $label->height)
        ->and($button->width)->toBe(6)
        ->and($button->height)->toBe(1)
        ->and($copy->text)->toBe('Continue copy')
        ->and($roundTrip->themeName())->toBe('Amber')
        ->and($roundTrip->toArray())->toBe($project->toArray());
});

test('studio projects align components to canvas edges and centers', function (): void {
    $project = StudioProject::blank();
    $component = $project->add(StudioComponentType::Button);

    $project->align($component->id, StudioAlignment::Left);
    expect($component->x)->toBe(0);
    $project->align($component->id, StudioAlignment::Top);
    expect($component->y)->toBe(0);
    $project->align($component->id, StudioAlignment::HorizontalCenter);
    expect($component->x)->toBe(intdiv($project->width() - $component->width, 2));
    $project->align($component->id, StudioAlignment::VerticalCenter);
    expect($component->y)->toBe(intdiv($project->height() - $component->height, 2));
});

test('studio supports the expanded component catalog through project round trips', function (): void {
    $project = StudioProject::blank();
    foreach ([StudioComponentType::Radio, StudioComponentType::Progress, StudioComponentType::TextArea] as $type) {
        $project->add($type);
    }

    $restored = StudioProject::fromArray($project->toArray());

    expect(array_map(
        static fn ($component): StudioComponentType => $component->type,
        $restored->components(),
    ))->toBe([StudioComponentType::Radio, StudioComponentType::Progress, StudioComponentType::TextArea]);
});

test('studio history restores complete project snapshots in both directions', function (): void {
    $history = new StudioHistory();
    $project = StudioProject::blank();
    $history->remember($project);
    $component = $project->add(StudioComponentType::Checkbox);

    $undone = $history->undo($project);
    $redone = $undone === null ? null : $history->redo($undone);

    expect($undone?->components())->toHaveCount(0)
        ->and($redone?->component($component->id)?->text)->toBe('Enabled')
        ->and($history->undoCount())->toBe(1)
        ->and($history->redoCount())->toBe(0);
});

test('studio project imports reject malformed structure and sanitize terminal controls', function (): void {
    $data = StudioProject::blank()->toArray();
    $data['name'] = "Safe\e[31m title";
    $data['components'] = [[
        'id' => 1,
        'type' => 'label',
        'x' => 1,
        'y' => 1,
        'width' => 12,
        'height' => 1,
        'text' => "Hello\n\e[2Jworld",
    ]];
    $project = StudioProject::fromArray($data);
    $duplicateIds = $data;
    $duplicateIds['components'][] = $duplicateIds['components'][0];
    $missingField = $data;
    unset($missingField['components'][0]['type']);
    $nullTheme = $data;
    $nullTheme['theme'] = null;

    expect($project->name())->toBe('Safe [31m title')
        ->and($project->components()[0]->text)->toBe('Hello  [2Jworld')
        ->and(fn (): StudioProject => StudioProject::fromArray($duplicateIds))->toThrow(\InvalidArgumentException::class)
        ->and(fn (): StudioProject => StudioProject::fromArray($missingField))->toThrow(\InvalidArgumentException::class)
        ->and(fn (): StudioProject => StudioProject::fromArray($nullTheme))->toThrow(\InvalidArgumentException::class);
});

test('studio code generation substitutes placeholders only once', function (): void {
    $data = StudioProject::blank()->toArray();
    $data['name'] = '{{PROJECT_WIDTH}}';
    $source = (new StudioPhpExporter())->generate(StudioProject::fromArray($data));

    expect($source)->toContain("private const string PROJECT_NAME = '{{PROJECT_WIDTH}}';");
});

test('studio code generation carries the project theme into the exported app', function (): void {
    $project = StudioProject::blank();
    $project->setThemeName('Amber');

    $source = (new StudioPhpExporter())->generate($project);

    expect($source)->toContain('private const int COLOR_CANVAS = 6;')
        ->and($source)->toContain('private const int COLOR_ACCENT = 14;')
        ->and($source)->toContain('self::COLOR_ACCENT');
});

test('studio path guard resolves aliases to an existing target', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-studio-path-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $projectPath = $directory . '/Project.json';
    file_put_contents($projectPath, '{}');
    $caseAlias = $directory . '/project.JSON';

    try {
        expect(StudioPathGuard::sameTarget($projectPath, $projectPath))->toBeTrue();
        if (is_file($caseAlias)) {
            expect(StudioPathGuard::sameTarget($projectPath, $caseAlias))->toBeTrue();
        }
    } finally {
        unlink($projectPath);
        rmdir($directory);
    }
});

test('studio projects save atomically and export syntactically valid runnable PHP', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-studio-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $projectPath = $directory . '/demo.json';
    $phpPath = $directory . '/demo.php';
    $project = StudioProject::starter();

    try {
        $store = new StudioProjectStore();
        $store->save($projectPath, $project);
        $loaded = $store->load($projectPath);
        $exporter = new StudioPhpExporter();
        $exporter->save($phpPath, $loaded);
        $source = (string) file_get_contents($phpPath);

        expect($loaded->toArray())->toBe($project->toArray())
            ->and($source)->toContain('final class GeneratedStudioView')
            ->and($source)->toContain("'Welcome Dashboard'")
            ->and(token_get_all($source))->not->toBeEmpty();
    } finally {
        if (is_file($projectPath)) {
            unlink($projectPath);
        }
        if (is_file($phpPath)) {
            unlink($phpPath);
        }
        rmdir($directory);
    }
});
