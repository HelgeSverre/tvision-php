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

/** Non-blocking modal surface so the normal Program loop remains testable. */
final class WorkbenchDialog extends View
{
    private int $selected = 0;

    /** @param list<array{label:string,command:int}> $actions */
    public function __construct(
        Rect $bounds,
        private readonly string $title,
        private readonly string $message,
        private readonly array $actions,
    ) {
        parent::__construct($bounds);
        $this->options |= State::PreProcess | State::FirstClick;
        $this->state |= State::Modal;
        $this->growMode = State::GrowHiX | State::GrowHiY;
    }

    public function draw(): void
    {
        [$x, $y, $width, $height] = $this->geometry();
        $normal = 0x07;
        $bright = 0x0F;
        $accent = 0x09;
        $selected = 0x1F;
        $this->fill($x, $y, $width, $height, ' ', 0x08);
        $this->box($x, $y, $width, $height, $accent);
        $this->write($x + 3, $y + 1, $this->title, $width - 6, $bright);

        $lines = wordwrap($this->message, max(10, $width - 6), "\n", true);
        foreach (explode("\n", $lines) as $index => $line) {
            if ($index >= $height - 6) {
                break;
            }
            $this->write($x + 3, $y + 3 + $index, $line, $width - 6, $normal);
        }

        $buttonY = $y + $height - 2;
        $buttonX = $x + 3;
        foreach ($this->actions as $index => $action) {
            $label = '[ ' . $action['label'] . ' ]';
            $this->write($buttonX, $buttonY, $label, mb_strlen($label), $index === $this->selected ? $selected : $normal);
            $buttonX += mb_strlen($label) + 3;
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
                $this->selected = ($this->selected - 1 + count($this->actions)) % count($this->actions);
            } elseif ($key->is(Key::Right) || $key->is(Key::Tab)) {
                $this->selected = ($this->selected + 1) % count($this->actions);
            } elseif ($key->is(Key::Enter)) {
                $this->activate($this->selected);
            } elseif ($key->is(Key::Esc)) {
                $this->activate(count($this->actions) - 1);
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
        [$x, $y, , $height] = $this->geometry();
        if ($local->y === $y + $height - 2) {
            $buttonX = $x + 3;
            foreach ($this->actions as $index => $action) {
                $length = mb_strlen('[ ' . $action['label'] . ' ]');
                if ($local->x >= $buttonX && $local->x < $buttonX + $length) {
                    $this->selected = $index;
                    $this->activate($index);
                    break;
                }
                $buttonX += $length + 3;
            }
        }
        $this->clearEvent($event);
    }

    /** @return array{int,int,int,int} */
    private function geometry(): array
    {
        $width = min(64, max(30, $this->bounds->width() - 8));
        $height = min(13, max(8, $this->bounds->height() - 6));

        return [intdiv($this->bounds->width() - $width, 2), intdiv($this->bounds->height() - $height, 2), $width, $height];
    }

    private function activate(int $index): void
    {
        $command = $this->actions[$index]['command'] ?? WorkbenchCommand::CancelDialog;
        if ($this->owner instanceof Group) {
            $this->owner->putEvent(Event::command($command));
        }
    }

    private function box(int $x, int $y, int $width, int $height, int $attr): void
    {
        $this->write($x, $y, '┌' . str_repeat('─', $width - 2) . '┐', $width, $attr);
        $this->write($x, $y + $height - 1, '└' . str_repeat('─', $width - 2) . '┘', $width, $attr);
        for ($row = 1; $row < $height - 1; $row++) {
            $this->write($x, $y + $row, '│', 1, $attr);
            $this->write($x + $width - 1, $y + $row, '│', 1, $attr);
        }
    }

    private function fill(int $x, int $y, int $width, int $height, string $char, int $attr): void
    {
        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, $char, $attr, $width);
        for ($row = 0; $row < $height; $row++) {
            $this->writeLine($x, $y + $row, $width, 1, $buffer);
        }
    }

    private function write(int $x, int $y, string $text, int $width, int $attr): void
    {
        $this->writeStr($x, $y, mb_substr($text, 0, max(0, $width)), $attr);
    }
}
