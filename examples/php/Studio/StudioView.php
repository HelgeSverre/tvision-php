<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use RuntimeException;

/**
 * A self-hosting, mouse-driven interface builder that demonstrates compound TUI
 * state: tools, layers, direct manipulation, property editing, history, preview,
 * persistence, and deterministic PHP source generation.
 */
final class StudioView extends View
{
    /** @var list<string> */
    private const array CONTEXT_ACTIONS = [
        'Duplicate',
        'Delete',
        'Align Left',
        'Center Horizontally',
        'Align Top',
        'Center Vertically',
        'Bring Forward',
        'Send Backward',
    ];

    private StudioFocus $focus = StudioFocus::Canvas;

    private int $toolIndex = 2;

    private ?int $selectedId = 4;

    private ?int $hoveredId = null;

    private int $propertyIndex = 0;

    private bool $propertyEditing = false;

    private string $propertyBuffer = '';

    private int $propertyCursor = 0;

    private bool $previewOpen = false;

    private bool $codeOpen = false;

    private int $codeScroll = 0;

    private ?Point $dragLast = null;

    private ?string $dragMode = null;

    private bool $dragRemembered = false;

    private ?Point $contextOrigin = null;

    private int $contextIndex = 0;

    /** @var list<string> */
    private array $activity = [];

    /** @var list<StudioTheme> */
    private array $themes;

    private int $themeIndex = 0;

    private bool $gridVisible = true;

    private bool $snapEnabled = true;

    private bool $dirty = false;

    private string $status = '';

    private bool $statusIsError = false;

    public function __construct(
        Rect $bounds,
        private StudioProject $project,
        private readonly StudioHistory $history,
        private readonly StudioProjectStore $store,
        private readonly StudioPhpExporter $exporter,
        private readonly string $projectPath,
        private readonly string $exportPath,
        bool $initiallyDirty = false,
    ) {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick;
        $this->themes = StudioTheme::presets();
        $this->dirty = $initiallyDirty;
        $this->repairSelection();
        $this->activity = [
            'Project workspace ready.',
            'Select a component, drag it, or double-click a tool to add one.',
        ];
        $this->status = 'Tab panels  •  Arrows move  •  +/- resize  •  Enter edit  •  F5 preview';
    }

    public function project(): StudioProject
    {
        return $this->project;
    }

    public function selectedComponent(): ?StudioComponent
    {
        return $this->selectedId === null ? null : $this->project->component($this->selectedId);
    }

    public function selectedComponentId(): ?int
    {
        return $this->selectedId;
    }

    public function focusArea(): StudioFocus
    {
        return $this->focus;
    }

    public function previewOpen(): bool
    {
        return $this->previewOpen;
    }

    public function codeOpen(): bool
    {
        return $this->codeOpen;
    }

    public function statusMessage(): string
    {
        return $this->status;
    }

    public function themeName(): string
    {
        return $this->theme()->name;
    }

    public function gridVisible(): bool
    {
        return $this->gridVisible;
    }

    public function snapEnabled(): bool
    {
        return $this->snapEnabled;
    }

    public function showStatus(string $message, bool $error = false): void
    {
        $this->setStatus($message, $error);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $this->fillRect(0, 0, $width, $height, ' ', $this->theme()->canvas);
        if ($width < 100 || $height < 26) {
            $this->drawCompactWarning($width, $height);

            return;
        }
        if ($this->previewOpen) {
            $this->drawPreview();

            return;
        }

        $this->drawToolbar();
        $this->drawStructure();
        $this->drawToolbox();
        $this->drawCanvas();
        $this->drawInspector();
        $this->drawActivity();
        $this->drawStatus();

        if ($this->codeOpen) {
            $this->drawCodeModal();
        } elseif ($this->contextOrigin !== null) {
            $this->drawContextMenu();
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($this->propertyEditing) {
            if ($event->what === EventType::KeyDown) {
                $this->handlePropertyEditorKey($event);
            } elseif ($event->what === EventType::MouseDown) {
                $this->commitPropertyEdit();
                $this->handleMouse($event);
            }

            return;
        }
        if ($this->previewOpen) {
            $this->handlePreviewEvent($event);

            return;
        }
        if ($this->codeOpen) {
            $this->handleCodeEvent($event);

            return;
        }
        if ($this->contextOrigin !== null) {
            $this->handleContextEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown) {
            $this->handleKey($event);
        } elseif ($event->what === EventType::MouseDown
            || $event->what === EventType::MouseMove
            || $event->what === EventType::MouseUp
        ) {
            $this->handleMouse($event);
        }
    }

    private function handleKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        if ($key->is(Key::AltX) || $key->keyCode === 0x11 || strtolower($key->char) === 'q') {
            $this->quit();
        } elseif ($key->is(Key::F5)) {
            $this->previewOpen = true;
            $this->setStatus('Preview running — F5 or Esc returns to Studio.');
        } elseif ($key->is(Key::F9)) {
            $this->openCode();
        } elseif ($key->is(Key::F2)) {
            $this->cycleTheme();
        } elseif ($key->keyCode === 0x13) {
            $this->saveProject();
        } elseif ($key->keyCode === 0x0F) {
            $this->loadProject();
        } elseif ($key->keyCode === 0x1A) {
            $this->undo();
        } elseif ($key->keyCode === 0x19) {
            $this->redo();
        } elseif (strtolower($key->char) === 'g') {
            $this->toggleGrid();
        } elseif (strtolower($key->char) === 's') {
            $this->toggleSnap();
        } elseif ($key->is(Key::Tab)) {
            $this->focus = $this->focus->next();
        } elseif ($key->is(Key::ShiftTab)) {
            $this->focus = $this->focus->next(-1);
        } else {
            match ($this->focus) {
                StudioFocus::Toolbox => $this->handleToolboxKey($key),
                StudioFocus::Canvas => $this->handleCanvasKey($key),
                StudioFocus::Inspector => $this->handleInspectorKey($key),
            };
        }
        $this->clearEvent($event);
    }

    private function handleToolboxKey(KeyDownEvent $key): void
    {
        $count = count(StudioComponentType::cases());
        if ($key->is(Key::Up)) {
            $this->toolIndex = ($this->toolIndex - 1 + $count) % $count;
        } elseif ($key->is(Key::Down)) {
            $this->toolIndex = ($this->toolIndex + 1) % $count;
        } elseif ($key->is(Key::Enter) || $key->char === ' ') {
            $this->addSelectedTool();
        }
    }

