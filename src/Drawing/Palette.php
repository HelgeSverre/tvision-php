<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/**
 * Maps a view's logical color index to an attribute byte. Faithful to Turbo
 * Vision palettes, which are 1-indexed strings of attribute bytes.
 */
final readonly class Palette
{
    private const int FALLBACK = 0x07;

    /** @param array<int,int> $entries 1-based index => attribute byte */
    public function __construct(private array $entries) {}

    public static function fromBytes(string $bytes): self
    {
        $entries = [];
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $entries[$i + 1] = ord($bytes[$i]);
        }

        return new self($entries);
    }

    public function get(int $index): int
    {
        return $this->entries[$index] ?? self::FALLBACK;
    }

    public function size(): int
    {
        return count($this->entries);
    }
}
