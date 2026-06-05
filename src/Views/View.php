<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;

/**
 * The base of every visible object (faithful to Turbo Vision's TView). Mutable.
 * Holds bounds + sf/of/gf flag words + an owner. The write primitives composite
 * a DrawBuffer (or char/string) into the ROOT Screen's back buffer at this view's
 * absolute origin, clipped to its own extent (M1 keeps clipping to the view extent;
 * full ancestor clipping arrives in M2).
 */
class View
{
    public ?View $owner = null;

    public int $state = State::Visible;

    public int $options = 0;

    public int $growMode = 0;

    /** Cursor position in local coordinates (set by setCursor). */
    protected Point $cursor;

    public function __construct(public Rect $bounds)
    {
        $this->cursor = new Point(0, 0);
    }

    // --- ownership / tree ---

    public function setOwner(?View $owner): void
    {
        $this->owner = $owner;
    }

    /**
     * The live Screen at the root of the tree, or null if not yet owned by a
     * Screen-backed root. Default: delegate to the owner; a Program overrides screen().
     */
    public function screen(): ?Screen
    {
        return $this->owner?->screen();
    }

    /** Sum of every ancestor's bounds->a from this view up to (excluding) the root. */
    public function absoluteOrigin(): Point
    {
        $x = $this->bounds->a->x;
        $y = $this->bounds->a->y;
        $node = $this->owner;
        while ($node !== null && $node->owner !== null) {
            $x += $node->bounds->a->x;
            $y += $node->bounds->a->y;
            $node = $node->owner;
        }

        return new Point($x, $y);
    }

    // --- geometry ---

    public function getBounds(): Rect
    {
        return $this->bounds;
    }

    /** The bounds translated to origin (0,0). */
    public function getExtent(): Rect
    {
        return Rect::of(0, 0, $this->bounds->width(), $this->bounds->height());
    }

    public function setBounds(Rect $bounds): void
    {
        $this->bounds = $bounds;
    }

    /**
     * Minimum/maximum size limits. M1 imposes none beyond non-negative; returned as
     * [minWidth, minHeight, maxWidth, maxHeight].
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    public function sizeLimits(): array
    {
        return [0, 0, PHP_INT_MAX, PHP_INT_MAX];
    }

    public function setCursor(int $x, int $y): void
    {
        $this->cursor = new Point($x, $y);
    }

    // --- state flags ---

    public function getState(int $flag): bool
    {
        return ($this->state & $flag) === $flag;
    }

    public function setState(int $flag, bool $enable): void
    {
        if ($enable) {
            $this->state |= $flag;
        } else {
            $this->state &= ~$flag;
        }
    }

    // --- drawing ---

    /** Default draw: fill the extent with a blank in the view's normal color. */
    public function draw(): void
    {
        $b = new DrawBuffer($this->bounds->width());
        $b->moveChar(0, ' ', $this->mapColor(1), $this->bounds->width());
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $this->writeLine(0, $y, $this->bounds->width(), 1, $b);
        }
    }

    /** Draw only if visible and exposed (owned by a Screen-backed root). */
    public function drawView(): void
    {
        if (! $this->getState(State::Visible)) {
            return;
        }
        if ($this->screen() === null) {
            return;
        }
        $this->draw();
    }

    /** The primary extension point; default no-op. */
    public function handleEvent(Event $event): void
    {
        // no-op
    }

    public function clearEvent(Event $event): void
    {
        $event->clear();
    }

    // --- palette / color ---

    /** This view's own palette, or null (then color resolves through the owner). */
    public function getPalette(): ?Palette
    {
        return null;
    }

    /** Resolve a single logical color index to an attribute byte via the palette chain. */
    public function mapColor(int $index): int
    {
        if ($index === 0) {
            return 0x07;
        }

        $palette = $this->getPalette();
        if ($palette !== null) {
            $mapped = $palette->get($index);
            // Walk up: the byte we just looked up is itself an index into the owner.
            if ($this->owner !== null) {
                return $this->owner->mapColor($mapped);
            }

            return $mapped;
        }

        if ($this->owner !== null) {
            return $this->owner->mapColor($index);
        }

        return 0x07;
    }

    /**
     * Resolve a (possibly two-byte) color word: the high byte and low byte each map
     * through the palette chain, recombined as (hi<<8 | lo). Faithful to TView::getColor.
     */
    public function getColor(int $color): int
    {
        $lo = $this->mapColor($color & 0xFF);
        $hi = $this->mapColor(($color >> 8) & 0xFF);

        return ($hi << 8) | $lo;
    }

    // --- screen-writing primitives (composite into the root Screen back buffer) ---

    /**
     * Blit a horizontal strip of a DrawBuffer into the root back buffer. $x/$y are
     * local; $w cells of one row starting at the buffer's column 0 are written.
     * Clipped to the view extent.
     */
    public function writeBuf(int $x, int $y, int $w, int $h, DrawBuffer $source): void
    {
        $cells = $source->cells();
        for ($row = 0; $row < $h; $row++) {
            $this->writeRowCells($x, $y + $row, $w, $cells);
        }
    }

    /** Like writeBuf but repeats one DrawBuffer row down $h lines. */
    public function writeLine(int $x, int $y, int $w, int $h, DrawBuffer $source): void
    {
        $cells = $source->cells();
        for ($row = 0; $row < $h; $row++) {
            $this->writeRowCells($x, $y + $row, $w, $cells);
        }
    }

    public function writeChar(int $x, int $y, string $char, int $attr, int $count): void
    {
        $b = new DrawBuffer(max(1, $x + $count));
        $b->moveChar($x, $char, $attr, $count);
        $this->writeRowCells($x, $y, $count, $b->cells());
    }

    public function writeStr(int $x, int $y, string $str, int $attr): void
    {
        $len = mb_strlen($str);
        $b = new DrawBuffer(max(1, $x + $len));
        $b->moveStr($x, $str, $attr);
        $this->writeRowCells($x, $y, $len, $b->cells());
    }

    /**
     * Composite $count cells (taken from $cells starting at local column $localX) into
     * the root back buffer at the view's absolute origin, clipped to the view extent.
     *
     * @param Cell[] $cells
     */
    private function writeRowCells(int $localX, int $localY, int $count, array $cells): void
    {
        $screen = $this->screen();
        if ($screen === null) {
            return;
        }
        if ($localY < 0 || $localY >= $this->bounds->height()) {
            return;
        }

        $origin = $this->absoluteOrigin();
        $back = $screen->back();

        for ($i = 0; $i < $count; $i++) {
            $cx = $localX + $i;
            if ($cx < 0 || $cx >= $this->bounds->width()) {
                continue; // outside the view extent
            }
            $cell = $cells[$cx] ?? new Cell();
            $back->put($origin->x + $cx, $origin->y + $localY, $cell);
        }
    }
}
