<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use Closure;
use HelgeSverre\TurboVision\Exceptions\DriverException;

/**
 * Real-TTY Driver: raw mode via `stty`, STDOUT writes, non-blocking STDIN polled
 * with stream_select, terminal size via `stty size`, SIGWINCH via pcntl.
 *
 * Testability boundary: only the deterministic, side-effect-free logic
 * (init() environment validation, parseSize(), idempotent shutdown()) is unit-tested.
 * Raw-mode entry/exit, live stream_select polling, SIGWINCH delivery, and alt-screen
 * visuals are inherently terminal-coupled and are exercised by bin/render-demo, not
 * by fragile unit mocks. Requires ext-posix and ext-pcntl.
 *
 * @phpstan-type SttyRunner Closure(string):string
 */
final class AnsiDriver implements Driver
{
    private readonly AnsiEncoder $encoder;

    /** @var resource */
    private $stdin;

    /** @var resource */
    private $stdout;

    /** @var Closure(string):string */
    private Closure $stty;

    private bool $initialised = false;

    private ?bool $savedBlocking = null;

    /** Original `stty -g` settings, captured at init() to restore on shutdown(). */
    private ?string $savedStty = null;

    private bool $resizeFlag = false;

    /**
     * @param resource|null $stdin   defaults to STDIN
     * @param resource|null $stdout  defaults to STDOUT
     * @param (Closure(string):string)|null $sttyRunner runs an stty command, returns its stdout
     */
    public function __construct(
        $stdin = null,
        $stdout = null,
        ?Closure $sttyRunner = null,
        private readonly bool $trackMouseMotion = false,
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stty = $sttyRunner ?? static fn (string $cmd): string => (string) shell_exec($cmd);
        $this->encoder = new AnsiEncoder();
    }

    /**
     * True only for a real interactive terminal. Non-OS streams (php://memory,
     * files, pipes) are rejected before posix_isatty() is called — calling it on
     * a memory stream emits a warning that `@` does not suppress on PHP 8.5.
     *
     * @param resource $stream
     */
    private static function isTty($stream): bool
    {
        if (! \function_exists('posix_isatty') || ! \is_resource($stream)) {
            return false;
        }

        if (stream_get_meta_data($stream)['stream_type'] !== 'STDIO') {
            return false;
        }

        return @posix_isatty($stream);
    }

    public function init(): void
    {
        if ($this->initialised) {
            return;
        }

        // Validate the environment BEFORE mutating any terminal state.
        if (! self::isTty($this->stdin) || ! self::isTty($this->stdout)) {
            throw DriverException::notATty();
        }

        $probe = trim(($this->stty)('command -v stty'));
        if ($probe === '') {
            throw DriverException::sttyUnavailable();
        }

        // Save current settings, then enter raw mode.
        $this->savedStty = trim(($this->stty)('stty -g'));
        $this->savedBlocking = stream_get_meta_data($this->stdin)['blocked'];
        $this->initialised = true;

        try {
            ($this->stty)('stty raw -echo');

            // Non-blocking STDIN so pollInput() never blocks past its timeout.
            stream_set_blocking($this->stdin, false);

            // Enter alt screen, clear, hide cursor, enable mouse.
            $this->write(
                $this->encoder->enterAltScreen()
                . $this->encoder->clearScreen()
                . $this->encoder->hideCursor()
                . $this->encoder->enableMouse($this->trackMouseMotion)
            );

            // Signals: handle them asynchronously so the terminal is restored even if the
            // app is blocked, killed, or hung up. Without this a kill/SIGTERM would leave
            // the terminal in raw mode + alt-screen (a "wedged" terminal).
            // NOTE: signals are dispatched synchronously (via pcntl_signal_dispatch() in
            // resized(), called each event-loop poll). We deliberately do NOT enable
            // async signals: that would let SIGWINCH interrupt a frame write mid-stream
            // (EINTR), truncating output. The write() loop also tolerates partial writes.
            if (\function_exists('pcntl_signal')) {
                pcntl_signal(SIGWINCH, function (): void {
                    $this->resizeFlag = true;
                });
                $restore = function (int $signo): never {
                    $this->shutdown();
                    exit(128 + $signo);
                };
                pcntl_signal(SIGINT, $restore);   // also covers Ctrl-C when ISIG is on
                pcntl_signal(SIGTERM, $restore);
                pcntl_signal(SIGHUP, $restore);
            }

            // Last-resort teardown for fatal errors / normal exit.
            register_shutdown_function([$this, 'shutdown']);
        } catch (\Throwable $exception) {
            $this->shutdown();

            throw $exception;
        }
    }

    public function shutdown(): void
    {
        if (! $this->initialised) {
            return;
        }

        $this->write(
            $this->encoder->disableMouse($this->trackMouseMotion)
            . $this->encoder->showCursor()
            . $this->encoder->leaveAltScreen()
            . $this->encoder->reset()
        );

        if ($this->savedStty !== null && $this->savedStty !== '') {
            ($this->stty)('stty ' . $this->savedStty);
        } else {
            ($this->stty)('stty sane');
        }

        if ($this->savedBlocking !== null) {
            stream_set_blocking($this->stdin, $this->savedBlocking);
        }

        $this->initialised = false;
        $this->savedBlocking = null;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        return self::parseSize(($this->stty)('stty size'));
    }

    public function write(string $bytes): void
    {
        // fwrite() can write fewer bytes than requested (a partial write) when the OS
        // buffer is full or the syscall is interrupted by a signal (EINTR). A single
        // fwrite() therefore silently truncates large frames — the desktop vanishes
        // and a synchronized-update never closes. Loop until everything is flushed.
        $length = strlen($bytes);
        $offset = 0;
        $stalls = 0;

        while ($offset < $length) {
            $written = @fwrite($this->stdout, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                if (++$stalls > 100000) {
                    return; // stream is wedged; give up rather than spin forever
                }

                continue; // interrupted / transiently unwritable: retry the remainder
            }
            $stalls = 0;
            $offset += $written;
        }
    }

    public function pollInput(int $timeoutMs): string
    {
        $read = [$this->stdin];
        $write = null;
        $except = null;
        $sec = intdiv($timeoutMs, 1000);
        $usec = ($timeoutMs % 1000) * 1000;

        $ready = @stream_select($read, $write, $except, $sec, $usec);
        if ($ready === false || $ready === 0) {
            return '';
        }

        $bytes = fread($this->stdin, 8192);

        return $bytes === false ? '' : $bytes;
    }

    public function resized(): bool
    {
        if (\function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        $was = $this->resizeFlag;
        $this->resizeFlag = false;

        return $was;
    }

    /**
     * Parse `stty size` output ("rows cols") into [cols, rows]; fall back to [80, 24].
     *
     * @return array{0:int,1:int}
     */
    public static function parseSize(string $raw): array
    {
        if (preg_match('/(\d+)\s+(\d+)/', trim($raw), $m) === 1) {
            $rows = (int) $m[1];
            $cols = (int) $m[2];
            if ($rows > 0 && $cols > 0) {
                return [$cols, $rows];
            }
        }

        return [80, 24];
    }
}
