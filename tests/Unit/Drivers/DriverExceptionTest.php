<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Exceptions\DriverException;
use HelgeSverre\TurboVision\Exceptions\InputClosedException;
use HelgeSverre\TurboVision\Exceptions\TurboVisionException;

test('DriverException is a TurboVisionException', function (): void {
    expect(new DriverException('x'))->toBeInstanceOf(TurboVisionException::class);
});

test('named constructors describe the failure mode', function (): void {
    expect(DriverException::notATty()->getMessage())
        ->toContain('TTY')
        ->and(DriverException::sttyUnavailable()->getMessage())
        ->toContain('stty');
});

test('write failures have a driver-specific exception', function (): void {
    expect(DriverException::writeFailed()->getMessage())->toContain('terminal output')
        ->and(DriverException::readFailed()->getMessage())->toContain('terminal input');
});

test('closed input has a distinct catchable driver exception', function (): void {
    expect(DriverException::inputClosed())
        ->toBeInstanceOf(InputClosedException::class)
        ->toBeInstanceOf(DriverException::class)
        ->and(DriverException::inputClosed()->getMessage())->toContain('closed');
});
