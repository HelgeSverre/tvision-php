<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Outline;

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use LogicException;
use SplObjectStorage;

/** A linked-node outline implementation, faithful to Turbo Vision's TOutline. */
final class Outline extends OutlineViewer
{
    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar = null,
        ?ScrollBar $vScrollBar = null,
        public ?Node $root = null,
    ) {
        parent::__construct($bounds, $hScrollBar, $vScrollBar);
        $this->update();
    }

    public function adjust(Node $node, bool $expand): void
    {
        $node->expanded = $expand;
    }

    public function getRoot(): ?Node
    {
        return $this->root;
    }

    public function getNumChildren(Node $node): int
    {
        $count = 0;
        /** @var SplObjectStorage<Node, null> $siblings */
        $siblings = new SplObjectStorage;
        for ($child = $node->childList; $child !== null; $child = $child->next) {
            if ($siblings->offsetExists($child)) {
                throw new LogicException('Outline sibling list contains a cycle.');
            }
            $siblings->offsetSet($child, null);
            $count++;
        }

        return $count;
    }

    public function getChild(Node $node, int $index): ?Node
    {
        if ($index < 0) {
            return null;
        }
        $child = $node->childList;
        /** @var SplObjectStorage<Node, null> $siblings */
        $siblings = new SplObjectStorage;
        while ($child !== null && $index-- > 0) {
            if ($siblings->offsetExists($child)) {
                throw new LogicException('Outline sibling list contains a cycle.');
            }
            $siblings->offsetSet($child, null);
            $child = $child->next;
        }

        return $child;
    }

    public function getText(Node $node): string
    {
        return $node->text;
    }

    public function isExpanded(Node $node): bool
    {
        return $node->expanded;
    }

    public function hasChildren(Node $node): bool
    {
        return $node->childList !== null;
    }
}
