<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;
use HelgeSverre\TurboVision\Terminal\Screen;
use InvalidArgumentException;

/**
 * The base of every visible object (faithful to Turbo Vision's TView). Mutable.
 * Holds bounds + sf/of/gf flag words + an owner. The write primitives composite
 * a DrawBuffer (or char/string) into the ROOT Screen's back buffer at this view's
 * absolute origin, clipped to its own extent and every ancestor's bounds.
 */
class View
{
    public private(set) ?View $owner = null;

    public int $state = State::Visible;

    public int $options = 0;

    public int $growMode = 0;

    /** Cursor position in local coordinates (set by setCursor). */
    protected Point $cursor;

    public function __construct(public private(set) Rect $bounds)
    {
        self::assertValidBounds($bounds);
        $this->cursor = new Point(0, 0);
    }

    // --- ownership / tree ---

    public function setOwner(?View $owner): void
    {
        for ($ancestor = $owner; $ancestor !== null; $ancestor = $ancestor->owner) {
            if ($ancestor === $this) {
                throw new InvalidArgumentException('A view cannot own itself or one of its ancestors.');
            }
        }
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
            $x = IntMath::add($x, $node->bounds->a->x);
            $y = IntMath::add($y, $node->bounds->a->y);
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
        self::assertValidBounds($bounds);
        $this->bounds = $bounds;
    }

