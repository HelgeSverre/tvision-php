<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;
use HelgeSverre\TurboVision\Support\SizeLimits;
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

    /**
     * Event classes this view accepts. Groups consult this before routing an event
     * to a child; a plain view deliberately receives only click, key, and command
     * events, matching TView's historical default.
     */
    public int $eventMask = EventMask::MouseDown | EventMask::Keyboard | EventMask::Command;

    /** Application-defined help context, zero meaning no context. */
    public int $helpCtx = 0;

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

    /**
     * Present the current back buffer immediately.
     *
     * Program normally owns frame presentation, but blocking modal loops cannot
     * return to Program's outer redraw cycle until they close. Keeping the small
     * primitive on View lets nested modal hosts display each state without knowing
     * which concrete object owns the Screen.
     */
    public function present(): void
    {
        $screen = $this->screen();
        if ($screen === null) {
            return;
        }

        $screen->setCursor($this->cursorPosition());
        $screen->flush();
    }

    /**
     * Give the application root first refusal on lifecycle events received while
     * a nested Group owns the blocking event loop. A returned command terminates
     * the current modal scope; null leaves the event for the modal view.
     */
    public function handleModalEvent(Event $event): ?int
    {
        return $this->owner?->handleModalEvent($event);
    }

    /**
     * Sum of every ancestor's bounds->a from this view up to (excluding) the
     * root. NOTE: a root whose own bounds->a is not (0, 0) is invisible to this
     * walk — offset roots render blank because every clip and blit assumes the
     * root starts at the screen origin. Keep roots anchored at (0, 0).
     */
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

    public function sizeLimits(): SizeLimits
    {
        if (($this->growMode & State::GrowFixed) === 0 && $this->owner !== null) {
            $extent = $this->owner->getExtent();

            return new SizeLimits(0, 0, $extent->width(), $extent->height());
        }

        return new SizeLimits();
    }

    /** Resize and/or reposition this view, clamping its extent to sizeLimits(). */
    public function locate(Rect $bounds): void
    {
        $limits = $this->sizeLimits();
        $width = $limits->clampWidth($bounds->width());
        $height = $limits->clampHeight($bounds->height());
        [$width, $height] = self::fitDrawableSize($width, $height);
        $located = Rect::of(
            $bounds->a->x,
            $bounds->a->y,
            IntMath::add($bounds->a->x, $width),
            IntMath::add($bounds->a->y, $height),
        );

        if (! $located->equals($this->bounds)) {
            $this->changeBounds($located);
            // changeBounds paints only the new footprint. Repaint the owner in
            // Z-order as well so the old footprint is restored by underlying
            // siblings/backgrounds instead of leaving stale cells behind.
            if ($this->owner !== null && $this->getState(State::Visible)) {
                $this->owner->drawView();
            }
        }
    }

    /** Preserve the current size while moving the local origin. */
    public function moveTo(int $x, int $y): void
    {
        $this->locate(Rect::of(
            $x,
            $y,
            IntMath::add($x, $this->bounds->width()),
            IntMath::add($y, $this->bounds->height()),
        ));
    }

    /** Preserve the current origin while growing/shrinking to an extent. */
    public function growTo(int $width, int $height): void
    {
        $this->locate(Rect::of(
            $this->bounds->a->x,
            $this->bounds->a->y,
            IntMath::add($this->bounds->a->x, $width),
            IntMath::add($this->bounds->a->y, $height),
        ));
    }

    public function setCursor(int $x, int $y): void
    {
        $this->cursor = new Point($x, $y);
        $this->resetCursor();
    }

    /** Make this view's local cursor eligible for presentation while focused. */
    public function showCursor(): void
    {
        $this->setState(State::CursorVis, true);
    }

    /** Hide this view's local cursor without changing its stored position. */
    public function hideCursor(): void
    {
        $this->setState(State::CursorVis, false);
    }

    /** Request a solid insertion cursor when the terminal supports its default shape. */
    public function blockCursor(): void
    {
        $this->setState(State::CursorIns, true);
    }

    /** Request the terminal's normal cursor shape. */
    public function normalCursor(): void
    {
        $this->setState(State::CursorIns, false);
    }

    /**
     * The absolute cursor position to present, or null when this view is not the
     * focused visible cursor owner. Groups override this to follow their current
     * subview.
     */
    public function cursorPosition(): ?Point
    {
        if (! $this->getState(State::Visible)
            || ! $this->getState(State::Focused)
            || ! $this->getState(State::CursorVis)
            || ! $this->getExtent()->contains($this->cursor)
        ) {
            return null;
        }

        $origin = $this->absoluteOrigin();

        return new Point(IntMath::add($origin->x, $this->cursor->x), IntMath::add($origin->y, $this->cursor->y));
    }

    /** Refresh the root Screen's desired cursor; actual output is coalesced by flush(). */
    public function resetCursor(): void
    {
        $this->screen()?->setCursor($this->cursorPosition());
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

    /**
     * The inverse of makeLocal(): translate a point in this view's local
     * coordinates into root-relative (global) space. Any drag implementation
     * needs this to convert local anchors into screen coordinates.
     */
    public function localToGlobal(Point $local): Point
    {
        $origin = $this->absoluteOrigin();

        return new Point(
            IntMath::add($origin->x, $local->x),
            IntMath::add($origin->y, $local->y),
        );
    }

    /** True if a global point falls within this view's bounds. */
    public function mouseInView(Point $global): bool
    {
        return $this->getExtent()->contains($this->makeLocal($global));
    }

    /** True for a visible view receiving a positional event within its extent. */
    public function containsMouse(Event $event): bool
    {
        $mouse = $event->asMouse();

        return $this->getState(State::Visible) && $mouse !== null && $this->mouseInView($mouse->where);
    }

    /**
     * Whether this view paints its whole rectangular extent. Transparent container
     * views override this so compositing clips against their opaque descendants
     * instead of treating the container's input region as painted content.
     */
    public function isOpaque(): bool
    {
        return true;
    }

    /**
     * Horizontal screen intervals this view obscures on a row.
     *
     * @return list<array{0:int,1:int}>
     */
    public function occlusionIntervals(int $globalY, int $minX, int $maxX): array
    {
        $interval = OcclusionRow::clip($this, $globalY, $minX, $maxX);

        return $interval === null || ! $this->isOpaque() ? [] : [$interval];
    }

    /** Whether at least one screen cell remains visible after higher-Z sibling clipping. */
    public function exposed(): bool
    {
        if (! $this->getState(State::Visible) || $this->screen() === null) {
            return false;
        }

        $rect = $this->globalBounds();
        for ($ancestor = $this->owner; $ancestor !== null; $ancestor = $ancestor->owner) {
            if (! $ancestor->getState(State::Visible)) {
                return false;
            }
            $rect = $rect->intersect($ancestor->globalBounds());
        }

        $rect = $rect->intersect(Rect::of(0, 0, $this->screen()->cols(), $this->screen()->rows()));
        if ($rect->isEmpty()) {
            return false;
        }

        for ($y = $rect->a->y; $y < $rect->b->y; $y++) {
            $coveredTo = $rect->a->x;
            foreach ($this->siblingOcclusionIntervals($y, $rect->a->x, $rect->b->x) as [$start, $end]) {
                if ($start > $coveredTo) {
                    return true;
                }
                $coveredTo = max($coveredTo, $end);
                if ($coveredTo >= $rect->b->x) {
                    break;
                }
            }
            if ($coveredTo < $rect->b->x) {
                return true;
            }
        }

        return false;
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

        $limits = $this->sizeLimits();
        $width = $limits->clampWidth(IntMath::subtract($bx, $ax));
        $height = $limits->clampHeight(IntMath::subtract($by, $ay));
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
     * Apply a drag result directly: $limits clamps the origin and $min/$max clamp the
     * size. Frame and Window drive this from their mouse handlers.
     */
    public function dragView(Rect $newBounds, Rect $limits, Point $min, Point $max): void
    {
        $w = min(max(0, $limits->width()), max($min->x, min($max->x, $newBounds->width())));
        $h = min(max(0, $limits->height()), max($min->y, min($max->y, $newBounds->height())));

        $ax = $newBounds->a->x;
        $ay = $newBounds->a->y;
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
        $wasFocused = $this->getState(State::Focused);
        if ($enable) {
            $this->state |= $flag;
        } else {
            $this->state &= ~$flag;
        }

        if (($flag & (State::CursorVis | State::CursorIns | State::Focused | State::Visible)) !== 0) {
            $this->resetCursor();
        }

        if (($flag & State::Visible) !== 0 && $this->owner !== null) {
            // A hidden view has to expose what was beneath it; redraw the owner
            // rather than attempting partial compositing from a stale back buffer.
            $this->owner->drawView();
        }

        $isFocused = $this->getState(State::Focused);
        if (($flag & State::Focused) !== 0
            && $wasFocused !== $isFocused
            && $this->owner instanceof Group
        ) {
            // Labels, histories, and other linked controls observe focus changes
            // through the same broadcasts emitted by the original TView.
            $this->owner->handleEvent(Event::broadcast(
                $isFocused ? Cmd::ReceivedFocus : Cmd::ReleasedFocus,
                $this,
            ));
        }

        if ($this->owner instanceof Group) {
            $this->owner->viewStateChanged($this, $flag, $enable);
        }
    }

    public function show(): void
    {
        $this->setState(State::Visible, true);
    }

    public function hide(): void
    {
        $this->setState(State::Visible, false);
    }

    /** Ask the owner to make this selectable view its current focus target. */
    public function focus(): bool
    {
        if ($this->owner === null
            || ($this->options & State::Selectable) === 0
            || ! $this->getState(State::Visible)
            || $this->getState(State::Disabled)
        ) {
            return false;
        }

        $this->select();

        return true;
    }

    /** Select this view and optionally bring top-select views to the front. */
    public function select(): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->setCurrent($this);
            if (($this->options & State::TopSelect) !== 0) {
                $this->makeFirst();
            }
        }
    }

    /** Move this owned view in front of every sibling. */
    public function makeFirst(): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->reorderInFrontOf($this, null);
        }
    }

    /** Move this owned view directly in front of an owned sibling. */
    public function putInFrontOf(?View $target): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->reorderInFrontOf($this, $target);
        }
    }

    /** Returns this view's help context, except while it owns a drag gesture. */
    public function getHelpCtx(): int
    {
        return $this->getState(State::Dragging) ? 1 : $this->helpCtx;
    }

    /** Whether this view permits the supplied modal command to complete. */
    public function valid(int $command): bool
    {
        return true;
    }

    /** Size of this view's transferable form datum; zero means no datum. */
    public function dataSize(): int
    {
        return 0;
    }

    /** Return this view's transferable form datum, if any. */
    public function getData(): mixed
    {
        return null;
    }

    /** Load this view's transferable form datum. */
    public function setData(mixed $data): void {}

    // --- drawing ---

    /**
     * Fill the view's whole extent with $char in the given color, reusing one
     * row buffer for every line. The shared primitive behind the default draw(),
     * Background's pattern fill, and StaticText's clear-then-paint.
     */
    protected function fillExtent(int $attr, string $char = ' '): void
    {
        $width = $this->bounds->width();
        $row = new DrawBuffer($width);
        $row->moveChar(0, $char, $attr, $width);
        for ($y = 0, $height = $this->bounds->height(); $y < $height; $y++) {
            $this->writeLine(0, $y, $width, 1, $row);
        }
    }

    /** Default draw: fill the extent with a blank in the view's normal color. */
    public function draw(): void
    {
        $this->fillExtent($this->mapColor(1));
    }

    /** Draw only if visible and exposed (owned by a Screen-backed root). */
    public function drawView(): void
    {
        if (! $this->exposed()) {
            return;
        }

        $this->draw();
    }

    /** The primary event-handling extension point. */
    public function handleEvent(Event $event): void {}

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
        $this->owner?->endModal($command);
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
        $occluded = $this->siblingOcclusionIntervals($globalY, $clip->a->x, $clip->b->x);
        $occlusionIndex = 0;
        $blank = new Cell();
        for ($offset = 0; $offset < $visibleCells; $offset++) {
            $sourceX = $firstSource + $offset;
            $cx = $targetX + $offset;
            $cell = $cells[$sourceX] ?? $blank;
            $globalX = IntMath::add($origin->x, $cx);
            if ($globalX < $clip->a->x || $globalX >= $clip->b->x) {
                continue;
            }
            while (isset($occluded[$occlusionIndex]) && $globalX >= $occluded[$occlusionIndex][1]) {
                $occlusionIndex++;
            }
            if (isset($occluded[$occlusionIndex])
                && $globalX >= $occluded[$occlusionIndex][0]
                && $globalX < $occluded[$occlusionIndex][1]
            ) {
                continue;
            }
            $back->put($globalX, $globalY, $cell);
        }
    }

    private function globalBounds(): Rect
    {
        $origin = $this->absoluteOrigin();

        return Rect::of(
            $origin->x,
            $origin->y,
            IntMath::add($origin->x, $this->bounds->width()),
            IntMath::add($origin->y, $this->bounds->height()),
        );
    }

    /**
     * Horizontal screen intervals covered by higher-Z siblings at every owner
     * level. Computing and merging them once per row avoids a sibling-tree walk
     * for every cell in a large DrawBuffer.
     *
     * @return list<array{0:int,1:int}>
     */
    private function siblingOcclusionIntervals(int $globalY, int $minX, int $maxX): array
    {
        $intervals = [];
        $child = $this;
        for ($owner = $this->owner; $owner instanceof Group; $owner = $owner->owner) {
            foreach ($owner->higherSiblingIntervals($child, $globalY, $minX, $maxX) as $interval) {
                $intervals[] = $interval;
            }
            $child = $owner;
        }
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }
}
