<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Resources;

/** Mutable builder for an immutable StringList. */
final class StringListMaker
{
    /** @var list<string> */
    private array $strings = [];

    public function add(string $string): self
    {
        $this->strings[] = $string;

        return $this;
    }

    /** @param iterable<string> $strings */
    public function addMany(iterable $strings): self
    {
        foreach ($strings as $string) {
            $this->add($string);
        }

        return $this;
    }

    public function clear(): self
    {
        $this->strings = [];

        return $this;
    }

    public function build(): StringList
    {
        return new StringList($this->strings);
    }
}
