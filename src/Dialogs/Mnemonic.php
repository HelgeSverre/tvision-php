<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;

/** Shared extraction and key matching for `~m~` dialog-control mnemonics. */
final class Mnemonic
{
    public static function extract(string $text): ?string
    {
        return preg_match('/~(\X)~/u', $text, $matches) === 1 ? $matches[1] : null;
    }

    /** Lowercase mnemonic letter of a "~X~label" string, or '' when absent. */
    public static function hotKey(string $text): string
    {
        return preg_match('/~(.)~/u', $text, $matches) === 1 ? strtolower($matches[1]) : '';
    }

    /** Display length of the text with ~mnemonic~ markers removed. */
    public static function visibleLength(string $text): int
    {
        return TerminalText::length(str_replace('~', '', $text));
    }

    public static function matches(string $text, KeyDownEvent $key): bool
    {
        $mnemonic = self::extract($text);
        if ($mnemonic === null) {
            return false;
        }
        if (($key->modifiers & KeyModifier::Alt) !== 0
            && $key->char !== ''
            && strcasecmp($mnemonic, $key->char) === 0
        ) {
            return true;
        }

        return self::legacyAltKey($mnemonic)?->value === $key->keyCode;
    }

    private static function legacyAltKey(string $mnemonic): ?Key
    {
        return match (strtolower($mnemonic)) {
            'a' => Key::AltA,
            'b' => Key::AltB,
            'c' => Key::AltC,
            'd' => Key::AltD,
            'e' => Key::AltE,
            'f' => Key::AltF,
            'g' => Key::AltG,
            'h' => Key::AltH,
            'i' => Key::AltI,
            'j' => Key::AltJ,
            'k' => Key::AltK,
            'l' => Key::AltL,
            'm' => Key::AltM,
            'n' => Key::AltN,
            'o' => Key::AltO,
            'p' => Key::AltP,
            'q' => Key::AltQ,
            'r' => Key::AltR,
            's' => Key::AltS,
            't' => Key::AltT,
            'u' => Key::AltU,
            'v' => Key::AltV,
            'w' => Key::AltW,
            'x' => Key::AltX,
            'y' => Key::AltY,
            'z' => Key::AltZ,
            default => null,
        };
    }

    private function __construct() {}
}
