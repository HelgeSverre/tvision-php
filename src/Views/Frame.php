<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Window\WindowFlags;

/**
 * The border/title/icon view drawn as a window's last subview (faithful to TFrame).
 * Single-line box when inactive, double-line when active; centered title; close icon
 * (left), zoom/unzoom icon and window number (right), drag icon (bottom-right). Reads
 * everything it needs from the owning Window via the FrameOwner contract and handles
 * the frame's move, resize, close, and zoom mouse gestures.
 */
class Frame extends View
{
    /** cpFrame: 1=passive frame, 2=passive title, 3=active title, 4=active icons, 5=active frame. */
    private const string PALETTE = "\x01\x01\x02\x02\x03";

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->growMode = State::GrowHiX | State::GrowHiY;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    private function frameOwner(): ?FrameOwner
    {
        return $this->owner instanceof FrameOwner ? $this->owner : null;
    }

    public function draw(): void
    {
        $w = $this->bounds->width();
        $h = $this->bounds->height();
        if ($w < 2 || $h < 2) {
            return;
        }

        $active = $this->getState(State::Active);
        $dragging = $this->getState(State::Dragging);

        // Faithful color words: dragging 0x0505/0x0005, inactive 0x0101/0x0002, active 0x0503/0x0004.
        if ($dragging) {
            $cFrame = $this->getColor(0x0505);
            $cTitle = $this->getColor(0x0005);
        } elseif (! $active) {
            $cFrame = $this->getColor(0x0101);
            $cTitle = $this->getColor(0x0002);
        } else {
            $cFrame = $this->getColor(0x0503);
            $cTitle = $this->getColor(0x0004);
        }
        $frameAttr = $cFrame & 0xFF;
        $titleAttr = $cTitle & 0xFF;

        [$tl, $tr, $bl, $br, $hz, $vt] = $active
            ? [Glyphs::DOUBLE_TOP_LEFT, Glyphs::DOUBLE_TOP_RIGHT, Glyphs::DOUBLE_BOTTOM_LEFT, Glyphs::DOUBLE_BOTTOM_RIGHT, Glyphs::DOUBLE_HORIZONTAL, Glyphs::DOUBLE_VERTICAL]
            : [Glyphs::SINGLE_TOP_LEFT, Glyphs::SINGLE_TOP_RIGHT, Glyphs::SINGLE_BOTTOM_LEFT, Glyphs::SINGLE_BOTTOM_RIGHT, Glyphs::SINGLE_HORIZONTAL, Glyphs::SINGLE_VERTICAL];

        // --- top line ---
        $top = new DrawBuffer($w);
        $top->moveChar(0, $tl, $frameAttr, 1);
        $top->moveChar(1, $hz, $frameAttr, $w - 2);
        $top->moveChar($w - 1, $tr, $frameAttr, 1);

        $owner = $this->frameOwner();
        if ($owner !== null) {
            $title = $owner->frameTitle();
            if ($title !== '') {
                $maxTitle = max(0, $w - 10);
                $len = min(mb_strlen($title), $maxTitle);
                $title = mb_substr($title, 0, $len);
                $i = intdiv($w - $len, 2);
                $top->moveChar($i - 1, ' ', $titleAttr, 1);
                $top->moveStr($i, $title, $titleAttr);
                $top->moveChar($i + $len, ' ', $titleAttr, 1);
            }

            $flags = $owner->frameFlags();
            $number = $owner->frameNumber();

            if ($active) {
                if (($flags & WindowFlags::Close) !== 0) {
                    $top->moveCStr(2, Glyphs::CLOSE_ICON, $frameAttr, $frameAttr);
                }
                if (($flags & WindowFlags::Zoom) !== 0) {
                    $icon = $owner->frameIsZoomed() ? Glyphs::UNZOOM_ICON : Glyphs::ZOOM_ICON;
                    $top->moveCStr($w - 5, $icon, $frameAttr, $frameAttr);
                }
            }

            if ($number > 0 && $number < 10) {
                $col = ($flags & WindowFlags::Zoom) !== 0 ? $w - 7 : $w - 3;
                $top->moveChar($col, (string) $number, $frameAttr, 1);
            }
        }

        $this->writeLine(0, 0, $w, 1, $top);

        // --- middle lines ---
        for ($y = 1; $y < $h - 1; $y++) {
            $mid = new DrawBuffer($w);
            $mid->moveChar(0, $vt, $frameAttr, 1);
            $mid->moveChar(1, ' ', $frameAttr, $w - 2);
            $mid->moveChar($w - 1, $vt, $frameAttr, 1);
            $this->writeLine(0, $y, $w, 1, $mid);
        }

        // --- bottom line ---
        $bot = new DrawBuffer($w);
        $bot->moveChar(0, $bl, $frameAttr, 1);
        $bot->moveChar(1, $hz, $frameAttr, $w - 2);
        $bot->moveChar($w - 1, $br, $frameAttr, 1);

        if ($active && $owner !== null && ($owner->frameFlags() & WindowFlags::Grow) !== 0) {
            $bot->moveCStr($w - 3, Glyphs::DRAG_ICON, $frameAttr, $frameAttr);
        }
        $this->writeLine(0, $h - 1, $w, 1, $bot);
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & (State::Active | State::Dragging)) !== 0) {
            $this->drawView();
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what !== EventType::MouseDown) {
            return;
        }
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        $owner = $this->frameOwner();
        if ($owner === null) {
            return;
        }

        $local = $this->makeLocal($mouse->where);
        $w = $this->bounds->width();
        $h = $this->bounds->height();
        $flags = $owner->frameFlags();
        $active = $this->getState(State::Active);

        if ($local->y === 0) {
            $inClose = $active && ($flags & WindowFlags::Close) !== 0 && $local->x >= 2 && $local->x <= 4;
            $inZoom = $active && ($flags & WindowFlags::Zoom) !== 0
                && (($local->x >= $w - 5 && $local->x <= $w - 3) || $mouse->doubleClick);

            if ($inClose) {
                $this->putEvent(Event::command(Cmd::Close, $owner));
                $this->clearEvent($event);

                return;
            }
            if ($inZoom) {
                $this->putEvent(Event::command(Cmd::Zoom, $owner));
                $this->clearEvent($event);

                return;
            }
            if (($flags & WindowFlags::Move) !== 0) {
                if ($owner instanceof Window) {
                    $owner->beginMouseDrag($mouse, State::DragMove);
                } else {
                    $this->putEvent(Event::command(Cmd::Resize, $owner));
                }
                $this->clearEvent($event);

                return;
            }
        }

        // Bottom-right resize corner.
        if ($local->x >= $w - 2 && $local->y >= $h - 1 && $active && ($flags & WindowFlags::Grow) !== 0) {
            if ($owner instanceof Window) {
                $owner->beginMouseDrag($mouse, State::DragGrow);
            } else {
                $this->putEvent(Event::command(Cmd::Resize, $owner));
            }
            $this->clearEvent($event);
        }
    }

    /** Frame delegates putEvent up to its owner Group (which routes to Program). */
    public function putEvent(Event $event): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->putEvent($event);
        }
    }
}
