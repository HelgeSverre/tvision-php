<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Support;

/**
 * Process-wide text clipboard shared by every editable view, so a copy in one
 * control (Editor, InputLine, Memo, …) is what the next paste delivers instead
 * of stale control-local state. Global application state, faithful to Turbo
 * Vision's single clipboard.
 */
final class Clipboard
{
    private static string $text = '';

    public static function get(): string
    {
        return self::$text;
    }

    public static function set(string $text): void
    {
        self::$text = $text;
    }

    private function __construct() {}
}
