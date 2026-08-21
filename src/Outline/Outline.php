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
        self::assertSiblingListAcyclic($node->childList);

        $count = 0;
        for ($child = $node->childList; $child !== null; $child = $child->next) {
            $count++;
        }

        return $count;
    }

    public function getChild(Node $node, int $index): ?Node
    {
        if ($index < 0) {
            return null;
        }
        self::assertSiblingListAcyclic($node->childList);

        $child = $node->childList;
        while ($child !== null && $index-- > 0) {
            $child = $child->next;
        }

        return $child;
    }

    /**
     * Cycle guard for the sibling chain, allocation-free (Floyd's tortoise and
     * hare). This detects cycles WITHIN one sibling list; the walker in
     * OutlineViewer separately rejects node reuse ACROSS parents (a DAG), which
     * a per-list check cannot see. Two distinct invariants, two mechanisms.
     */
    private static function assertSiblingListAcyclic(?Node $start): void
    {
        $tortoise = $start;
        $hare = $start?->next;
        while ($hare !== null) {
            if ($tortoise === $hare) {
                throw new LogicException('Outline sibling list contains a cycle.');
            }
            $tortoise = $tortoise?->next;
            $hare = $hare->next?->next;
        }
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
