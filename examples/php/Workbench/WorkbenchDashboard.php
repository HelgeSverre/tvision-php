<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Workbench;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** Interactive landing view used by the default framework showcase. */
final class WorkbenchDashboard extends View
{
    /** @var list<array{label:string,command:int}> */
    private const array ACTIONS = [
        ['label' => 'New workspace', 'command' => WorkbenchCommand::NewWorkspace],
        ['label' => 'Task board', 'command' => WorkbenchCommand::OpenTasks],
        ['label' => 'Activity log', 'command' => WorkbenchCommand::OpenActivity],
        ['label' => 'About', 'command' => WorkbenchCommand::About],
    ];

    private int $selectedAction = 0;

    private int $pipelineProgress = 68;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick;
        $this->growMode = State::GrowHiX | State::GrowHiY;
    }

    public function selectedAction(): int
    {
        return $this->selectedAction;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $normal = $this->mapColor(1);
        $muted = $this->mapColor(2);
        $accent = $this->mapColor(3);
        $selected = $this->mapColor(4);

        $this->fill(0, 0, $width, $height, ' ', $normal);
        $this->write(2, 1, 'TURBO WORKBENCH', max(0, $width - 4), $accent);
        $this->write(2, 2, 'A living tour of TurboVision for PHP', max(0, $width - 4), $muted);
        $this->rule(2, 4, max(0, $width - 4), $muted);

        $cardWidth = max(12, intdiv(max(1, $width - 8), 3));
        $this->card(2, 6, $cardWidth, 'WINDOWS', '04', 'move · resize · zoom', $normal, $accent);
        $this->card(3 + $cardWidth, 6, $cardWidth, 'EVENTS', '128', 'keyboard + mouse', $normal, $accent);
        $this->card(4 + $cardWidth * 2, 6, max(10, $width - (4 + $cardWidth * 2) - 2), 'TESTS', '480+', 'headless assertions', $normal, $accent);

        $this->write(2, 11, 'Build pipeline', 20, $normal);
        $barWidth = max(8, $width - 22);
        $filled = intdiv($barWidth * $this->pipelineProgress, 100);
        $this->write(18, 11, str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled), $barWidth, $accent);
        $this->write(max(2, $width - 6), 11, $this->pipelineProgress . '%', 4, $normal);

        $this->write(2, 13, 'Use ←/→ to choose, Enter to launch, +/- to animate progress.', max(0, $width - 4), $muted);
        $buttonY = max(15, $height - 3);
        $x = 2;
        foreach (self::ACTIONS as $index => $action) {
            $label = '[ ' . $action['label'] . ' ]';
            $attr = $index === $this->selectedAction ? $selected : $normal;
            $this->write($x, $buttonY, $label, mb_strlen($label), $attr);
            $x += mb_strlen($label) + 2;
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            if ($key->is(Key::Left) || $key->is(Key::ShiftTab)) {
                $this->selectedAction = ($this->selectedAction - 1 + count(self::ACTIONS)) % count(self::ACTIONS);
            } elseif ($key->is(Key::Right) || $key->is(Key::Tab)) {
                $this->selectedAction = ($this->selectedAction + 1) % count(self::ACTIONS);
            } elseif ($key->is(Key::Enter) || $key->char === ' ') {
                $this->activate($this->selectedAction);
            } elseif ($key->char === '+' || $key->char === '=') {
                $this->pipelineProgress = min(100, $this->pipelineProgress + 4);
            } elseif ($key->char === '-') {
                $this->pipelineProgress = max(0, $this->pipelineProgress - 4);
            } else {
                return;
            }
            $this->clearEvent($event);

            return;
        }

        if ($event->what !== EventType::MouseDown) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null || ($mouse->buttons & 1) === 0) {
            return;
        }
        $local = $this->makeLocal($mouse->where);
        $buttonY = max(15, $this->bounds->height() - 3);
        if ($local->y !== $buttonY) {
            return;
        }
        $x = 2;
        foreach (self::ACTIONS as $index => $action) {
            $length = mb_strlen('[ ' . $action['label'] . ' ]');
            if ($local->x >= $x && $local->x < $x + $length) {
                $this->selectedAction = $index;
                $this->activate($index);
                $this->clearEvent($event);

                return;
            }
            $x += $length + 2;
        }
    }

    private function activate(int $index): void
    {
        $command = self::ACTIONS[$index]['command'];
        for ($owner = $this->owner; $owner !== null; $owner = $owner->owner) {
            if ($owner instanceof Group) {
                $owner->putEvent(Event::command($command));

                return;
            }
        }
    }

    private function card(int $x, int $y, int $width, string $title, string $value, string $caption, int $normal, int $accent): void
    {
        if ($width < 8) {
            return;
        }
        $this->write($x, $y, '┌' . str_repeat('─', $width - 2) . '┐', $width, $normal);
        $this->write($x, $y + 1, '│ ' . $this->pad($title, $width - 3) . '│', $width, $normal);
        $this->write($x, $y + 2, '│ ' . $this->pad($value, $width - 3) . '│', $width, $accent);
        $this->write($x, $y + 3, '│ ' . $this->pad($caption, $width - 3) . '│', $width, $normal);
        $this->write($x, $y + 4, '└' . str_repeat('─', $width - 2) . '┘', $width, $normal);
    }

    private function fill(int $x, int $y, int $width, int $height, string $char, int $attr): void
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

    private function rule(int $x, int $y, int $width, int $attr): void
    {
        $this->write($x, $y, str_repeat('─', $width), $width, $attr);
    }

    private function write(int $x, int $y, string $text, int $width, int $attr): void
    {
        if ($width <= 0 || $y < 0 || $y >= $this->bounds->height()) {
            return;
        }
        $this->writeStr($x, $y, mb_substr($text, 0, $width), $attr);
    }

    private function pad(string $text, int $width): string
    {
        $text = mb_substr($text, 0, max(0, $width));

        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }
}
