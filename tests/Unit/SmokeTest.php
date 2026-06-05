<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Exceptions\TurboVisionException;

test('autoloading and test harness work', function (): void {
    $e = new TurboVisionException('hello');

    expect($e)->toBeInstanceOf(RuntimeException::class)
        ->and($e->getMessage())->toBe('hello');
});
