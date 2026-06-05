<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Exceptions\DriverException;

/**
 * A non-TTY stream pair: in-memory handles so posix_isatty() returns false.
 *
 * @return array{0:resource,1:resource}
 */
function memoryStreams(): array
{
    $in = fopen('php://memory', 'r+b');
    $out = fopen('php://memory', 'r+b');
    assert($in !== false && $out !== false);

    return [$in, $out];
}

test('init throws DriverException when STDIN/STDOUT is not a TTY', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');

    expect(fn () => $driver->init())->toThrow(DriverException::class);
});

test('init does not write anything when it fails the TTY check', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');

    try {
        $driver->init();
    } catch (DriverException) {
        // expected
    }

    rewind($out);
    expect(stream_get_contents($out))->toBe(''); // terminal state untouched
});

test('parseSize converts "rows cols" to [cols, rows]', function (): void {
    expect(AnsiDriver::parseSize("24 80\n"))->toBe([80, 24])
        ->and(AnsiDriver::parseSize('30 100'))->toBe([100, 30]);
});

test('parseSize falls back to 80x24 on garbage', function (): void {
    expect(AnsiDriver::parseSize(''))->toBe([80, 24])
        ->and(AnsiDriver::parseSize('not a size'))->toBe([80, 24])
        ->and(AnsiDriver::parseSize('0 0'))->toBe([80, 24]);
});

test('shutdown before init, and twice, is a harmless no-op', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');
    unset($out); // not used in this test

    $driver->shutdown();
    $driver->shutdown();

    expect(true)->toBeTrue(); // reached here without error
});