    private static function assertValidBounds(Rect $bounds): void
    {
        $width = $bounds->width();
        $height = $bounds->height();
        if ($width < 0 || $height < 0) {
            throw new InvalidArgumentException('View bounds must have a non-negative extent.');
        }
        if ($width > Buffer::MAX_CELLS
            || $height > Buffer::MAX_CELLS
            || ($width !== 0 && $height > intdiv(Buffer::MAX_CELLS, $width))
        ) {
            throw new InvalidArgumentException('View bounds exceed the safe drawable-cell limit.');
        }
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

    /** Translate a global (root-relative) point into this view's local coordinates. */
    public function makeLocal(Point $global): Point
    {
        $origin = $this->absoluteOrigin();

        return new Point(
            IntMath::subtract($global->x, $origin->x),
            IntMath::subtract($global->y, $origin->y),
        );
    }

    /** True if a global point falls within this view's bounds. */
    public function mouseInView(Point $global): bool
    {
        return $this->getExtent()->contains($this->makeLocal($global));
    }

    /**
     * The drawing rectangle in local coordinates. Screen writes are additionally
     * intersected with every ancestor at composition time.
     */
    public function getClipRect(): Rect
    {
        return $this->getExtent();
    }

    /**
     * Compute new bounds when the owner grows by $delta, honoring this view's growMode.
     * Faithful to TView::calcBounds: gfGrowLoX/HiX move the left/right edge, gfGrowLoY/
     * HiY the top/bottom edge. gfGrowAll = all four. gfGrowRel scales each selected
     * edge in proportion to the owner's old/new size (the default Window behavior).
     */
    public function calcBounds(Point $delta): Rect
    {
        $ax = $this->bounds->a->x;
        $ay = $this->bounds->a->y;
        $bx = $this->bounds->b->x;
        $by = $this->bounds->b->y;
        $ownerExtent = $this->owner?->getExtent();
        $ownerWidth = $ownerExtent?->width() ?? 0;
        $ownerHeight = $ownerExtent?->height() ?? 0;

        if (($this->growMode & State::GrowLoX) !== 0) {
            $ax = $this->growCoordinate($ax, $ownerWidth, $delta->x);
        }
        if (($this->growMode & State::GrowHiX) !== 0) {
            $bx = $this->growCoordinate($bx, $ownerWidth, $delta->x);
        }
        if (($this->growMode & State::GrowLoY) !== 0) {
            $ay = $this->growCoordinate($ay, $ownerHeight, $delta->y);
        }
        if (($this->growMode & State::GrowHiY) !== 0) {
            $by = $this->growCoordinate($by, $ownerHeight, $delta->y);
        }

        [$minWidth, $minHeight, $maxWidth, $maxHeight] = $this->sizeLimits();
        $width = max($minWidth, min($maxWidth, IntMath::subtract($bx, $ax)));
        $height = max($minHeight, min($maxHeight, IntMath::subtract($by, $ay)));
        [$width, $height] = self::fitDrawableSize($width, $height);

        $bx = IntMath::add($ax, max(0, $width));
        $by = IntMath::add($ay, max(0, $height));

        return Rect::of($ax, $ay, $bx, $by);
    }

    /** @return array{0:int,1:int} */
    private static function fitDrawableSize(int $width, int $height): array
    {
        $width = min(Buffer::MAX_CELLS, max(0, $width));
        $height = min(Buffer::MAX_CELLS, max(0, $height));
        if ($width !== 0) {
            $height = min($height, intdiv(Buffer::MAX_CELLS, $width));
        }

        return [$width, $height];
    }

    private function growCoordinate(int $coordinate, int $newOwnerSize, int $delta): int
    {
        if (($this->growMode & State::GrowRel) === 0) {
            return IntMath::add($coordinate, $delta);
        }

        $oldOwnerSize = IntMath::subtract($newOwnerSize, $delta);
        if ($oldOwnerSize <= 0) {
            return IntMath::add($coordinate, $delta);
        }

        return intdiv(
            IntMath::add(IntMath::multiply($coordinate, $newOwnerSize), intdiv($oldOwnerSize, 2)),
            $oldOwnerSize,
        );
    }

    /**
     * Apply new bounds. Group overrides this to additionally reflow its subviews.
     * The single funnel every move/resize routes through.
     */
    public function changeBounds(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->drawView();
    }

    /**
     * Move/resize this view in response to a drag. M2 implements the geometric result
     * directly (no inner pump loop): $mode selects move vs grow, $limits clamps the
     * origin, $min/$max clamp the size. Frame/Window drive this from mouse handlers.
     */
    public function dragView(Rect $newBounds, Rect $limits, Point $min, Point $max): void
    {
        $w = min(max(0, $limits->width()), max($min->x, min($max->x, $newBounds->width())));
        $h = min(max(0, $limits->height()), max($min->y, min($max->y, $newBounds->height())));

        $ax = $newBounds->a->x;
        $ay = $newBounds->a->y;
        // Keep the view fully inside $limits.
        $ax = max($limits->a->x, min(IntMath::subtract($limits->b->x, $w), $ax));
        $ay = max($limits->a->y, min(IntMath::subtract($limits->b->y, $h), $ay));

        $this->changeBounds(Rect::of($ax, $ay, IntMath::add($ax, $w), IntMath::add($ay, $h)));
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
        for ($view = $this; $view !== null; $view = $view->owner) {
            if (! $view->getState(State::Visible)) {
                return;
            }
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

    /** Whether this view currently owns an in-progress mouse gesture. */
    public function hasMouseCapture(): bool
    {
        return $this->getState(State::Dragging);
    }

    /** Resolve command state through the owner chain; Program owns the real set. */
    public function commandEnabled(int $command): bool
    {
        return $this->owner?->commandEnabled($command) ?? true;
    }

    /**
     * End the current modal execute() loop with $command. Overridden by Group;
     * the stub here makes it safe to call on any View owner reference.
     */
    public function endModal(int $command): void
    {
        // no-op on a plain View; Group overrides
    }

    /**
     * Fetch the next event for a modal loop. Overridden by Group (which walks up
     * to the root Screen); the stub here makes it safe to call on any View owner.
     */
    public function pumpEvent(): ?Event
    {
        return null;
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
     * Clipped to the view and its ancestor extents.
     */
    public function writeBuf(int $x, int $y, int $w, int $h, DrawBuffer $source): void
    {
        $this->writeRows($x, $y, $w, $h, $source->cells());
    }

    /** Like writeBuf but repeats one DrawBuffer row down $h lines. */
    public function writeLine(int $x, int $y, int $w, int $h, DrawBuffer $source): void
    {
        $this->writeRows($x, $y, $w, $h, $source->cells());
    }

    /**
     * Repeat one source row only across the portion intersecting this view. Deriving
     * the target interval first keeps adversarial widths/heights from becoming loop
     * counts and avoids overflowing `$y + $row` near the integer limits.
     *
     * @param Cell[] $cells
     */
    private function writeRows(int $x, int $y, int $w, int $h, array $cells): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }

        $height = $this->bounds->height();
        if ($height <= 0 || $y >= $height) {
            return;
        }

        $skippedRows = 0;
        $targetY = $y;
        if ($y < 0) {
            // If the requested interval ends at/before row zero, it is wholly clipped.
            // `$h` is positive here, so negating it cannot overflow.
            if ($y <= -$h) {
                return;
            }
            $skippedRows = -$y;
            $targetY = 0;
        }

        $visibleRows = min($h - $skippedRows, $height - $targetY);
        for ($row = 0; $row < $visibleRows; $row++) {
            $this->writeRowCells($x, $targetY + $row, $w, $cells);
        }
    }

    public function writeChar(int $x, int $y, string $char, int $attr, int $count): void
    {
        $b = new DrawBuffer(max(1, $count));
        $b->moveChar(0, $char, $attr, $count);
        $this->writeRowCells($x, $y, $count, $b->cells());
    }

    public function writeStr(int $x, int $y, string $str, int $attr): void
    {
        $len = TerminalText::length($str);
        $b = new DrawBuffer(max(1, $len));
        $b->moveStr(0, $str, $attr);
        $this->writeRowCells($x, $y, $len, $b->cells());
    }

    /**
     * Composite the first $count source cells at local ($localX,$localY), clipped to
     * the view extent, every ancestor extent, and the screen.
     *
     * @param Cell[] $cells
     */
    private function writeRowCells(int $localX, int $localY, int $count, array $cells): void
    {
        if ($count <= 0) {
            return;
        }

        $screen = $this->screen();
        if ($screen === null) {
            return;
        }
        if ($localY < 0 || $localY >= $this->bounds->height()) {
            return;
        }

        $origin = $this->absoluteOrigin();
        $back = $screen->back();
        $clip = Rect::of(
            $origin->x,
            $origin->y,
            IntMath::add($origin->x, $this->bounds->width()),
            IntMath::add($origin->y, $this->bounds->height()),
        );
        $ancestor = $this->owner;
        while ($ancestor !== null) {
            $ancestorOrigin = $ancestor->absoluteOrigin();
            $clip = $clip->intersect(Rect::of(
                $ancestorOrigin->x,
                $ancestorOrigin->y,
                IntMath::add($ancestorOrigin->x, $ancestor->bounds->width()),
                IntMath::add($ancestorOrigin->y, $ancestor->bounds->height()),
            ));
            $ancestor = $ancestor->owner;
        }
        $clip = $clip->intersect(Rect::of(0, 0, $screen->cols(), $screen->rows()));
        $globalY = IntMath::add($origin->y, $localY);
        if ($clip->isEmpty() || $globalY < $clip->a->y || $globalY >= $clip->b->y) {
            return;
        }

        $width = $this->bounds->width();
        if ($width <= 0 || $localX >= $width) {
            return;
        }

        $firstSource = 0;
        $targetX = $localX;
        if ($localX < 0) {
            // No part of [localX, localX + count) reaches the view. Avoid adding the
            // two caller-controlled integers because that addition itself may overflow.
            if ($localX <= -$count) {
                return;
            }
            $firstSource = -$localX;
            $targetX = 0;
        }

        $visibleCells = min($count - $firstSource, $width - $targetX);
        $blank = new Cell();
        for ($offset = 0; $offset < $visibleCells; $offset++) {
            $sourceX = $firstSource + $offset;
            $cx = $targetX + $offset;
            $cell = $cells[$sourceX] ?? $blank;
            $globalX = IntMath::add($origin->x, $cx);
            if ($globalX < $clip->a->x || $globalX >= $clip->b->x) {
                continue;
            }
            $back->put($globalX, $globalY, $cell);
        }
    }
}
