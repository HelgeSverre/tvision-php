<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Outline;

use HelgeSverre\TurboVision\Support\IntMath;
use Closure;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;
use HelgeSverre\TurboVision\Views\State;
use LogicException;
use SplObjectStorage;

/**
 * Abstract, scrollable tree viewer (Turbo Vision's TOutlineViewer).
 *
 * A subclass supplies its tree storage through the six abstract node accessors.
 * Visible positions are preorder positions: collapsed descendants never consume a
 * row. The implementation intentionally uses only single-cell Unicode box glyphs,
 * so graph columns stay aligned with the framework's cell-based DrawBuffer.
 */
abstract class OutlineViewer extends Scroller
{
    /** ovExpanded: this line is expanded (or is a leaf). */
    public const int Expanded = 0x01;

    /** ovChildren: this line has visible children. */
    public const int Children = 0x02;

    /** ovLast: this line is the final sibling. */
    public const int Last = 0x04;

    /** Original cmOutlineItemSelected command value. */
    public const int ItemSelected = Cmd::OutlineItemSelected;

    /** cpOutlineViewer: normal, focus, selected, collapsed. */
    private const string PALETTE = "\x06\x07\x03\x08";

    /** Zero-based focused visible position. */
    public int $focused = 0;

    public function __construct(Rect $bounds, ?ScrollBar $hScrollBar = null, ?ScrollBar $vScrollBar = null)
    {
        parent::__construct($bounds, $hScrollBar, $vScrollBar);
        $this->growMode = State::GrowHiX | State::GrowHiY;
        // Mouse drags need their move/up events routed after the initial click.
        $this->eventMask |= EventMask::Mouse;
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    abstract public function getRoot(): ?Node;

    abstract public function getNumChildren(Node $node): int;

    abstract public function getChild(Node $node, int $index): ?Node;

    abstract public function getText(Node $node): string;

    abstract public function hasChildren(Node $node): bool;

    abstract public function isExpanded(Node $node): bool;

    /** Show or hide $node's descendants. Called only for nodes with children. */
    abstract public function adjust(Node $node, bool $expand): void;

    /** The default single-selection model. Override for multi-selection outlines. */
    public function isSelected(int $position): bool
    {
        return $position === $this->focused;
    }

    /**
     * Selection hook. It broadcasts the legacy outline selection command upward,
     * carrying this viewer as the payload, like ListViewer's selection broadcast.
     */
    public function selected(int $position): void
    {
        $this->owner?->handleEvent(Event::broadcast(self::ItemSelected, $this));
    }

    /** Hook for subclasses that mirror the focus into an external data model. */
    public function focused(int $position): void
    {
        $this->focused = $position;
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & State::Focused) !== 0) {
            $this->drawView();
        }
    }

    /** Recalculate scroll extents after changing tree data or expansion state. */
    public function update(): void
    {
        $count = 0;
        $maxWidth = 0;
        $this->walkVisible(function (self $_viewer, Node $node, int $level, int $position, array $continues, int $flags) use (&$count, &$maxWidth): bool {
            $count = $position + 1;
            $maxWidth = max($maxWidth, TerminalText::length($this->graphFor($level, $continues, $flags)) + TerminalText::length($this->getText($node)));

            return false;
        });
        $this->setLimit($maxWidth, $count);
        $this->adjustFocus($this->focused);
    }

    /** Expand $node and every descendant with children. */
    public function expandAll(Node $node): void
    {
        if (! $this->hasChildren($node)) {
            return;
        }
        $this->adjust($node, true);
        $count = max(0, $this->getNumChildren($node));
        for ($i = 0; $i < $count; $i++) {
            $child = $this->getChild($node, $i);
            if ($child !== null) {
                $this->expandAll($child);
            }
        }
    }

    /** Return the visible node at $position, or null when it is out of range. */
    public function getNode(int $position): ?Node
    {
        $found = null;
        $this->walkVisible(function (self $_viewer, Node $node, int $_level, int $current) use ($position, &$found): bool {
            if ($current !== $position) {
                return false;
            }
            $found = $node;

            return true;
        });

        return $found;
    }

    /**
     * Call $action for each currently visible node. Returning true stops the walk
     * and returns that node. The callback receives node, level, visible position,
     * continuation bits, and ov flags.
     */
    public function firstThat(Closure $action): ?Node
    {
        return $this->walkVisible($action);
    }

    /** Same callback contract as firstThat(), but always traverses the full tree. */
    public function forEachNode(Closure $action): void
    {
        $this->walkVisible(function (self $viewer, Node $node, int $level, int $position, array $continues, int $flags) use ($action): bool {
            $action($viewer, $node, $level, $position, $continues, $flags);

            return false;
        });
    }

    /**
     * Create a graph with the original bitset API. The default implementation
     * converts the bitset to continuation marks before drawing Unicode glyphs.
     */
    public function getGraph(int $level, int $lines, int $flags): string
    {
        return $this->createGraph($level, $this->continuationsFor($level, $lines), $flags);
    }

    /**
     * Construct a one-cell-per-character tree graph. $levelWidth and $endWidth
     * retain the original extensibility while being guarded to prevent unbounded
     * caller-controlled buffers.
     *
     * $lines accepts the original bitset form and an internal continuation list.
     * @param int|list<bool> $lines
     */
    public function createGraph(int $level, int|array $lines, int $flags, int $levelWidth = 3, int $endWidth = 3): string
    {
        $level = max(0, $level);
        $levelWidth = max(1, min(64, $levelWidth));
        $endWidth = max(1, min(64, $endWidth));
        $continues = is_array($lines) ? $lines : $this->continuationsFor($level, $lines);
        $graph = '';
        for ($i = 0; $i < $level; $i++) {
            $graph .= ($continues[$i] ?? false) ? '│' : ' ';
            $graph .= str_repeat(' ', $levelWidth - 1);
        }

        if ($endWidth === 1) {
            return $graph . $this->expansionGlyph($flags);
        }

        $graph .= ($flags & self::Last) !== 0 ? '└' : '├';
        if ($endWidth > 2) {
            $graph .= str_repeat('─', $endWidth - 2);
        }

        return $graph . $this->expansionGlyph($flags);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $normal = $this->getColor(1) & 0xFF;
        $lastDrawn = -1;
        $this->walkVisible(function (self $_viewer, Node $node, int $level, int $position, array $continues, int $flags) use ($width, &$lastDrawn): bool {
            if ($position < $this->delta->y) {
                return false;
            }
            if ($position >= $this->delta->y + $this->bounds->height()) {
                return true;
            }

            $selected = $this->isSelected($position);
            $color = $position === $this->focused && $this->getState(State::Focused)
                ? $this->getColor(2)
                : ($selected ? $this->getColor(3) : $this->getColor(1));
            if (($flags & self::Expanded) === 0 && ! $selected) {
                $color = $this->getColor(4);
            }
            $color &= 0xFF;

            $buffer = new DrawBuffer($width);
            $buffer->moveChar(0, ' ', $color, $width);
            $line = $this->graphFor($level, $continues, $flags) . $this->getText($node);
            $visible = TerminalText::slice($line, $this->delta->x, $width);
            $buffer->moveStr(0, $visible, $color);
            $this->writeLine(0, $position - $this->delta->y, $width, 1, $buffer);
            $lastDrawn = $position - $this->delta->y;

            return false;
        });

        $blank = new DrawBuffer($width);
        $blank->moveChar(0, ' ', $normal, $width);
        for ($row = $lastDrawn + 1; $row < $height; $row++) {
            $this->writeLine(0, $row, $width, 1, $blank);
        }
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->isNothing()) {
            return;
        }

        if ($event->what === EventType::MouseDown || $event->what === EventType::MouseMove || $event->what === EventType::MouseUp) {
            $this->handleMouse($event);

            return;
        }
        if ($event->what === EventType::KeyDown) {
            $this->handleKey($event);
        }
    }

    private function handleMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        if ($event->what === EventType::MouseMove && ! $this->getState(State::Dragging)) {
            return;
        }
        if ($event->what === EventType::MouseDown) {
            $this->setState(State::Dragging, true);
        }
        if ($event->what === EventType::MouseUp) {
            $this->setState(State::Dragging, false);
        }

        $local = $this->makeLocal($mouse->where);
        if ($local->y < 0 || $local->y >= $this->bounds->height()) {
            return;
        }
        $position = $this->delta->y + $local->y;
        $node = $this->getNode($position);
        if ($node === null) {
            return;
        }
        $changed = $position !== $this->focused;
        $this->adjustFocus($position);

        if ($event->what === EventType::MouseDown && ! $mouse->doubleClick) {
            $meta = $this->metadataAt($position);
            if ($meta !== null && $local->x < TerminalText::length($this->graphFor($meta['level'], $meta['continues'], $meta['flags']))) {
                $this->toggle($node);
            }
        }
        if ($mouse->doubleClick && $event->what === EventType::MouseDown) {
            $this->selected($this->focused);
        }
        if ($changed || $event->what !== EventType::MouseMove) {
            $this->drawView();
        }
        $this->clearEvent($event);
    }

    private function handleKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        $newFocus = $this->focused;
        $height = max(1, $this->bounds->height());
        $handled = true;
        // Accept both identities: real terminals deliver the folded combined code
        // (Key::CtrlPageUp/CtrlPageDown via EscapeDecoder::legacyKeyCode), while
        // hand-built events carry the base code plus a Ctrl modifier bit.
        if (
            ($key->modifiers & KeyModifier::Ctrl) !== 0 && $key->keyCode === Key::PageUp->value
            || $key->keyCode === Key::CtrlPageUp->value
        ) {
            $newFocus = 0;
        } elseif (
            ($key->modifiers & KeyModifier::Ctrl) !== 0 && $key->keyCode === Key::PageDown->value
            || $key->keyCode === Key::CtrlPageDown->value
        ) {
            $newFocus = max(0, $this->limit->y - 1);
        } else {
            switch ($key->keyCode) {
                case Key::Up->value:
                case Key::Left->value:
                    $newFocus--;
                    break;
                case Key::Down->value:
                case Key::Right->value:
                    $newFocus++;
                    break;
                case Key::PageUp->value:
                    $newFocus -= $height - 1;
                    break;
                case Key::PageDown->value:
                    $newFocus += $height - 1;
                    break;
                case Key::Home->value:
                    $newFocus = $this->delta->y;
                    break;
                case Key::End->value:
                    $newFocus = $this->delta->y + $height - 1;
                    break;
                case Key::Enter->value:
                    $this->selected($newFocus);
                    break;
                default:
                    $handled = $this->handleOutlineCharacter($key->char, $newFocus);
                    break;
            }
        }
        if (! $handled) {
            return;
        }
        $this->adjustFocus($newFocus);
        $this->drawView();
        $this->clearEvent($event);
    }

    private function handleOutlineCharacter(string $char, int $position): bool
    {
        $node = $this->getNode($position);
        if ($node === null) {
            return false;
        }
        if ($char === '*') {
            $this->expandAll($node);
            $this->update();

            return true;
        }
        if ($char === '+' || $char === '-') {
            if ($this->hasChildren($node)) {
                $this->adjust($node, $char === '+');
                $this->update();
            }

            return true;
        }

        return false;
    }

    private function toggle(Node $node): void
    {
        if (! $this->hasChildren($node)) {
            return;
        }
        $this->adjust($node, ! $this->isExpanded($node));
        $this->update();
    }

    private function adjustFocus(int $position): void
    {
        if ($this->limit->y <= 0) {
            $this->focused(0);

            return;
        }
        $position = IntMath::clamp($position, 0, $this->limit->y - 1);
        if ($position !== $this->focused) {
            $this->focused($position);
        }
        if ($position < $this->delta->y) {
            $this->scrollVerticallyTo($position);
        } elseif ($position >= $this->delta->y + max(1, $this->bounds->height())) {
            $this->scrollVerticallyTo($position - $this->bounds->height() + 1);
        }
    }

    /** TScroller's bars own delta; retain useful keyboard scrolling without a bar. */
    private function scrollVerticallyTo(int $y): void
    {
        if ($this->getVScrollBar() !== null) {
            $this->scrollTo($this->delta->x, $y);

            return;
        }
        $max = max(0, $this->limit->y - $this->bounds->height());
        $y = IntMath::clamp($y, 0, $max);
        if ($y !== $this->delta->y) {
            $this->delta = new Point($this->delta->x, $y);
        }
    }

    /** @return array{level:int,continues:list<bool>,flags:int}|null */
    private function metadataAt(int $target): ?array
    {
        $metadata = null;
        $this->walkVisible(function (self $_viewer, Node $_node, int $level, int $position, array $continues, int $flags) use ($target, &$metadata): bool {
            if ($position !== $target) {
                return false;
            }
            $metadata = compact('level', 'continues', 'flags');

            return true;
        });

        return $metadata;
    }

    /** @param list<bool> $continues */
    private function graphFor(int $level, array $continues, int $flags): string
    {
        // Use the virtual method so specialized viewers can replace the graph style.
        return $this->getGraph($level, $this->lineBits($continues), $flags);
    }

    /** @return list<bool> */
    private function continuationsFor(int $level, int $lines): array
    {
        $continues = [];
        for ($i = 0; $i < $level; $i++) {
            $continues[] = $i < PHP_INT_SIZE * 8 - 1 && (($lines & (1 << $i)) !== 0);
        }

        return $continues;
    }

    /** @param list<bool> $continues */
    private function lineBits(array $continues): int
    {
        $lines = 0;
        foreach ($continues as $level => $continuesAtLevel) {
            if ($continuesAtLevel && $level < PHP_INT_SIZE * 8 - 1) {
                $lines |= 1 << $level;
            }
        }

        return $lines;
    }

    private function expansionGlyph(int $flags): string
    {
        return ($flags & self::Expanded) !== 0 ? '─' : '+';
    }

    /**
     * @param Closure(self, Node, int, int, list<bool>, int): bool $action
     */
    private function walkVisible(Closure $action): ?Node
    {
        $position = -1;
        /** @var SplObjectStorage<Node, null> $seen */
        $seen = new SplObjectStorage;

        return $this->walkNode($this->getRoot(), 0, [], true, $position, $action, $seen);
    }

    /**
     * @param list<bool> $continues
     * @param Closure(self, Node, int, int, list<bool>, int): bool $action
     * @param SplObjectStorage<Node, null> $seen
     */
    private function walkNode(?Node $node, int $level, array $continues, bool $last, int &$position, Closure $action, SplObjectStorage $seen): ?Node
    {
        if ($node === null) {
            return null;
        }
        if ($seen->offsetExists($node)) {
            throw new LogicException('Outline node graph must be a tree; cyclic or reused nodes are not supported.');
        }
        $seen->offsetSet($node, null);
        $hasChildren = $this->hasChildren($node);
        $expanded = ! $hasChildren || $this->isExpanded($node);
        $flags = ($expanded ? self::Expanded : 0) | ($hasChildren && $expanded ? self::Children : 0) | ($last ? self::Last : 0);
        $position++;
        if ($action($this, $node, $level, $position, $continues, $flags)) {
            return $node;
        }
        if (! $hasChildren || ! $expanded) {
            return null;
        }

        $count = max(0, $this->getNumChildren($node));
        $childContinues = $continues;
        $childContinues[] = ! $last;
        for ($i = 0; $i < $count; $i++) {
            $result = $this->walkNode($this->getChild($node, $i), $level + 1, $childContinues, $i === $count - 1, $position, $action, $seen);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
