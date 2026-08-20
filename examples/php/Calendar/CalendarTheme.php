<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use InvalidArgumentException;

/** Semantic CGA/ANSI attributes used by the calendar demo. */
final readonly class CalendarTheme
{
    /** @param list<int> $eventColors */
    public function __construct(
        public int $canvas = 0x07,
        public int $primary = 0x0F,
        public int $muted = 0x08,
        public int $grid = 0x08,
        public int $accent = 0x0B,
        public int $selection = 0x0B,
        public int $weekend = 0x03,
        public int $status = 0x0B,
        public int $error = 0x0C,
        public int $shadow = 0x08,
        public array $eventColors = [0x0B, 0x0A, 0x0D, 0x0E, 0x09, 0x0C],
    ) {
        $attributes = [
            $this->canvas,
            $this->primary,
            $this->muted,
            $this->grid,
            $this->accent,
            $this->selection,
            $this->weekend,
            $this->status,
            $this->error,
            $this->shadow,
            ...$this->eventColors,
        ];
        if ($this->eventColors === [] || array_any($attributes, static fn (int $attr): bool => $attr < 0 || $attr > 0xFF)) {
            throw new InvalidArgumentException('Calendar theme attributes must be bytes and eventColors cannot be empty.');
        }
    }

    public static function modernDark(): self
    {
        return new self();
    }

    public function eventColor(string $calendar): int
    {
        $colorCount = count($this->eventColors);
        $bucket = 0;
        $hash = hash('crc32b', $calendar, true);
        for ($offset = 0; $offset < strlen($hash); $offset++) {
            $bucket = ($bucket * 256 + ord($hash[$offset])) % $colorCount;
        }

        return $this->eventColors[$bucket];
    }
}
