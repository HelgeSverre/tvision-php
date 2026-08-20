<?php

declare(strict_types=1);

/*
 * Real-terminal integration tests: run an example through an actual PTY and inspect
 * the bytes the AnsiDriver emits and how the process exits. This is the coverage the
 * back-buffer/headless tests structurally lacked — it exercises raw mode, the real
 * fwrite() path (which can partial-write and truncate a frame), synchronized output,
 * and signal/teardown handling. It would have caught the "black desktop / wedged
 * terminal" partial-write bug.
 *
 * Opt-in: `vendor/bin/pest --group=integration`. Skipped when the platform has no
 * usable PTY (so normal CI is unaffected).
 */

/**
 * Run an example in a PTY, send $quit after the first frame, drain continuously,
 * and return what was emitted plus whether it exited on its own.
 *
 * @return array{supported:bool, bytes:int, out:string, exited:bool}
 */
function tvRunInPty(string $examplePath, string $quit): array
{
    if (! function_exists('proc_open')) {
        return ['supported' => false, 'bytes' => 0, 'out' => '', 'exited' => false];
    }

    $desc = [0 => ['pty'], 1 => ['pty'], 2 => ['file', '/dev/null', 'w']];
    $pipes = [];
    $proc = @proc_open(['php', $examplePath], $desc, $pipes, dirname($examplePath));
    if (! is_resource($proc)) {
        return ['supported' => false, 'bytes' => 0, 'out' => '', 'exited' => false];
    }

    stream_set_blocking($pipes[1], false);
    $out = '';
    $start = microtime(true);
    $sent = false;

    while (microtime(true) - $start < 2.5) {
        $chunk = fread($pipes[1], 65536);
        if ($chunk !== '' && $chunk !== false) {
            $out .= $chunk;
        } else {
            usleep(300);
        }
        if (! $sent && microtime(true) - $start > 0.6) {
            fwrite($pipes[0], $quit);
            $sent = true;
        }
        if (! proc_get_status($proc)['running']) {
            break;
        }
    }
    $out .= (string) stream_get_contents($pipes[1]);

    $status = proc_get_status($proc);
    if ($status['running']) {
        proc_terminate($proc);
    }
    proc_close($proc);

    return ['supported' => true, 'bytes' => strlen($out), 'out' => $out, 'exited' => ! $status['running']];
}

test('Guide03 emits a complete colour frame and restores the terminal on quit', function (): void {
    $result = tvRunInPty(__DIR__ . '/../../examples/php/tutorial/Guide03.php', "\ex"); // Alt-X

    if (! $result['supported']) {
        $this->markTestSkipped('No usable PTY on this platform.');
    }

    $out = $result['out'];

    // The whole frame reaches the terminal (not just the menu bar) — the partial-write bug.
    expect($result['bytes'])->toBeGreaterThan(4000)
        ->and(substr_count($out, "\e[0;90m"))->toBeGreaterThan(10)           // graphite on terminal-default background
        ->and(substr_count($out, "\e[?2026h"))->toBe(substr_count($out, "\e[?2026l")) // sync balanced
        ->and($result['exited'])->toBeTrue()                                   // Alt-X actually quits
        ->and($out)->toContain("\e[?1049l")                                    // terminal restored (alt-screen left)
        ->and($out)->toContain("\e[?25h");                                     // cursor restored
})->group('integration')->skip(
    fn (): bool => ! function_exists('proc_open'),
    'Real-terminal tests require proc_open + PTY support.',
);

test('BIOS emits a full frame to a real terminal and quits cleanly on Esc', function (): void {
    $result = tvRunInPty(__DIR__ . '/../../examples/php/bios.php', "\e"); // Esc

    if (! $result['supported']) {
        $this->markTestSkipped('No usable PTY on this platform.');
    }

    $out = $result['out'];

    // A full frame is emitted (not a stub or empty shell)
    expect($result['bytes'])->toBeGreaterThan(2000)
        ->and(substr_count($out, "\e[?2026h"))->toBe(substr_count($out, "\e[?2026l")) // sync balanced
        ->and($result['exited'])->toBeTrue()                                // Esc actually quits
        ->and($out)->toContain("\e[?1049l")                                 // alt-screen left
        ->and($out)->toContain("\e[?25h");                                  // cursor restored
})->group('integration')->skip(
    fn (): bool => ! function_exists('proc_open'),
    'Real-terminal tests require proc_open + PTY support.',
);

test('Guide04 emits a window demo to a real terminal and quits cleanly on Alt-X', function (): void {
    $result = tvRunInPty(__DIR__ . '/../../examples/php/tutorial/Guide04.php', "\ex"); // Alt-X

    if (! $result['supported']) {
        $this->markTestSkipped('No usable PTY on this platform.');
    }

    $out = $result['out'];

    expect($result['bytes'])->toBeGreaterThan(2000)                        // a full frame, not a stub
        ->and(substr_count($out, "\e[?2026h"))->toBe(substr_count($out, "\e[?2026l")) // sync balanced
        ->and($result['exited'])->toBeTrue()                                // Alt-X actually quits
        ->and($out)->toContain("\e[?1049l")                                 // alt-screen left
        ->and($out)->toContain("\e[?25h");                                  // cursor restored
})->group('integration')->skip(
    fn (): bool => ! function_exists('proc_open'),
    'Real-terminal tests require proc_open + PTY support.',
);

test('Calendar enables hover tracking without forcing cell backgrounds', function (): void {
    $result = tvRunInPty(__DIR__ . '/../../examples/php/calendar.php', 'q');

    if (! $result['supported']) {
        $this->markTestSkipped('No usable PTY on this platform.');
    }

    $out = $result['out'];

    expect($result['bytes'])->toBeGreaterThan(4000)
        ->and($out)->toContain("\e[?1003h")
        ->and($out)->toContain("\e[?1003l")
        ->and($out)->not->toMatch('/\e\[[0-9;]*(?:4[0-7]|10[0-7])m/')
        ->and($result['exited'])->toBeTrue();
})->group('integration')->skip(
    fn (): bool => ! function_exists('proc_open'),
    'Real-terminal tests require proc_open + PTY support.',
);

test('Turbo Studio starts with drag tracking and foreground-only output', function (): void {
    $result = tvRunInPty(__DIR__ . '/../../examples/php/studio.php', 'q');

    if (! $result['supported']) {
        $this->markTestSkipped('No usable PTY on this platform.');
    }

    $out = $result['out'];

    expect($result['bytes'])->toBeGreaterThan(100)
        ->and($out)->toContain("\e[?1003h")
        ->and($out)->toContain("\e[?1003l")
        ->and($out)->not->toMatch('/\e\[[0-9;]*(?:4[0-7]|10[0-7])m/')
        ->and($result['exited'])->toBeTrue();
})->group('integration')->skip(
    fn (): bool => ! function_exists('proc_open'),
    'Real-terminal tests require proc_open + PTY support.',
);
