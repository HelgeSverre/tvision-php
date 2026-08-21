<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;

/** Scrollable, keyboard/mouse navigable renderer for context-sensitive help. */
final class HelpViewer extends Scroller
{
    /** normal text, link, selected link. */
    private const string PALETTE = "\x06\x07\x08";

    public HelpTopic $topic;

    /** Zero-based selected reference, or null when the topic has none. */
    public ?int $selected = null;

    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar,
        ?ScrollBar $vScrollBar,
        public readonly HelpFile $helpFile,
        int $context,
    ) {
        parent::__construct($bounds, $hScrollBar, $vScrollBar);
        $this->options |= State::Selectable;
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->topic = $this->helpFile->getTopic($context);
        $this->resetTopicMetrics();
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function changeBounds(Rect $bounds): void
    {
        parent::changeBounds($bounds);
        $this->resetTopicMetrics();
    }

    public function switchToTopic(int $context): void
    {
        $this->topic = $this->helpFile->getTopic($context);
        $this->selected = $this->topic->getNumCrossRefs() === 0 ? null : 0;
        $this->scrollTo(0, 0);
        $this->resetTopicMetrics();
        $this->drawView();
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $normal = $this->mapColor(1);
        $link = $this->mapColor(2);
        $selectedLink = $this->mapColor(3);
        $lines = $this->topic->lines($width);

        for ($row = 0; $row < $height; $row++) {
            $lineNumber = $this->delta->y + $row;
            $line = TerminalText::slice($lines[$lineNumber] ?? '', $this->delta->x, $width);
            $buffer = new DrawBuffer($width);
            $buffer->moveChar(0, ' ', $normal, $width);
            $buffer->moveStr(0, $line, $normal);
            foreach ($this->topic->crossRefs() as $index => $reference) {
                $location = $this->topic->crossRefLocation($index, $width);
                if ($location->y !== $lineNumber) {
                    continue;
                }
                $attr = $index === $this->selected ? $selectedLink : $link;
                $start = $location->x - $this->delta->x;
                for ($x = max(0, $start); $x < min($width, $start + $reference->length); $x++) {
                    $buffer->putAttribute($x, $attr);
                }
            }
            $this->writeLine(0, $row, $width, 1, $buffer);
        }
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->isNothing()) {
            return;
        }
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey()?->keyCode;
            if ($key === Key::Tab->value) {
                $this->selectRelative(1);
            } elseif ($key === Key::ShiftTab->value) {
                $this->selectRelative(-1);
            } elseif ($key === Key::Enter->value) {
                $this->followSelected();
            } elseif ($key === Key::Esc->value) {
                $this->owner?->endModal(Cmd::Close);
            } else {
                return;
            }
            $this->drawView();
            $this->clearEvent($event);
            return;
        }
        if ($event->what !== EventType::MouseDown) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $point = $this->makeLocal($mouse->where);
        $reference = $this->referenceAt(new Point($point->x + $this->delta->x, $point->y + $this->delta->y));
        if ($reference === null) {
            return;
        }
        $this->selected = $reference;
        if ($mouse->doubleClick) {
            $this->followSelected();
        }
        $this->drawView();
        $this->clearEvent($event);
    }

    private function resetTopicMetrics(): void
    {
        $width = max(1, $this->bounds->width());
        $this->topic->setWidth($width);
        $widest = 0;
        foreach ($this->topic->lines($width) as $line) {
            $widest = max($widest, TerminalText::length($line));
        }
        $this->setLimit(max($width, $widest), $this->topic->numLines($width));
        $this->selected ??= $this->topic->getNumCrossRefs() > 0 ? 0 : null;
    }

    private function selectRelative(int $step): void
    {
        $count = $this->topic->getNumCrossRefs();
        if ($count === 0) {
            $this->selected = null;
            return;
        }
        $this->selected = (($this->selected ?? 0) + $step + $count) % $count;
        $location = $this->topic->crossRefLocation($this->selected, max(1, $this->bounds->width()));
        $targetY = $this->delta->y;
        if ($location->y < $targetY) {
            $targetY = $location->y;
        } elseif ($location->y >= $targetY + $this->bounds->height()) {
            $targetY = $location->y - $this->bounds->height() + 1;
        }
        $this->scrollTo($this->delta->x, max(0, $targetY));
    }

    private function followSelected(): void
    {
        if ($this->selected !== null) {
            $this->switchToTopic($this->topic->getCrossRef($this->selected)->ref);
        }
    }

    private function referenceAt(Point $point): ?int
    {
        foreach ($this->topic->crossRefs() as $index => $reference) {
            $location = $this->topic->crossRefLocation($index, max(1, $this->bounds->width()));
            if ($location->y === $point->y && $point->x >= $location->x && $point->x < $location->x + $reference->length) {
                return $index;
            }
        }

        return null;
    }
}
