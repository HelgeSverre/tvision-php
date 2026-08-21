<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views\Window;

/** wp* palette indices + the three window palette byte strings (verbatim from views.h). */
final class WindowPalette
{
    public const int Blue = 0;
    public const int Cyan = 1;
    public const int Gray = 2;

    public const string BLUE_WINDOW = "\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F";
    public const string CYAN_WINDOW = "\x10\x11\x12\x13\x14\x15\x16\x17";
    public const string GRAY_WINDOW = "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

    /** The palette byte string for a wp* index (defaults to Blue). */
    public static function byteFor(int $index): string
    {
        return match (self::normalize($index)) {
            self::Cyan => self::CYAN_WINDOW,
            self::Gray => self::GRAY_WINDOW,
            default => self::BLUE_WINDOW,
        };
    }

    /** Normalize untrusted palette values to Turbo Vision's default blue window. */
    public static function normalize(int $index): int
    {
        return match ($index) {
            self::Cyan, self::Gray => $index,
            default => self::Blue,
        };
    }
}
