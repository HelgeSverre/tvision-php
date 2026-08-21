<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Outline;

/**
 * A linked outline node, faithful to Turbo Vision's TNode while retaining PHP
 * ownership semantics. $childList and $next are deliberately public: this makes
 * it cheap to construct and incrementally mutate an outline without adapters.
 */
final class Node
{
    public function __construct(
        public string $text,
        public ?self $childList = null,
        public ?self $next = null,
        public bool $expanded = true,
    ) {}

    /** Build a sibling chain in the supplied display order. */
    public static function siblings(self ...$nodes): ?self
    {
        if ($nodes === []) {
            return null;
        }

        $count = count($nodes);
        for ($index = 0; $index < $count; $index++) {
            $nodes[$index]->next = $nodes[$index + 1] ?? null;
        }

        return $nodes[0];
    }
}
