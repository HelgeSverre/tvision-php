<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Support\IntMath;

test('integer math preserves ordinary arithmetic', function (): void {
    expect(IntMath::add(20, 22))->toBe(42)
        ->and(IntMath::subtract(20, 22))->toBe(-2)
        ->and(IntMath::multiply(-7, 6))->toBe(-42)
        ->and(IntMath::multiply(-7, -6))->toBe(42);
});

test('integer math saturates every overflow direction', function (): void {
    expect(IntMath::add(PHP_INT_MAX, 1))->toBe(PHP_INT_MAX)
        ->and(IntMath::add(PHP_INT_MIN, -1))->toBe(PHP_INT_MIN)
        ->and(IntMath::subtract(PHP_INT_MIN, 1))->toBe(PHP_INT_MIN)
        ->and(IntMath::subtract(PHP_INT_MAX, -1))->toBe(PHP_INT_MAX)
        ->and(IntMath::multiply(PHP_INT_MAX, 2))->toBe(PHP_INT_MAX)
        ->and(IntMath::multiply(PHP_INT_MIN, 2))->toBe(PHP_INT_MIN)
        ->and(IntMath::multiply(PHP_INT_MIN, -1))->toBe(PHP_INT_MAX)
        ->and(IntMath::multiply(-2, PHP_INT_MIN))->toBe(PHP_INT_MAX);
});
