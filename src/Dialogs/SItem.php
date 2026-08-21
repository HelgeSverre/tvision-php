<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** A small, compatibility-friendly linked string list (Turbo Vision's TSItem). */
final readonly class SItem
{
    public function __construct(public string $value, public ?self $next = null) {}

    /** @return list<string> */
    public function values(): array
    {
        $values = [];
        for ($item = $this; $item !== null; $item = $item->next) {
            $values[] = $item->value;
        }

        return $values;
    }

    public static function list(string ...$values): ?self
    {
        $head = null;
        for ($i = count($values) - 1; $i >= 0; $i--) {
            $head = new self($values[$i], $head);
        }

        return $head;
    }
}
