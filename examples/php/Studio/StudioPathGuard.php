<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

/** Resolves project/export aliases before either atomic writer can replace a target. */
final class StudioPathGuard
{
    public static function sameTarget(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);
        if ($leftReal !== false && $rightReal !== false) {
            if ($leftReal === $rightReal) {
                return true;
            }

            $leftStat = @stat($leftReal);
            $rightStat = @stat($rightReal);

            return $leftStat !== false
                && $rightStat !== false
                && $leftStat['dev'] === $rightStat['dev']
                && $leftStat['ino'] === $rightStat['ino'];
        }

        return self::normalized($left) === self::normalized($right);
    }

    private static function normalized(string $path): string
    {
        $directory = realpath(dirname($path));

        return ($directory !== false ? $directory : dirname($path)) . DIRECTORY_SEPARATOR . basename($path);
    }
}
