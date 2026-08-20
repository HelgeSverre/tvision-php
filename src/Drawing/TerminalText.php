<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drawing;

/** Utilities for mapping Unicode text onto the framework's one-column cell model. */
final class TerminalText
{
    /** @return list<string> */
    public static function graphemes(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (self::isPrintableAscii($text)) {
            return str_split($text);
        }

        $result = preg_match_all('/\X/u', $text, $matches);
        if ($result === false) {
            return ['?'];
        }

        return $matches[0];
    }

    public static function length(string $text): int
    {
        if (self::isPrintableAscii($text)) {
            return strlen($text);
        }

        return count(self::graphemes($text));
    }

    public static function slice(string $text, int $offset, ?int $length = null): string
    {
        if (self::isPrintableAscii($text)) {
            return $length === null ? substr($text, $offset) : substr($text, $offset, $length);
        }

        return implode('', array_slice(self::graphemes($text), $offset, $length));
    }

    /**
     * Return a safe, exactly one-column cell glyph. Double-width, zero-width,
     * control, malformed, and emoji-presentation glyphs use a visible fallback.
     */
    public static function cellGlyph(string $value): string
    {
        if (strlen($value) === 1) {
            $ord = ord($value);

            return $ord >= 0x20 && $ord <= 0x7E ? $value : '?';
        }

        $graphemes = self::graphemes($value);
        if (count($graphemes) !== 1) {
            return '?';
        }

        $glyph = $graphemes[0];
        if (preg_match('/[\p{Cc}\p{Cf}\p{Cs}\x{FE0F}]/u', $glyph) !== 0) {
            return '?';
        }

        $base = preg_replace('/\p{M}/u', '', $glyph);
        if ($base === null || $base === '' || mb_strwidth($base, 'UTF-8') !== 1) {
            return '?';
        }

        return $glyph;
    }

    private static function isPrintableAscii(string $text): bool
    {
        return preg_match('/^[\x20-\x7E]*$/D', $text) === 1;
    }
}
