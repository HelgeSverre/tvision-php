<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Terminal\TerminalCapabilities;

test('unknown terminals use conservative protocol fallbacks', function (): void {
    $capabilities = TerminalCapabilities::detect(['TERM' => 'xterm-256color']);

    expect($capabilities->synchronizedUpdates)->toBeFalse()
        ->and($capabilities->kittyKeyboard)->toBeFalse();
});

test('known modern terminals enable only protocols they advertise', function (): void {
    $kitty = TerminalCapabilities::detect(['TERM' => 'xterm-kitty', 'KITTY_WINDOW_ID' => '1']);
    $ghostty = TerminalCapabilities::detect(['TERM' => 'xterm-ghostty', 'TERM_PROGRAM' => 'ghostty']);

    expect($kitty->synchronizedUpdates)->toBeTrue()
        ->and($kitty->kittyKeyboard)->toBeTrue()
        ->and($ghostty->synchronizedUpdates)->toBeTrue()
        ->and($ghostty->kittyKeyboard)->toBeTrue();
});

test('multiplexers default to safe fallbacks and explicit overrides win', function (): void {
    $multiplexed = TerminalCapabilities::detect([
        'TERM' => 'screen-256color',
        'KITTY_WINDOW_ID' => '1',
    ]);
    $overridden = TerminalCapabilities::detect([
        'TERM' => 'screen-256color',
        'KITTY_WINDOW_ID' => '1',
        'TVISION_SYNC_UPDATE' => 'yes',
        'TVISION_KITTY_KEYBOARD' => '1',
    ]);

    expect($multiplexed->synchronizedUpdates)->toBeFalse()
        ->and($multiplexed->kittyKeyboard)->toBeFalse()
        ->and($overridden->synchronizedUpdates)->toBeTrue()
        ->and($overridden->kittyKeyboard)->toBeTrue();
});
