<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Support;

/**
 * Integer arithmetic for coordinates and widget state that must never promote to
 * float. Values outside PHP's integer domain saturate at the nearest endpoint.
 *
 * @internal
 */
final class IntMath
{
    public static function add(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            return PHP_INT_MAX;
        }
        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            return PHP_INT_MIN;
        }

        return $left + $right;
    }

    public static function subtract(int $left, int $right): int
    {
        if ($right > 0 && $left < PHP_INT_MIN + $right) {
            return PHP_INT_MIN;
        }
        if ($right < 0 && $left > PHP_INT_MAX + $right) {
            return PHP_INT_MAX;
        }

        return $left - $right;
    }

    public static function multiply(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        if ($left > 0) {
            if ($right > 0 && $left > intdiv(PHP_INT_MAX, $right)) {
                return PHP_INT_MAX;
            }
            if ($right < 0 && $right < intdiv(PHP_INT_MIN, $left)) {
                return PHP_INT_MIN;
            }
        } else {
            if ($right > 0 && $left < intdiv(PHP_INT_MIN, $right)) {
                return PHP_INT_MIN;
            }
            if ($right < 0 && $left < intdiv(PHP_INT_MAX, $right)) {
                return PHP_INT_MAX;
            }
        }

        return $left * $right;
    }

    private function __construct() {}
}