    private function handleCanvasKey(KeyDownEvent $key): void
    {
        $component = $this->selectedComponent();
        if ($component === null) {
            if ($key->is(Key::Enter)) {
                $this->focus = StudioFocus::Toolbox;
            }

            return;
        }

        if ($key->is(Key::Delete) || strtolower($key->char) === 'd') {
            $this->deleteSelected();

            return;
        }
        if (strtolower($key->char) === 'c') {
            $this->duplicateSelected();

            return;
        }
        if (strtolower($key->char) === 'h') {
            $this->alignSelected(StudioAlignment::HorizontalCenter);

            return;
        }
        if (strtolower($key->char) === 'v') {
            $this->alignSelected(StudioAlignment::VerticalCenter);

            return;
        }
        if ($key->is(Key::Enter)) {
            $this->focus = StudioFocus::Inspector;
            $this->propertyIndex = 0;

            return;
        }

        $dx = $key->is(Key::Left) ? -1 : ($key->is(Key::Right) ? 1 : 0);
        $dy = $key->is(Key::Up) ? -1 : ($key->is(Key::Down) ? 1 : 0);
        if ($dx !== 0 || $dy !== 0) {
            $step = $this->snapEnabled ? 2 : 1;
            $this->history->remember($this->project);
            $this->project->move($component->id, $component->x + $dx * $step, $component->y + $dy * $step);
            $this->markChanged('Moved ' . $this->componentName($component) . '.');

            return;
        }
        if ($key->char === '+' || $key->char === '=') {
            $step = $this->snapEnabled ? 2 : 1;
            $this->history->remember($this->project);
            $this->project->resize($component->id, $component->width + $step, $component->height + $step);
            $this->markChanged('Resized ' . $this->componentName($component) . '.');
        } elseif ($key->char === '-') {
            $step = $this->snapEnabled ? 2 : 1;
            $this->history->remember($this->project);
            $this->project->resize($component->id, $component->width - $step, $component->height - $step);
            $this->markChanged('Resized ' . $this->componentName($component) . '.');
        }
    }

    private function handleInspectorKey(KeyDownEvent $key): void
    {
        $properties = StudioProperty::cases();
        if ($this->selectedComponent() === null) {
            return;
        }
        if ($key->is(Key::Up)) {
            $this->propertyIndex = ($this->propertyIndex - 1 + count($properties)) % count($properties);
        } elseif ($key->is(Key::Down)) {
            $this->propertyIndex = ($this->propertyIndex + 1) % count($properties);
        } elseif ($key->is(Key::Enter)) {
            $this->beginPropertyEdit();
        } elseif ($key->is(Key::Left) || $key->is(Key::Right)) {
            $property = $properties[$this->propertyIndex];
            if ($property->numeric()) {
                $this->adjustNumericProperty($property, $key->is(Key::Left) ? -1 : 1);
            }
        }
    }

    private function handleMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $local = $this->makeLocal($mouse->where);

        if ($event->what === EventType::MouseMove) {
            if ($this->dragLast !== null) {
                $this->continueDrag($local);
            } else {
                $this->hoveredId = $this->componentAt($local)?->id;
            }
            $this->clearEvent($event);

            return;
        }
        if ($event->what === EventType::MouseUp) {
            if ($this->dragRemembered && $this->selectedComponent() !== null) {
                $this->markChanged(ucfirst((string) $this->dragMode) . 'd ' . $this->componentName($this->selectedComponent()) . '.');
            }
            $this->dragLast = null;
            $this->dragMode = null;
            $this->dragRemembered = false;
            $this->clearEvent($event);

            return;
        }
        if ($event->what !== EventType::MouseDown) {
            return;
        }

        if (($mouse->buttons & 4) !== 0) {
            $component = $this->componentAt($local);
            if ($component !== null) {
                $this->selectedId = $component->id;
                $this->focus = StudioFocus::Canvas;
                $this->contextOrigin = $local;
                $this->contextIndex = 0;
            }
            $this->clearEvent($event);

            return;
        }
        if (($mouse->buttons & 1) === 0) {
            return;
        }
        if ($local->y === 0) {
            $this->activateToolbarAt($local->x);
            $this->clearEvent($event);

            return;
        }

