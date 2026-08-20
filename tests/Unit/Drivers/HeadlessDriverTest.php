<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;

test('size is fixed and settable; resize latches a flag', function (): void {
    $d = new HeadlessDriver(80, 24);

    expect($d->size())->toBe([80, 24])
        ->and($d->resized())->toBeFalse();

    $d->resizeTo(100, 30);
    expect($d->size())->toBe([100, 30])
        ->and($d->resized())->toBeTrue()
        ->and($d->resized())->toBeFalse(); // flag cleared after read
});

test('output accumulates; output() peeks, takeOutput() drains', function (): void {
    $d = new HeadlessDriver();
    $d->write('abc');
    $d->write('def');

    expect($d->output())->toBe('abcdef')
        ->and($d->output())->toBe('abcdef')   // peek does not drain
        ->and($d->takeOutput())->toBe('abcdef')
        ->and($d->output())->toBe('');         // drained
});

test('fed input is returned by pollInput and then consumed', function (): void {
    $d = new HeadlessDriver();
    $d->feedInput("\e[A");

    expect($d->pollInput(0))->toBe("\e[A")
        ->and($d->pollInput(0))->toBe(''); // queue emptied
});

test('init and shutdown are observable and shutdown is idempotent', function (): void {
    $d = new HeadlessDriver();

    expect($d->isInitialised())->toBeFalse();
    $d->init();
    expect($d->isInitialised())->toBeTrue();
    $d->shutdown();
    $d->shutdown(); // idempotent, no error
    expect($d->isInitialised())->toBeFalse();
});

test('headless dimensions and poll timeouts reject invalid values', function (): void {
    $driver = new HeadlessDriver();

    expect(fn () => new HeadlessDriver(-1, 20))
        ->toThrow(InvalidArgumentException::class, 'non-negative')
        ->and(fn () => $driver->resizeTo(20, -1))
        ->toThrow(InvalidArgumentException::class, 'non-negative')
        ->and(fn () => $driver->pollInput(-1))
        ->toThrow(InvalidArgumentException::class, 'non-negative');
});
