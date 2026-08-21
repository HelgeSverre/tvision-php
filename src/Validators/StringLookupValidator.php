<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/** Exact-match lookup validator backed by an immutable, sorted string list. */
final class StringLookupValidator extends LookupValidator
{
    /** @var list<string> */
    private array $strings = [];

    /** @param iterable<string> $strings */
    public function __construct(iterable $strings = [])
    {
        $this->newStringList($strings);
    }

    /** @param iterable<string> $strings */
    public function newStringList(iterable $strings): void
    {
        $items = [];
        foreach ($strings as $string) {
            $items[] = (string) $string;
        }
        sort($items, SORT_STRING);
        $this->strings = array_values(array_unique($items, SORT_STRING));
    }

    /** @return list<string> */
    public function strings(): array
    {
        return $this->strings;
    }

    public function lookup(string $input): bool
    {
        $low = 0;
        $high = count($this->strings) - 1;
        while ($low <= $high) {
            $middle = $low + intdiv($high - $low, 2);
            $comparison = strcmp($input, $this->strings[$middle]);
            if ($comparison === 0) {
                return true;
            }
            if ($comparison < 0) {
                $high = $middle - 1;
            } else {
                $low = $middle + 1;
            }
        }

        return false;
    }

    public function error(): void
    {
        $this->setError('Input is not in the list of valid strings.');
    }
}
