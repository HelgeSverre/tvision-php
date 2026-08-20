<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Exceptions\TurboVisionException;

test('autoloading and test harness work', function (): void {
    $e = new TurboVisionException('hello');

    expect($e)->toBeInstanceOf(RuntimeException::class)
        ->and($e->getMessage())->toBe('hello');
});

test('the BIOS example namespace is available without dev autoloading', function (): void {
    $composer = json_decode(
        (string) file_get_contents(__DIR__ . '/../../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    if (! is_array($composer)) {
        throw new UnexpectedValueException('composer.json must decode to an object');
    }
    $autoload = $composer['autoload'] ?? null;
    if (! is_array($autoload)) {
        throw new UnexpectedValueException('composer.json must define autoload');
    }
    $psr4 = $autoload['psr-4'] ?? null;
    if (! is_array($psr4)) {
        throw new UnexpectedValueException('composer.json must define PSR-4 autoloading');
    }

    expect($psr4['HelgeSverre\\TurboVision\\Examples\\'] ?? null)
        ->toBe('examples/php/');
});
