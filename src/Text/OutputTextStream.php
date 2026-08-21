<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Text;

/**
 * Small PHP-native equivalent of Turbo Vision's otstream. It deliberately is
 * not a global PHP stream wrapper: callers can pass it to log adapters that
 * accept a write callback, and retain deterministic ownership/lifecycle.
 */
final readonly class OutputTextStream
{
    public function __construct(private Terminal $terminal) {}

    public function write(string $text): int
    {
        return $this->terminal->write($text);
    }

    public function writeln(string $text = ''): int
    {
        return $this->write($text . "\n");
    }

    public function printf(string $format, string|int|float|bool|null ...$values): int
    {
        return $this->write(sprintf($format, ...$values));
    }

    public function flush(): void
    {
        $this->terminal->flush();
    }

    /** Enables `$stream('message')` where a write callback is wanted. */
    public function __invoke(string $text): int
    {
        return $this->write($text);
    }
}