        [$leftSeparator, $rightSeparator, , $activityY] = $this->paneGeometry();
        if ($local->y > 1 && $local->y < $activityY && $local->x < $leftSeparator) {
            $this->handleLeftPaneClick($local, $mouse->doubleClick);
        } elseif ($local->y > 1 && $local->y < $activityY && $local->x > $rightSeparator) {
            $this->handleInspectorClick($local, $mouse->doubleClick);
        } elseif ($local->x > $leftSeparator && $local->x < $rightSeparator
            && $local->y > 1 && $local->y < $activityY
        ) {
            $this->handleCanvasClick($local);
        }
        $this->clearEvent($event);
    }

    private function handleLeftPaneClick(Point $local, bool $doubleClick): void
    {
        $toolIndex = $local->y - 3;
        $tools = StudioComponentType::cases();
        if ($toolIndex >= 0 && isset($tools[$toolIndex])) {
            $this->focus = StudioFocus::Toolbox;
            $this->toolIndex = $toolIndex;
            if ($doubleClick) {
                $this->addSelectedTool();
            }

            return;
        }

        $layerIndex = $local->y - ($this->layersSeparatorY() + 2);
        $layers = $this->visibleLayers();
        if ($layerIndex >= 0 && isset($layers[$layerIndex])) {
            $this->selectedId = $layers[$layerIndex]->id;
            $this->focus = StudioFocus::Canvas;
        }
    }

    private function handleCanvasClick(Point $local): void
    {
        $component = $this->componentAt($local);
        $this->focus = StudioFocus::Canvas;
        $this->selectedId = $component?->id;
        if ($component === null) {
            return;
        }

        [$originX, $originY] = $this->projectContentOrigin();
        $resizeX = $originX + $component->x + $component->width - 1;
        $resizeY = $originY + $component->y + $component->height - 1;
        $this->dragMode = $local->x === $resizeX && $local->y === $resizeY ? 'resize' : 'move';
        $this->dragLast = $local;
        $this->dragRemembered = false;
    }

    private function handleInspectorClick(Point $local, bool $doubleClick): void
    {
        $index = $local->y - 6;
        if ($index < 0 || ! isset(StudioProperty::cases()[$index]) || $this->selectedComponent() === null) {
            return;
        }
        $this->focus = StudioFocus::Inspector;
        $this->propertyIndex = $index;
        if ($doubleClick) {
            $this->beginPropertyEdit();
        }
    }

    private function continueDrag(Point $local): void
    {
        $last = $this->dragLast;
        $component = $this->selectedComponent();
        if ($last === null || $component === null) {
            return;
        }
        $dx = $local->x - $last->x;
        $dy = $local->y - $last->y;
        if ($dx === 0 && $dy === 0) {
            return;
        }
        if (! $this->dragRemembered) {
            $this->history->remember($this->project);
            $this->dragRemembered = true;
        }
        if ($this->dragMode === 'resize') {
            $width = $component->width + $dx;
            $height = $component->height + $dy;
            $this->project->resize(
                $component->id,
                $this->snapEnabled && $dx !== 0 ? $this->snapToGrid($width) : $width,
                $this->snapEnabled && $dy !== 0 ? $this->snapToGrid($height) : $height,
            );
        } else {
            $x = $component->x + $dx;
            $y = $component->y + $dy;
            $this->project->move(
                $component->id,
                $this->snapEnabled && $dx !== 0 ? $this->snapToGrid($x) : $x,
                $this->snapEnabled && $dy !== 0 ? $this->snapToGrid($y) : $y,
            );
        }
        $this->dragLast = $local;
    }

    private function beginPropertyEdit(): void
    {
        $component = $this->selectedComponent();
        if ($component === null) {
            return;
        }
        $property = StudioProperty::cases()[$this->propertyIndex];
        $this->propertyBuffer = $this->propertyValue($component, $property);
        $this->propertyCursor = mb_strlen($this->propertyBuffer);
        $this->propertyEditing = true;
        $this->setStatus('Editing ' . $property->value . ' — Enter applies, Esc cancels.');
    }

    private function handlePropertyEditorKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        if ($key->is(Key::Esc)) {
            $this->propertyEditing = false;
            $this->setStatus('Property edit cancelled.');
        } elseif ($key->is(Key::Enter)) {
            $this->commitPropertyEdit();
        } elseif ($key->is(Key::Left)) {
            $this->propertyCursor = max(0, $this->propertyCursor - 1);
        } elseif ($key->is(Key::Right)) {
            $this->propertyCursor = min(mb_strlen($this->propertyBuffer), $this->propertyCursor + 1);
        } elseif ($key->is(Key::Home)) {
            $this->propertyCursor = 0;
        } elseif ($key->is(Key::End)) {
            $this->propertyCursor = mb_strlen($this->propertyBuffer);
        } elseif ($key->is(Key::Backspace) && $this->propertyCursor > 0) {
            $this->propertyBuffer = mb_substr($this->propertyBuffer, 0, $this->propertyCursor - 1)
                . mb_substr($this->propertyBuffer, $this->propertyCursor);
            $this->propertyCursor--;
        } elseif ($key->is(Key::Delete) && $this->propertyCursor < mb_strlen($this->propertyBuffer)) {
            $this->propertyBuffer = mb_substr($this->propertyBuffer, 0, $this->propertyCursor)
                . mb_substr($this->propertyBuffer, $this->propertyCursor + 1);
        } elseif ($key->char !== '') {
            $property = StudioProperty::cases()[$this->propertyIndex];
            if (! $property->numeric() || ctype_digit($key->char)) {
                $this->propertyBuffer = mb_substr($this->propertyBuffer, 0, $this->propertyCursor)
                    . $key->char
                    . mb_substr($this->propertyBuffer, $this->propertyCursor);
                $this->propertyCursor += mb_strlen($key->char);
            }
        }
        $this->clearEvent($event);
    }

    private function commitPropertyEdit(): void
    {
        $component = $this->selectedComponent();
        if (! $this->propertyEditing || $component === null) {
            $this->propertyEditing = false;

            return;
        }

        $property = StudioProperty::cases()[$this->propertyIndex];
        $oldValue = $this->propertyValue($component, $property);
        if ($oldValue !== $this->propertyBuffer) {
            $this->history->remember($this->project);
            $this->applyProperty($component, $property, $this->propertyBuffer);
            $this->markChanged($property->value . ' updated on ' . $this->componentName($component) . '.');
        }
        $this->propertyEditing = false;
    }

    private function adjustNumericProperty(StudioProperty $property, int $delta): void
    {
        $component = $this->selectedComponent();
        if ($component === null || ! $property->numeric()) {
            return;
        }
        $this->history->remember($this->project);
        $this->applyProperty($component, $property, (string) ((int) $this->propertyValue($component, $property) + $delta));
        $this->markChanged($property->value . ' adjusted on ' . $this->componentName($component) . '.');
    }

    private function applyProperty(StudioComponent $component, StudioProperty $property, string $value): void
    {
        if ($property === StudioProperty::Text) {
            $this->project->setText($component->id, $value);

            return;
        }
        $number = max(0, (int) $value);
        if ($property === StudioProperty::X) {
            $this->project->move($component->id, $number, $component->y);
        } elseif ($property === StudioProperty::Y) {
            $this->project->move($component->id, $component->x, $number);
        } elseif ($property === StudioProperty::Width) {
            $this->project->resize($component->id, $number, $component->height);
        } else {
            $this->project->resize($component->id, $component->width, $number);
        }
    }

    private function propertyValue(StudioComponent $component, StudioProperty $property): string
    {
        return match ($property) {
            StudioProperty::Text => $component->text,
            StudioProperty::X => (string) $component->x,
            StudioProperty::Y => (string) $component->y,
            StudioProperty::Width => (string) $component->width,
            StudioProperty::Height => (string) $component->height,
        };
    }

    private function addSelectedTool(): void
    {
        if (! $this->project->canAdd()) {
            $this->setStatus('The project cannot accept another component.', true);

            return;
        }
        $type = StudioComponentType::cases()[$this->toolIndex];
        $this->history->remember($this->project);
        $component = $this->project->add($type);
        $this->selectedId = $component->id;
        $this->focus = StudioFocus::Canvas;
        $this->markChanged('Added ' . $this->componentName($component) . '.');
    }

    private function duplicateSelected(): void
    {
        $component = $this->selectedComponent();
        if ($component === null) {
            return;
        }
        if (! $this->project->canAdd()) {
            $this->setStatus('The project cannot accept another component.', true);

            return;
        }
        $this->history->remember($this->project);
        $copy = $this->project->duplicate($component->id);
        if ($copy !== null) {
            $this->selectedId = $copy->id;
            $this->markChanged('Duplicated ' . $this->componentName($component) . '.');
        }
    }

    private function deleteSelected(): void
    {
        $component = $this->selectedComponent();
        if ($component === null) {
            return;
        }
        $this->history->remember($this->project);
        $name = $this->componentName($component);
        $this->project->delete($component->id);
        $this->selectedId = null;
        $this->markChanged('Deleted ' . $name . '.');
    }

    private function undo(): void
    {
        $project = $this->history->undo($this->project);
        if ($project === null) {
            $this->setStatus('Nothing to undo.');

            return;
        }
        $this->project = $project;
        $this->repairSelection();
        $this->dirty = true;
        $this->log('Undo restored the previous design.');
        $this->setStatus('Undo.');
    }

    private function redo(): void
    {
        $project = $this->history->redo($this->project);
        if ($project === null) {
            $this->setStatus('Nothing to redo.');

            return;
        }
        $this->project = $project;
        $this->repairSelection();
        $this->dirty = true;
        $this->log('Redo restored the next design.');
        $this->setStatus('Redo.');
    }

    private function newProject(): void
    {
        $this->history->remember($this->project);
        $this->project = StudioProject::blank();
        $this->selectedId = null;
        $this->dirty = true;
        $this->log('Created a blank 52 × 18 interface.');
        $this->setStatus('New project — double-click a component to begin.');
    }

    private function saveProject(): void
    {
        try {
            $this->store->save($this->projectPath, $this->project);
            $this->dirty = false;
            $this->log('Saved ' . basename($this->projectPath) . '.');
            $this->setStatus('Project saved to ' . $this->projectPath . '.');
        } catch (RuntimeException $exception) {
            $this->setStatus($exception->getMessage(), true);
        }
    }

    private function loadProject(): void
    {
        if (! is_file($this->projectPath)) {
            $this->setStatus('No saved project exists at ' . $this->projectPath . '.', true);

            return;
        }
        try {
            $this->project = $this->store->load($this->projectPath);
            $this->history->clear();
            $this->selectedId = $this->project->components()[0]->id ?? null;
            $this->dirty = false;
            $this->log('Loaded ' . basename($this->projectPath) . '.');
            $this->setStatus('Project loaded from ' . $this->projectPath . '.');
        } catch (RuntimeException $exception) {
            $this->setStatus($exception->getMessage(), true);
        }
    }

    private function exportPhp(): void
    {
        try {
            $this->exporter->save($this->exportPath, $this->project);
            $this->log('Exported runnable PHP to ' . basename($this->exportPath) . '.');
            $this->setStatus('Generated app exported to ' . $this->exportPath . '.');
        } catch (RuntimeException $exception) {
            $this->setStatus($exception->getMessage(), true);
        }
    }

    private function cycleTheme(): void
    {
        $this->themeIndex = ($this->themeIndex + 1) % count($this->themes);
        $this->log('Theme switched to ' . $this->theme()->name . '.');
        $this->setStatus($this->theme()->name . ' theme applied to chrome, canvas, components, and overlays.');
    }

    private function toggleGrid(): void
    {
        $this->gridVisible = ! $this->gridVisible;
        $state = $this->gridVisible ? 'shown' : 'hidden';
        $this->log('Canvas grid ' . $state . '.');
        $this->setStatus('Canvas grid ' . $state . ' (G toggles).');
    }

    private function toggleSnap(): void
    {
        $this->snapEnabled = ! $this->snapEnabled;
        $state = $this->snapEnabled ? 'enabled' : 'disabled';
        $this->log('Two-cell snapping ' . $state . '.');
        $this->setStatus('Two-cell snapping ' . $state . ' (S toggles).');
    }

    private function alignSelected(StudioAlignment $alignment): void
    {
        $component = $this->selectedComponent();
        if ($component === null) {
            return;
        }
        $this->history->remember($this->project);
        $this->project->align($component->id, $alignment);
        $this->markChanged($alignment->value . ' for ' . $this->componentName($component) . '.');
    }

    private function snapToGrid(int $value): int
    {
        return (int) (round($value / 2) * 2);
    }

    private function openCode(): void
    {
        $this->codeOpen = true;
        $this->codeScroll = 0;
        $this->setStatus('Generated PHP — arrows scroll, E exports, Esc closes.');
    }

    private function handlePreviewEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key !== null && ($key->is(Key::Esc) || $key->is(Key::F5))) {
                $this->previewOpen = false;
                $this->setStatus('Returned to design mode.');
            }
            $this->clearEvent($event);
        } elseif ($event->what === EventType::MouseDown) {
            $this->previewOpen = false;
            $this->setStatus('Returned to design mode.');
            $this->clearEvent($event);
        }
    }

    private function handleCodeEvent(Event $event): void
    {
        if ($event->what !== EventType::KeyDown) {
            return;
        }
        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        $page = max(1, $this->bounds->height() - 12);
        $lineCount = count(explode("\n", $this->exporter->generate($this->project)));
        $maximum = max(0, $lineCount - $page);
        if ($key->is(Key::Esc) || $key->is(Key::F9)) {
            $this->codeOpen = false;
        } elseif ($key->is(Key::Up)) {
            $this->codeScroll = max(0, $this->codeScroll - 1);
        } elseif ($key->is(Key::Down)) {
            $this->codeScroll = min($maximum, $this->codeScroll + 1);
        } elseif ($key->is(Key::PageUp)) {
            $this->codeScroll = max(0, $this->codeScroll - $page);
        } elseif ($key->is(Key::PageDown)) {
            $this->codeScroll = min($maximum, $this->codeScroll + $page);
        } elseif (strtolower($key->char) === 'e') {
            $this->exportPhp();
        }
        $this->clearEvent($event);
    }

    private function handleContextEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            if ($key->is(Key::Esc)) {
                $this->closeContextMenu();
            } elseif ($key->is(Key::Up)) {
                $actionCount = count(self::CONTEXT_ACTIONS);
                $this->contextIndex = ($this->contextIndex - 1 + $actionCount) % $actionCount;
            } elseif ($key->is(Key::Down)) {
                $this->contextIndex = ($this->contextIndex + 1) % count(self::CONTEXT_ACTIONS);
            } elseif ($key->is(Key::Enter)) {
                $this->activateContextAction($this->contextIndex);
            }
            $this->clearEvent($event);

            return;
        }
        if ($event->what !== EventType::MouseDown && $event->what !== EventType::MouseMove) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $local = $this->makeLocal($mouse->where);
        $index = $this->contextActionAt($local);
        if ($event->what === EventType::MouseMove) {
            if ($index !== null) {
                $this->contextIndex = $index;
            }
        } elseif (($mouse->buttons & 1) !== 0 && $index !== null) {
            $this->activateContextAction($index);
        } else {
            $this->closeContextMenu();
        }
        $this->clearEvent($event);
    }

    private function activateContextAction(int $index): void
    {
        $component = $this->selectedComponent();
        if ($component === null) {
            $this->closeContextMenu();

            return;
        }
        if ($index === 0) {
            $this->duplicateSelected();
        } elseif ($index === 1) {
            $this->deleteSelected();
        } elseif ($index >= 2 && $index <= 5) {
            $this->alignSelected(StudioAlignment::cases()[$index - 2]);
        } else {
            $this->history->remember($this->project);
            if ($index === 6) {
                $this->project->bringForward($component->id);
                $this->markChanged('Brought ' . $this->componentName($component) . ' forward.');
            } else {
                $this->project->sendBackward($component->id);
                $this->markChanged('Sent ' . $this->componentName($component) . ' backward.');
            }
        }
        $this->closeContextMenu();
    }

    private function closeContextMenu(): void
    {
        $this->contextOrigin = null;
        $this->contextIndex = 0;
    }

    private function quit(): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->putEvent(Event::command(Cmd::Quit));
        }
    }

    private function markChanged(string $message): void
    {
        $this->dirty = true;
        $this->log($message);
        $this->setStatus($message);
    }

    private function repairSelection(): void
    {
        if ($this->selectedId !== null && $this->project->component($this->selectedId) === null) {
            $components = $this->project->components();
            $this->selectedId = $components === [] ? null : $components[array_key_last($components)]->id;
        }
    }

    private function componentName(StudioComponent $component): string
    {
        return $component->type->label() . ' #' . $component->id;
    }

    private function setStatus(string $message, bool $error = false): void
    {
        $this->status = $message;
        $this->statusIsError = $error;
    }

    private function log(string $message): void
    {
        $this->activity[] = $message;
        if (count($this->activity) > 20) {
            array_shift($this->activity);
        }
    }

    private function theme(): StudioTheme
    {
        return $this->themes[$this->themeIndex];
    }

    private function activateToolbarAt(int $x): void
    {
        if ($x >= 1 && $x <= 5) {
            $this->newProject();
        } elseif ($x >= 7 && $x <= 12) {
            $this->loadProject();
        } elseif ($x >= 14 && $x <= 19) {
            $this->saveProject();
        } elseif ($x >= 22 && $x <= 27) {
            $this->undo();
        } elseif ($x >= 29 && $x <= 34) {
            $this->redo();
        } elseif ($x >= 36 && $x <= 42) {
            $this->cycleTheme();
        } elseif ($x >= 44 && $x <= 49) {
            $this->openCode();
        } elseif ($x >= 51 && $x <= 57) {
            $this->toggleGrid();
        } elseif ($x >= 59 && $x <= 65) {
            $this->toggleSnap();
        } elseif ($x >= $this->bounds->width() - 16) {
            $this->previewOpen = true;
            $this->setStatus('Preview running — F5 or Esc returns to Studio.');
        }
    }

    /** @return array{int, int, int, int} */
    private function paneGeometry(): array
    {
        $width = $this->bounds->width();
        $leftSeparator = $width >= 110 ? 22 : 18;
        $rightPaneWidth = $width >= 110 ? 31 : 26;

        return [$leftSeparator, $width - $rightPaneWidth, 1, $this->bounds->height() - 4];
    }

    private function layersSeparatorY(): int
    {
        [, , , $activityY] = $this->paneGeometry();

        return min(count(StudioComponentType::cases()) + 5, $activityY - 7);
    }

    /** @return list<StudioComponent> */
    private function visibleLayers(): array
    {
        [, , , $activityY] = $this->paneGeometry();
        $maximum = max(0, $activityY - ($this->layersSeparatorY() + 2));

        return array_slice(array_reverse($this->project->components()), 0, $maximum);
    }

    /** @return array{int, int, int, int} */
    private function projectFrameGeometry(): array
    {
        [$leftSeparator, $rightSeparator, , $activityY] = $this->paneGeometry();
        $availableWidth = $rightSeparator - $leftSeparator - 1;
        $availableHeight = $activityY - 2;
        $width = $this->project->width() + 2;
        $height = $this->project->height() + 2;
        $x = $leftSeparator + 1 + max(0, intdiv($availableWidth - $width, 2));
        $y = 2 + max(0, intdiv($availableHeight - $height, 2));

        return [$x, $y, $width, $height];
    }

    /** @return array{int, int} */
    private function projectContentOrigin(): array
    {
        [$x, $y] = $this->projectFrameGeometry();

        return [$x + 1, $y + 1];
    }

    private function componentAt(Point $local): ?StudioComponent
    {
        [$originX, $originY] = $this->projectContentOrigin();
        $x = $local->x - $originX;
        $y = $local->y - $originY;
        if ($x < 0 || $y < 0 || $x >= $this->project->width() || $y >= $this->project->height()) {
            return null;
        }
        foreach (array_reverse($this->project->components()) as $component) {
            if ($x >= $component->x && $x < $component->x + $component->width
                && $y >= $component->y && $y < $component->y + $component->height
            ) {
                return $component;
            }
        }

        return null;
    }

    /** @return array{int, int, int, int} */
    private function contextMenuGeometry(): array
    {
        $origin = $this->contextOrigin ?? new Point(1, 1);
        $width = 27;
        $height = count(self::CONTEXT_ACTIONS) + 2;
        $x = max(1, min($origin->x + 1, $this->bounds->width() - $width - 1));
        $y = max(1, min($origin->y, $this->bounds->height() - $height - 2));

        return [$x, $y, $width, $height];
    }

    private function contextActionAt(Point $local): ?int
    {
        [$x, $y, $width] = $this->contextMenuGeometry();
        if ($local->x <= $x || $local->x >= $x + $width - 1) {
            return null;
        }
        $index = $local->y - $y - 1;

        return $index >= 0 && $index < count(self::CONTEXT_ACTIONS) ? $index : null;
    }

    private function drawToolbar(): void
    {
        $width = $this->bounds->width();
        $theme = $this->theme();
        $this->fillRect(0, 0, $width, 1, ' ', $theme->canvas);
        $this->writeClipped(1, 0, '[New]', 5, $theme->primary);
        $this->writeClipped(7, 0, '[Open]', 6, $theme->canvas);
        $this->writeClipped(14, 0, '[Save]', 6, $theme->accent);
        $this->writeClipped(22, 0, '[Undo]', 6, $this->history->undoCount() > 0 ? $theme->canvas : $theme->muted);
        $this->writeClipped(29, 0, '[Redo]', 6, $this->history->redoCount() > 0 ? $theme->canvas : $theme->muted);
        $this->writeClipped(36, 0, '[Theme]', 7, $theme->secondary);
        $this->writeClipped(44, 0, '[Code]', 6, $theme->canvas);
        $this->writeClipped(
            51,
            0,
            $this->gridVisible ? '[Grid+]' : '[Grid-]',
            7,
            $this->gridVisible ? $theme->success : $theme->muted,
        );
        $this->writeClipped(
            59,
            0,
            $this->snapEnabled ? '[Snap+]' : '[Snap-]',
            7,
            $this->snapEnabled ? $theme->success : $theme->muted,
        );

        $projectLabel = 'Turbo Studio · ' . basename($this->projectPath);
        $this->writeClipped(68, 0, $projectLabel, max(0, $width - 87), $theme->muted);
        $this->writeClipped($width - 16, 0, '[▶ Preview F5]', 15, $theme->accent);
    }

    private function drawStructure(): void
    {
        [$leftSeparator, $rightSeparator, $topY, $activityY] = $this->paneGeometry();
        $width = $this->bounds->width();
        $theme = $this->theme();

        $this->fillRect(1, $topY, $width - 2, 1, '─', $theme->grid);
        $this->writeClipped(0, $topY, '┌', 1, $theme->grid);
        $this->writeClipped($leftSeparator, $topY, '┬', 1, $theme->grid);
        $this->writeClipped($rightSeparator, $topY, '┬', 1, $theme->grid);
        $this->writeClipped($width - 1, $topY, '┐', 1, $theme->grid);

        for ($y = $topY + 1; $y < $activityY; $y++) {
            $this->writeClipped(0, $y, '│', 1, $theme->grid);
            $this->writeClipped($leftSeparator, $y, '│', 1, $theme->grid);
            $this->writeClipped($rightSeparator, $y, '│', 1, $theme->grid);
            $this->writeClipped($width - 1, $y, '│', 1, $theme->grid);
        }

        $layersY = $this->layersSeparatorY();
        $this->fillRect(1, $layersY, $leftSeparator - 1, 1, '─', $theme->grid);
        $this->writeClipped(0, $layersY, '├', 1, $theme->grid);
        $this->writeClipped($leftSeparator, $layersY, '┤', 1, $theme->grid);

        $this->fillRect(1, $activityY, $width - 2, 1, '─', $theme->grid);
        $this->writeClipped(0, $activityY, '├', 1, $theme->grid);
        $this->writeClipped($leftSeparator, $activityY, '┼', 1, $theme->grid);
        $this->writeClipped($rightSeparator, $activityY, '┼', 1, $theme->grid);
        $this->writeClipped($width - 1, $activityY, '┤', 1, $theme->grid);

        $this->drawSectionHeading(0, $leftSeparator, $topY, 'Components', $this->focus === StudioFocus::Toolbox);
        $this->drawSectionHeading(
            $leftSeparator,
            $rightSeparator,
            $topY,
            'Design · ' . $this->project->width() . '×' . $this->project->height(),
            $this->focus === StudioFocus::Canvas,
        );
        $this->drawSectionHeading($rightSeparator, $width - 1, $topY, 'Inspector', $this->focus === StudioFocus::Inspector);
        $this->writeClipped(2, $layersY, ' Layers ', max(0, $leftSeparator - 4), $theme->primary);
        $this->writeClipped(2, $activityY, ' Activity ', max(0, $width - 4), $theme->primary);
    }

    private function drawSectionHeading(int $start, int $end, int $y, string $heading, bool $focused): void
    {
        $attr = $focused ? $this->theme()->accent : $this->theme()->primary;
        $marker = $focused ? $this->theme()->focusGlyph . ' ' : '  ';
        $this->writeClipped($start + 2, $y, $marker . $heading . ' ', max(0, $end - $start - 3), $attr);
    }

    private function drawToolbox(): void
    {
        [$leftSeparator] = $this->paneGeometry();
        $tools = StudioComponentType::cases();
        foreach ($tools as $index => $tool) {
            $selected = $index === $this->toolIndex;
            $attr = $selected && $this->focus === StudioFocus::Toolbox
                ? $this->theme()->accent
                : ($selected ? $this->theme()->primary : $this->theme()->canvas);
            $marker = $selected ? '›' : ' ';
            $this->writeClipped(2, 3 + $index, $marker . ' ' . $tool->icon() . '  ' . $tool->label(), max(0, $leftSeparator - 3), $attr);
        }
        $this->writeClipped(2, 3 + count($tools), 'Enter adds / double', max(0, $leftSeparator - 3), $this->theme()->muted);

        foreach ($this->visibleLayers() as $index => $component) {
            $selected = $component->id === $this->selectedId;
            $attr = $selected ? $this->theme()->accent : $this->theme()->canvas;
            $label = ($selected ? '› ' : '  ')
                . $component->type->icon()
                . ' #'
                . $component->id
                . ' '
                . ($component->text !== '' ? $component->text : $component->type->label());
            $this->writeClipped(1, $this->layersSeparatorY() + 2 + $index, $label, max(0, $leftSeparator - 2), $attr);
        }
    }

    private function drawCanvas(): void
    {
        [$frameX, $frameY, $frameWidth, $frameHeight] = $this->projectFrameGeometry();
        $theme = $this->theme();
        $this->fillRect($frameX + 1, $frameY + 1, $frameWidth, $frameHeight, $theme->shadowGlyph, $theme->shadow);
        $this->fillRect($frameX, $frameY, $frameWidth, $frameHeight, ' ', $theme->canvas);
        $this->drawBox($frameX, $frameY, $frameWidth, $frameHeight, $theme->grid);
        $this->writeClipped(
            $frameX + 2,
            $frameY,
            ' ' . $this->project->name() . ' ',
            max(0, $frameWidth - 4),
            $this->focus === StudioFocus::Canvas ? $theme->accent : $theme->primary,
        );

        [$originX, $originY] = $this->projectContentOrigin();
        if ($this->gridVisible) {
            for ($y = 0; $y < $this->project->height(); $y += 2) {
                for ($x = ($y % 4 === 0 ? 1 : 3); $x < $this->project->width(); $x += 4) {
                    $this->writeClipped($originX + $x, $originY + $y, $theme->gridGlyph, 1, $theme->grid);
                }
            }
        }
        $this->drawProjectComponents($originX, $originY, true);
    }

    private function drawProjectComponents(int $originX, int $originY, bool $designMode): void
    {
        foreach ($this->project->components() as $component) {
            $selected = $designMode && $component->id === $this->selectedId;
            $hovered = $designMode && $component->id === $this->hoveredId;
            $this->drawProjectComponent($originX, $originY, $component, $selected, $hovered);
        }
    }

    private function drawProjectComponent(
        int $originX,
        int $originY,
        StudioComponent $component,
        bool $selected,
        bool $hovered,
    ): void {
        $x = $originX + $component->x;
        $y = $originY + $component->y;
        $width = $component->width;
        $height = $component->height;
        $theme = $this->theme();
        $attr = $selected ? $theme->accent : ($hovered ? $theme->secondary : $theme->canvas);

        switch ($component->type) {
            case StudioComponentType::Panel:
                if ($width > 2 && $height > 2) {
                    $this->fillRect($x + 1, $y + 1, $width - 2, $height - 2, ' ', $theme->canvas);
                }
                $this->drawBox($x, $y, $width, $height, $selected || $hovered ? $attr : $theme->grid);
                $this->writeClipped($x + 2, $y, ' ' . $component->text . ' ', max(0, $width - 4), $selected ? $attr : $theme->primary);
                break;
            case StudioComponentType::Label:
                $this->writeClipped($x, $y, $component->text, $width, $selected ? $attr : $theme->primary);
                break;
            case StudioComponentType::Button:
                $label = '[ ' . $component->text . ' ]';
                $padding = max(0, $width - mb_strlen($label));
                $label = str_repeat(' ', intdiv($padding, 2)) . $label . str_repeat(' ', $padding - intdiv($padding, 2));
                $this->writeClipped($x, $y, $label, $width, $selected ? $attr : $theme->accent);
                break;
            case StudioComponentType::Input:
                $label = '› ' . $component->text;
                if (mb_strlen($label) < $width) {
                    $label .= str_repeat($theme->gridGlyph, $width - mb_strlen($label));
                }
                $this->writeClipped($x, $y, $label, $width, $selected ? $attr : $theme->canvas);
                break;
            case StudioComponentType::ListBox:
                $this->drawBox($x, $y, $width, $height, $selected || $hovered ? $attr : $theme->grid);
                foreach (explode('|', $component->text) as $index => $item) {
                    if ($index >= $height - 2) {
                        break;
                    }
                    $itemAttr = $index === 0 ? $theme->accent : $theme->canvas;
                    $this->writeClipped(
                        $x + 2,
                        $y + 1 + $index,
                        ($index === 0 ? '› ' : '  ') . $item,
                        max(0, $width - 3),
                        $selected ? $attr : $itemAttr,
                    );
                }
                break;
            case StudioComponentType::Checkbox:
                $this->writeClipped($x, $y, '[x] ' . $component->text, $width, $selected ? $attr : $theme->success);
                break;
            case StudioComponentType::Separator:
                $this->fillRect($x, $y, $width, 1, '─', $selected ? $attr : $theme->grid);
                break;
            case StudioComponentType::Radio:
                $this->writeClipped($x, $y, '(o) ' . $component->text, $width, $selected ? $attr : $theme->secondary);
                break;
            case StudioComponentType::Progress:
                $percentage = max(0, min(100, (int) $component->text));
                $barWidth = max(1, $width - 7);
                $complete = (int) round($barWidth * $percentage / 100);
                $label = '['
                    . str_repeat('=', $complete)
                    . str_repeat('-', $barWidth - $complete)
                    . '] '
                    . str_pad((string) $percentage, 3, ' ', STR_PAD_LEFT)
                    . '%';
                $this->writeClipped($x, $y, $label, $width, $selected ? $attr : $theme->warning);
                break;
            case StudioComponentType::TextArea:
                $this->drawBox($x, $y, $width, $height, $selected || $hovered ? $attr : $theme->grid);
                foreach (explode('|', $component->text) as $index => $line) {
                    if ($index >= $height - 2) {
                        break;
                    }
                    $this->writeClipped($x + 2, $y + 1 + $index, $line, max(0, $width - 4), $selected ? $attr : $theme->canvas);
                }
                break;
        }

        if ($selected) {
            $this->writeClipped($x, $y, $theme->focusGlyph, 1, $theme->accent);
            $this->writeClipped($x + $width - 1, $y + $height - 1, $theme->focusGlyph, 1, $theme->accent);
        }
    }

    private function drawInspector(): void
    {
        [$leftSeparator, $rightSeparator] = $this->paneGeometry();
        $width = $this->bounds->width();
        $x = $rightSeparator + 2;
        $available = max(0, $width - $x - 2);
        $component = $this->selectedComponent();
        if ($component === null) {
            $this->writeClipped($x, 3, 'Nothing selected', $available, $this->theme()->muted);
            $this->writeClipped($x, 5, 'Choose a layer or click', $available, $this->theme()->canvas);
            $this->writeClipped($x, 6, 'a component on the canvas.', $available, $this->theme()->canvas);

            return;
        }

        $this->writeClipped($x, 3, $component->type->icon() . '  ' . $component->type->label() . ' #' . $component->id, $available, $this->theme()->primary);
        $this->writeClipped($x, 4, $component->x . ',' . $component->y . '  ·  ' . $component->width . '×' . $component->height, $available, $this->theme()->muted);

        foreach (StudioProperty::cases() as $index => $property) {
            $row = 6 + $index;
            $selected = $index === $this->propertyIndex;
            $attr = $selected && $this->focus === StudioFocus::Inspector
                ? $this->theme()->accent
                : ($selected ? $this->theme()->primary : $this->theme()->canvas);
            $marker = $selected ? '›' : ' ';
            $value = $selected && $this->propertyEditing
                ? $this->propertyBuffer
                : $this->propertyValue($component, $property);
            $this->writeClipped($x, $row, $marker . ' ' . str_pad($property->value, 7), min(10, $available), $attr);
            $valueX = $x + 10;
            $valueWidth = max(0, $available - 10);
            $this->writeClipped($valueX, $row, $value, $valueWidth, $attr);
            if ($selected && $this->propertyEditing && $valueWidth > 0) {
                $cursor = min($this->propertyCursor, $valueWidth - 1);
                $cursorChar = mb_substr($this->propertyBuffer, $this->propertyCursor, 1);
                $this->writeClipped(
                    $valueX + $cursor,
                    $row,
                    $cursorChar !== '' ? $cursorChar : '▏',
                    1,
                    $this->theme()->accent,
                );
            }
        }

        $this->writeClipped($x, 13, 'Enter/double-click edits', $available, $this->theme()->muted);
        $this->writeClipped($x, 14, '← → nudges numbers', $available, $this->theme()->muted);
        $this->writeClipped($x, 16, 'Direct manipulation', $available, $this->theme()->primary);
        $this->writeClipped($x, 17, 'Drag body to move', $available, $this->theme()->canvas);
        $this->writeClipped($x, 18, 'Drag ' . $this->theme()->focusGlyph . ' handle to resize', $available, $this->theme()->canvas);
        $this->writeClipped($x, 19, 'Right-click for actions', $available, $this->theme()->canvas);
    }

    private function drawActivity(): void
    {
        [, , , $activityY] = $this->paneGeometry();
        $width = $this->bounds->width();
        $visible = array_slice($this->activity, -2);
        foreach ($visible as $index => $message) {
            $marker = $index === count($visible) - 1 ? '›' : $this->theme()->gridGlyph;
            $attr = $index === count($visible) - 1 ? $this->theme()->accent : $this->theme()->muted;
            $this->writeClipped(2, $activityY + 1 + $index, $marker . ' ' . $message, max(0, $width - 4), $attr);
        }
    }

    private function drawStatus(): void
    {
        $width = $this->bounds->width();
        $y = $this->bounds->height() - 1;
        $attr = $this->statusIsError ? $this->theme()->error : $this->theme()->canvas;
        $this->fillRect(0, $y, $width, 1, ' ', $attr);
        $this->writeClipped(1, $y, $this->status, max(0, $width - 32), $attr);
        $right = ($this->dirty ? '● Unsaved' : '✓ Saved')
            . '  ·  '
            . $this->theme()->name
            . '  ';
        $this->writeClipped($width - mb_strlen($right), $y, $right, mb_strlen($right), $this->dirty ? $this->theme()->warning : $this->theme()->success);
    }

    private function drawPreview(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $frameWidth = $this->project->width() + 2;
        $frameHeight = $this->project->height() + 2;
        $frameX = max(1, intdiv($width - $frameWidth, 2));
        $frameY = max(1, intdiv($height - $frameHeight, 2));
        $this->fillRect($frameX + 2, $frameY + 1, $frameWidth, $frameHeight, $this->theme()->shadowGlyph, $this->theme()->shadow);
        $this->fillRect($frameX, $frameY, $frameWidth, $frameHeight, ' ', $this->theme()->canvas);
        $this->drawBox($frameX, $frameY, $frameWidth, $frameHeight, $this->theme()->grid);
        $this->writeClipped($frameX + 2, $frameY, ' ' . $this->project->name() . ' · Preview ', max(0, $frameWidth - 4), $this->theme()->accent);
        $this->drawProjectComponents($frameX + 1, $frameY + 1, false);
        $help = ' PREVIEW  •  F5 or Esc returns to Studio  •  click anywhere to close ';
        $this->writeClipped(max(0, intdiv($width - mb_strlen($help), 2)), $height - 1, $help, min($width, mb_strlen($help)), $this->theme()->muted);
    }

    private function drawCodeModal(): void
    {
        $width = min(96, $this->bounds->width() - 8);
        $height = min(26, $this->bounds->height() - 6);
        $x = intdiv($this->bounds->width() - $width, 2);
        $y = intdiv($this->bounds->height() - $height, 2);
        $this->fillRect($x + 2, $y + 1, $width, $height, $this->theme()->shadowGlyph, $this->theme()->shadow);
        $this->fillRect($x, $y, $width, $height, ' ', $this->theme()->canvas);
        $this->drawBox($x, $y, $width, $height, $this->theme()->grid);
        $this->writeClipped($x + 3, $y, ' Generated PHP · F9 ', max(0, $width - 6), $this->theme()->accent);

        $lines = explode("\n", $this->exporter->generate($this->project));
        $contentHeight = $height - 4;
        foreach (array_slice($lines, $this->codeScroll, $contentHeight) as $index => $line) {
            $lineNumber = $this->codeScroll + $index + 1;
            $this->writeClipped($x + 2, $y + 2 + $index, str_pad((string) $lineNumber, 4, ' ', STR_PAD_LEFT), 4, $this->theme()->muted);
            $attr = str_starts_with(trim($line), '//') || str_starts_with(trim($line), '*')
                ? $this->theme()->muted
                : (str_contains($line, 'class ') || str_contains($line, 'function ') ? $this->theme()->secondary : $this->theme()->canvas);
            $this->writeClipped($x + 7, $y + 2 + $index, $line, max(0, $width - 9), $attr);
        }

        $help = '↑↓/PgUp/PgDn scroll   E export to ' . basename($this->exportPath) . '   Esc close';
        $this->writeClipped($x + 3, $y + $height - 2, $help, max(0, $width - 6), $this->theme()->muted);
    }

    private function drawContextMenu(): void
    {
        [$x, $y, $width, $height] = $this->contextMenuGeometry();
        $actions = self::CONTEXT_ACTIONS;
        $this->fillRect($x + 1, $y + 1, $width, $height, $this->theme()->shadowGlyph, $this->theme()->shadow);
        $this->fillRect($x, $y, $width, $height, ' ', $this->theme()->canvas);
        $this->drawBox($x, $y, $width, $height, $this->theme()->grid);
        $this->writeClipped($x + 2, $y, ' Component ', max(0, $width - 4), $this->theme()->primary);
        foreach ($actions as $index => $action) {
            $selected = $index === $this->contextIndex;
            $this->writeClipped(
                $x + 2,
                $y + 1 + $index,
                ($selected ? '› ' : '  ') . $action,
                max(0, $width - 4),
                $selected ? $this->theme()->accent : ($action === 'Delete' ? $this->theme()->error : $this->theme()->canvas),
            );
        }
    }

    private function drawCompactWarning(int $width, int $height): void
    {
        $message = 'Turbo Studio needs at least 100 × 26';
        $this->writeClipped(max(0, intdiv($width - mb_strlen($message), 2)), max(0, intdiv($height, 2)), $message, $width, $this->theme()->error);
    }

    private function drawBox(int $x, int $y, int $width, int $height, int $attr): void
    {
        if ($width < 2 || $height < 2) {
            return;
        }
        $this->fillRect($x + 1, $y, $width - 2, 1, '─', $attr);
        $this->fillRect($x + 1, $y + $height - 1, $width - 2, 1, '─', $attr);
        for ($row = $y + 1; $row < $y + $height - 1; $row++) {
            $this->writeClipped($x, $row, '│', 1, $attr);
            $this->writeClipped($x + $width - 1, $row, '│', 1, $attr);
        }
        $this->writeClipped($x, $y, '┌', 1, $attr);
        $this->writeClipped($x + $width - 1, $y, '┐', 1, $attr);
        $this->writeClipped($x, $y + $height - 1, '└', 1, $attr);
        $this->writeClipped($x + $width - 1, $y + $height - 1, '┘', 1, $attr);
    }

    private function fillRect(int $x, int $y, int $width, int $height, string $char, int $attr): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, $char, $attr, $width);
        for ($row = 0; $row < $height; $row++) {
            $this->writeLine($x, $y + $row, $width, 1, $buffer);
        }
    }

    private function writeClipped(int $x, int $y, string $text, int $width, int $attr): void
    {
        if ($width <= 0 || $y < 0 || $y >= $this->bounds->height()) {
            return;
        }
        $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
        $this->writeStr($x, $y, mb_substr($text, 0, $width), $attr);
    }
}
