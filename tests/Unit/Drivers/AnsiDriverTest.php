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
        ->and(AnsiDriver::parseSize('terminal says 24 80'))->toBe([80, 24])
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

test('write reports a closed output stream as a driver failure', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '80 24');
    fclose($out);

    expect(fn () => $driver->write('frame'))
        ->toThrow(DriverException::class, 'terminal output');
});

test('write survives repeated EINTR and transient zero-byte stalls', function (): void {
    assert(stream_wrapper_register('tv-interrupted-write', InterruptedWriteStream::class));
    InterruptedWriteStream::$interruptsRemaining = 32;
    InterruptedWriteStream::$silentStallsRemaining = 32;
    InterruptedWriteStream::$written = '';
    [$input] = memoryStreams();
    $output = fopen('tv-interrupted-write://terminal', 'wb');
    assert($output !== false);

    try {
        $driver = new AnsiDriver($input, $output, fn (string $cmd): string => '24 80');
        $driver->write('complete frame');

        expect(InterruptedWriteStream::$written)->toBe('complete frame');
    } finally {
        fclose($input);
        fclose($output);
        stream_wrapper_unregister('tv-interrupted-write');
    }
});

test('write still fails within a bounded interval when output never makes progress', function (): void {
    assert(stream_wrapper_register('tv-stalled-write', InterruptedWriteStream::class));
    InterruptedWriteStream::$interruptsRemaining = 0;
    InterruptedWriteStream::$silentStallsRemaining = 1_000;
    [$input] = memoryStreams();
    $output = fopen('tv-stalled-write://terminal', 'wb');
    assert($output !== false);

    try {
        $driver = new AnsiDriver($input, $output, fn (string $cmd): string => '24 80');

        expect(fn () => $driver->write('blocked frame'))
            ->toThrow(DriverException::class, 'terminal output');
    } finally {
        fclose($input);
        fclose($output);
        stream_wrapper_unregister('tv-stalled-write');
    }
});

test('write bounds an endless sequence of signal interruptions', function (): void {
    assert(stream_wrapper_register('tv-endless-eintr-write', InterruptedWriteStream::class));
    InterruptedWriteStream::$interruptsRemaining = 1_000;
    InterruptedWriteStream::$silentStallsRemaining = 0;
    [$input] = memoryStreams();
    $output = fopen('tv-endless-eintr-write://terminal', 'wb');
    assert($output !== false);

    try {
        $driver = new AnsiDriver($input, $output, fn (string $cmd): string => '24 80');

        expect(fn () => $driver->write('interrupted frame'))
            ->toThrow(DriverException::class, 'terminal output');
    } finally {
        fclose($input);
        fclose($output);
        stream_wrapper_unregister('tv-endless-eintr-write');
    }
});

test('pollInput validates timeouts and reports a closed input stream', function (): void {
    [$in, $out] = memoryStreams();
    $driver = new AnsiDriver($in, $out, fn (string $cmd): string => '24 80');

    expect(fn () => $driver->pollInput(-1))
        ->toThrow(InvalidArgumentException::class, 'non-negative');

    fclose($in);
    expect(fn () => $driver->pollInput(0))
        ->toThrow(DriverException::class, 'terminal input');
});

test('pollInput reports end-of-file instead of turning a disconnected terminal into a busy loop', function (): void {
    $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    assert($streams !== false);
    [$input, $peer] = $streams;
    $output = fopen('php://memory', 'r+b');
    assert($output !== false);
    fclose($peer);

    try {
        $driver = new AnsiDriver($input, $output, fn (string $cmd): string => '24 80');

        expect(fn () => $driver->pollInput(100))
            ->toThrow(DriverException::class, 'terminal input');
    } finally {
        fclose($input);
        fclose($output);
    }
});

test('pollInput treats a signal-interrupted wait as transient', function (): void {
    $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $controlStreams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    assert($streams !== false);
    assert($controlStreams !== false);
    [$input, $peer] = $streams;
    [$parentControl, $childControl] = $controlStreams;
    $output = fopen('php://memory', 'r+b');
    assert($output !== false);

    $previousHandler = pcntl_signal_get_handler(SIGWINCH);
    pcntl_signal(SIGWINCH, static function (): void {}, false);
    $parentPid = posix_getpid();
    $childPid = pcntl_fork();
    assert($childPid !== -1);

    if ($childPid === 0) {
        fread($childControl, 1);
        usleep(10_000);
        posix_kill($parentPid, SIGWINCH);
        exit(0);
    }

    try {
        $driver = new AnsiDriver($input, $output, fn (string $cmd): string => '24 80');

        fwrite($parentControl, '1');
        expect($driver->pollInput(1000))->toBe('');
    } finally {
        pcntl_waitpid($childPid, $status);
        pcntl_signal_dispatch();
        pcntl_signal(SIGWINCH, $previousHandler);
        fclose($input);
        fclose($peer);
        fclose($parentControl);
        fclose($childControl);
        fclose($output);
    }
})->skip(
    ! function_exists('pcntl_fork')
        || ! function_exists('pcntl_signal')
        || ! function_exists('pcntl_signal_get_handler')
        || ! function_exists('pcntl_waitpid')
        || ! function_exists('posix_getpid')
        || ! function_exists('posix_kill')
        || ! defined('SIGWINCH'),
    'Requires pcntl, posix, and SIGWINCH.',
);

test('terminal size polling detects resizes when pcntl is unavailable', function (): void {
    [$in, $out] = memoryStreams();
    $rawSize = '24 80';
    $now = 1.0;
    $driver = new AnsiDriver(
        $in,
        $out,
        static function (string $cmd) use (&$rawSize): string {
            return $rawSize;
        },
        clock: static function () use (&$now): float {
            return $now;
        },
        signalSupport: false,
    );

    expect($driver->size())->toBe([80, 24])
        ->and($driver->resized())->toBeFalse();

    $rawSize = '30 100';
    $now += 0.3;

    expect($driver->resized())->toBeTrue()
        ->and($driver->size())->toBe([100, 30])
        ->and($driver->resized())->toBeFalse();
});

final class InterruptedWriteStream
{
    /** @var resource|null */
    public $context;

    public static int $interruptsRemaining = 0;

    public static int $silentStallsRemaining = 0;

    public static string $written = '';

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        if (self::$interruptsRemaining > 0) {
            self::$interruptsRemaining--;
            trigger_error('fwrite(): Write failed with errno=4 Interrupted system call', E_USER_WARNING);

            return 0;
        }
        if (self::$silentStallsRemaining > 0) {
            self::$silentStallsRemaining--;

            return 0;
        }

        self::$written .= $data;

        return strlen($data);
    }
}
