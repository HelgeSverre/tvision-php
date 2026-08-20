<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Terminal;

/**
 * Conservatively detected optional terminal protocols. Every feature can be
 * explicitly forced with a TVISION_* environment override; unknown terminals use
 * the legacy-safe path rather than receiving speculative control sequences.
 */
final readonly class TerminalCapabilities
{
    public function __construct(
        public bool $synchronizedUpdates = false,
        public bool $kittyKeyboard = false,
    ) {}

    /** @param array<string, string> $environment */
    public static function detect(array $environment): self
    {
        $term = strtolower($environment['TERM'] ?? '');
        $program = strtolower($environment['TERM_PROGRAM'] ?? '');
        $multiplexed = str_starts_with($term, 'screen') || str_starts_with($term, 'tmux');
        $kitty = ($environment['KITTY_WINDOW_ID'] ?? '') !== ''
            || $program === 'kitty'
            || str_contains($term, 'kitty');
        $kittyKeyboard = $kitty || $program === 'ghostty';
        $knownSynchronized = $kitty
            || $program === 'ghostty'
            || $program === 'contour'
            || str_contains($term, 'contour');

        return new self(
            synchronizedUpdates: self::override(
                $environment['TVISION_SYNC_UPDATE'] ?? null,
                $knownSynchronized && ! $multiplexed,
            ),
            kittyKeyboard: self::override(
                $environment['TVISION_KITTY_KEYBOARD'] ?? null,
                $kittyKeyboard && ! $multiplexed,
            ),
        );
    }

    /** Read the real process environment without making tests mutate globals. */
    public static function detectProcess(): self
    {
        $environment = [];
        foreach (['TERM', 'TERM_PROGRAM', 'KITTY_WINDOW_ID', 'TVISION_SYNC_UPDATE', 'TVISION_KITTY_KEYBOARD'] as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $environment[$name] = $value;
            }
        }

        return self::detect($environment);
    }

    private static function override(?string $value, bool $detected): bool
    {
        return match (strtolower(trim($value ?? ''))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $detected,
        };
    }
}
