<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\KitchenSink;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** High-fidelity landing dashboard and small keyboard/mouse interaction showcase. */
final class KitchenSinkDashboard extends View
{
    private int $pulse = 62;

    private int $interactions = 0;

    private bool $live = true;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick;
        $this->eventMask |= EventMask::Mouse;
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->helpCtx = KitchenSinkApp::HelpOverview;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $normal = $this->getColor(1);
        $muted = $this->getColor(2);
        $accent = $this->getColor(3);
        $strong = $this->getColor(4);

        for ($y = 0; $y < $height; $y++) {
            $row = new DrawBuffer($width, $normal);
            $row->moveChar(0, ' ', $normal, $width);

            if ($y === 1) {
                $row->moveStr(2, TerminalText::slice('TURBOVISION // KITCHEN SINK', 0, max(0, $width - 4)), $strong);
            } elseif ($y === 2) {
                $row->moveStr(2, TerminalText::slice('One library. Every subsystem. Fully interactive.', 0, max(0, $width - 4)), $muted);
            } elseif ($y === 4) {
                $this->card($row, 2, '01', 'INPUT', 'mouse + keys', $accent, $muted);
            } elseif ($y === 5) {
                $this->card($row, 2, '02', 'DATA', 'files + resources', $accent, $muted);
            } elseif ($y === 6) {
                $this->card($row, 2, '03', 'VIEWS', 'edit + scroll', $accent, $muted);
            } elseif ($y === 8) {
                $barWidth = max(1, min(28, $width - 20));
                $filled = intdiv($barWidth * $this->pulse, 100);
                $row->moveStr(2, 'SYSTEM COVERAGE', $muted);
                $row->moveChar(18, '─', $muted, $barWidth);
                $row->moveChar(18, '━', $accent, $filled);
                $row->moveStr(19 + $barWidth, sprintf('%3d%%', $this->pulse), $strong);
            } elseif ($y === 10) {
                $row->moveStr(2, 'LIVE', $this->live ? $accent : $muted);
                $row->moveStr(8, sprintf('events %04d', $this->interactions), $normal);
                $row->moveStr(24, '←/→ tune   Space pause', $muted);
            } elseif ($y === $height - 2) {
                $row->moveStr(2, TerminalText::slice('Choose a lab at right · F10 menu · F1 contextual help', 0, max(0, $width - 4)), $muted);
            }

            $this->writeLine(0, $y, $width, 1, $row);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse !== null) {
                $this->interactions++;
                if (($mouse->buttons & 0x02) !== 0) {
                    if ($this->owner instanceof Group) {
                        $this->owner->putEvent(Event::command(KitchenSinkCommand::ContextMenu, $mouse->where));
                    }
                } else {
                    $this->pulse = ($this->pulse + 7) % 101;
                }
                $this->drawView();
                $this->clearEvent($event);
            }

            return;
        }

        if ($event->what !== EventType::KeyDown || ! $this->getState(State::Focused)) {
            return;
        }
        $key = $event->asKey();
        if ($key?->keyCode === Key::Left->value) {
            $this->pulse = max(0, $this->pulse - 5);
        } elseif ($key?->keyCode === Key::Right->value) {
            $this->pulse = min(100, $this->pulse + 5);
        } elseif ($key?->char === ' ') {
            $this->live = ! $this->live;
        } else {
            return;
        }
        $this->interactions++;
        $this->drawView();
        $this->clearEvent($event);
    }

    private function card(DrawBuffer $row, int $x, string $index, string $title, string $detail, int $accent, int $muted): void
    {
        $row->moveStr($x, $index, $accent);
        $row->moveStr($x + 4, str_pad($title, 8), $this->getColor(1));
        $row->moveStr($x + 14, $detail, $muted);
    }
}
