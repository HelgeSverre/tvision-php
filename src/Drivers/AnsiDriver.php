<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Drivers;

use Closure;
use HelgeSverre\TurboVision\Exceptions\DriverException;
use HelgeSverre\TurboVision\Terminal\TerminalCapabilities;

/**
 * Real-TTY Driver: raw mode via `stty`, STDOUT writes, non-blocking STDIN polled
 * with stream_select, terminal size via `stty size`, SIGWINCH via pcntl.
 *
 * Testability boundary: only the deterministic, side-effect-free logic
 * (init() environment validation, parseSize(), idempotent shutdown()) is unit-tested.
 * Raw-mode entry/exit, live stream_select polling, SIGWINCH delivery, and alt-screen
 * visuals are inherently terminal-coupled and are exercised by bin/render-demo, not
 * by fragile unit mocks. pcntl/posix improve integration but have safe fallbacks.
 *
 * @phpstan-type SttyRunner Closure(string):string
 */
final class AnsiDriver implements Driver, ProvidesTerminalCapabilities
{
    private const int MAX_WRITE_STALLS = 256;

    private const int WRITE_STALL_DELAY_MICROSECONDS = 1_000;

    private const float RESIZE_POLL_SECONDS = 0.25;

    private readonly AnsiEncoder $encoder;

    private readonly TerminalCapabilities $capabilities;

    /** @var Closure(): float */
    private readonly Closure $clock;

    private readonly bool $signalsAvailable;

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

    /** @var array{0:int,1:int}|null */
    private ?array $lastSize = null;

    /** @var array{0:int,1:int}|null */
    private ?array $pendingSize = null;

    private float $nextResizePollAt = 0.0;

    private bool $shutdownRegistered = false;

    /** @var array<int, callable|int> signal => handler active before init() */
    private array $savedSignalHandlers = [];

    /**
     * @param resource|null $stdin   defaults to STDIN
     * @param resource|null $stdout  defaults to STDOUT
     * @param (Closure(string):string)|null $sttyRunner runs an stty command, returns its stdout
     * @param (Closure():float)|null $clock monotonic seconds, injectable for fallback-resize tests
     */
    public function __construct(
        $stdin = null,
        $stdout = null,
        ?Closure $sttyRunner = null,
        private readonly bool $trackMouseMotion = false,
        ?TerminalCapabilities $capabilities = null,
        ?Closure $clock = null,
        ?bool $signalSupport = null,
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stty = $sttyRunner ?? static function (string $cmd): string {
            if (! \function_exists('shell_exec')) {
                return '';
            }

            return (string) shell_exec($cmd);
        };
        $this->encoder = new AnsiEncoder();
        $this->capabilities = $capabilities ?? TerminalCapabilities::detectProcess();
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000_000;
        $runtimeSignalSupport = (
            \function_exists('pcntl_signal')
            && \function_exists('pcntl_signal_dispatch')
            && \defined('SIGWINCH')
            && \defined('SIGINT')
            && \defined('SIGTERM')
            && \defined('SIGHUP')
        );
        $this->signalsAvailable = ($signalSupport ?? true) && $runtimeSignalSupport;
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
        if (! \is_resource($stream)) {
            return false;
        }

        if (stream_get_meta_data($stream)['stream_type'] !== 'STDIO') {
            return false;
        }

        if (\function_exists('stream_isatty')) {
            return @stream_isatty($stream);
        }

        return \function_exists('posix_isatty') && @posix_isatty($stream);
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
                . ($this->capabilities->kittyKeyboard ? $this->encoder->pushKittyKeyboard() : '')
            );

            // Signals: handle them asynchronously so the terminal is restored even if the
            // app is blocked, killed, or hung up. Without this a kill/SIGTERM would leave
            // the terminal in raw mode + alt-screen (a "wedged" terminal).
            // NOTE: signals are dispatched synchronously (via pcntl_signal_dispatch() in
            // resized(), called each event-loop poll). We deliberately do NOT enable
            // async signals: that would let SIGWINCH interrupt a frame write mid-stream
            // (EINTR), truncating output. The write() loop also tolerates partial writes.
            if ($this->signalsAvailable) {
                $this->installSignalHandler(SIGWINCH, function (): void {
                    $this->resizeFlag = true;
                });
                $restore = function (int $signo): never {
                    $this->shutdown();
                    exit(128 + $signo);
                };
                $this->installSignalHandler(SIGINT, $restore); // also covers Ctrl-C when ISIG is on
                $this->installSignalHandler(SIGTERM, $restore);
                $this->installSignalHandler(SIGHUP, $restore);
            }

            // Last-resort teardown for fatal errors / normal exit.
            if (! $this->shutdownRegistered) {
                register_shutdown_function([$this, 'shutdown']);
                $this->shutdownRegistered = true;
            }
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

        // Mark it first so a teardown-time signal cannot recursively restore the
        // same resources. Every cleanup step is best-effort and independent: a
        // wedged output stream must never prevent raw-mode restoration.
        $this->initialised = false;
        try {
            $this->write(
                ($this->capabilities->kittyKeyboard ? $this->encoder->popKittyKeyboard() : '')
                . $this->encoder->disableMouse($this->trackMouseMotion)
                . $this->encoder->showCursor()
                . $this->encoder->leaveAltScreen()
                . $this->encoder->reset()
            );
        } catch (\Throwable) {
            // Continue restoring the TTY even if the visual teardown cannot write.
        }

        try {
            if ($this->savedStty !== null && $this->savedStty !== '') {
                ($this->stty)('stty ' . $this->savedStty);
            } else {
                ($this->stty)('stty sane');
            }
        } catch (\Throwable) {
            try {
                ($this->stty)('stty sane');
            } catch (\Throwable) {
                // Nothing else can restore the OS terminal mode here.
            }
        }

        if ($this->savedBlocking !== null) {
            try {
                stream_set_blocking($this->stdin, $this->savedBlocking);
            } catch (\Throwable) {
                // The stream may already have been closed by application teardown.
            }
        }

        $this->restoreSignalHandlers();
        $this->savedBlocking = null;
        $this->savedStty = null;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        if ($this->pendingSize !== null) {
            $size = $this->pendingSize;
            $this->pendingSize = null;
        } else {
            $size = $this->querySize();
        }
        $this->lastSize = $size;

        return $size;
    }

    public function terminalCapabilities(): TerminalCapabilities
    {
        return $this->capabilities;
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
            $writeWarning = null;
            set_error_handler(static function (int $severity, string $message) use (&$writeWarning): bool {
                $writeWarning = $message;

                return true;
            }, E_WARNING | E_USER_WARNING);
            try {
                $written = fwrite($this->stdout, substr($bytes, $offset));
            } catch (\Throwable $exception) {
                throw DriverException::writeFailed($exception);
            } finally {
                restore_error_handler();
            }
            if ($written === false || $written === 0) {
                $stalls++;
                if ($stalls >= self::MAX_WRITE_STALLS) {
                    $previous = $writeWarning === null ? null : new \RuntimeException($writeWarning);

                    throw DriverException::writeFailed($previous);
                }

                if (self::operationWasInterrupted($writeWarning)) {
                    if ($this->signalsAvailable) {
                        pcntl_signal_dispatch();
                    }
                    usleep(self::WRITE_STALL_DELAY_MICROSECONDS);

                    continue;
                }

                if ($this->signalsAvailable) {
                    pcntl_signal_dispatch();
                }
                usleep(self::WRITE_STALL_DELAY_MICROSECONDS);

                continue; // transiently unwritable: retry the remainder
            }
            $stalls = 0;
            $offset += $written;
        }
    }

    private function installSignalHandler(int $signal, callable $handler): void
    {
        $previous = \function_exists('pcntl_signal_get_handler')
            ? pcntl_signal_get_handler($signal)
            : SIG_DFL;
        if (pcntl_signal($signal, $handler)) {
            $this->savedSignalHandlers[$signal] = $previous;
        }
    }

    private function restoreSignalHandlers(): void
    {
        if (! $this->signalsAvailable) {
            $this->savedSignalHandlers = [];

            return;
        }

        foreach ($this->savedSignalHandlers as $signal => $handler) {
            try {
                pcntl_signal($signal, $handler);
            } catch (\Throwable) {
                // Best effort during shutdown; terminal restoration has priority.
            }
        }
        $this->savedSignalHandlers = [];
    }

    public function pollInput(int $timeoutMs): string
    {
        if ($timeoutMs < 0) {
            throw new \InvalidArgumentException('The poll timeout must be non-negative.');
        }

        $read = [$this->stdin];
        $write = null;
        $except = null;
        $sec = intdiv($timeoutMs, 1000);
        $usec = ($timeoutMs % 1000) * 1000;

        $selectWarning = null;
        set_error_handler(static function (int $severity, string $message) use (&$selectWarning): bool {
            $selectWarning = $message;

            return true;
        }, E_WARNING);
        try {
            $ready = stream_select($read, $write, $except, $sec, $usec);
        } catch (\Throwable $exception) {
            throw DriverException::readFailed($exception);
        } finally {
            restore_error_handler();
        }
        if ($ready === false) {
            if (self::operationWasInterrupted($selectWarning)) {
                if ($this->signalsAvailable) {
                    pcntl_signal_dispatch();
                }

                return '';
            }

            $previous = $selectWarning === null ? null : new \RuntimeException($selectWarning);

            throw DriverException::readFailed($previous);
        }
        if ($ready === 0) {
            return '';
        }

        try {
            $bytes = fread($this->stdin, 8192);
        } catch (\Throwable $exception) {
            throw DriverException::readFailed($exception);
        }

        if ($bytes === false) {
            throw DriverException::readFailed();
        }
        if ($bytes === '' && feof($this->stdin)) {
            throw DriverException::inputClosed();
        }

        return $bytes;
    }

    /** Whether an I/O operation failed only because a signal interrupted the syscall. */
    private static function operationWasInterrupted(?string $warning): bool
    {
        if ($warning === null) {
            return false;
        }

        if (\defined('PCNTL_EINTR')) {
            foreach (['/Unable to select \[(\d+)\]/', '/errno[=:\s]+(\d+)/i'] as $pattern) {
                if (preg_match($pattern, $warning, $matches) === 1) {
                    return (int) $matches[1] === (int) \constant('PCNTL_EINTR');
                }
            }
        }

        return str_contains($warning, 'Interrupted system call');
    }

    public function resized(): bool
    {
        if ($this->signalsAvailable) {
            pcntl_signal_dispatch();
        } else {
            $now = ($this->clock)();
            if ($now >= $this->nextResizePollAt) {
                $this->nextResizePollAt = $now + self::RESIZE_POLL_SECONDS;
                $current = $this->querySize();
                if ($this->lastSize !== null && $current !== $this->lastSize) {
                    $this->pendingSize = $current;
                    $this->resizeFlag = true;
                }
            }
        }
        $was = $this->resizeFlag;
        $this->resizeFlag = false;

        return $was;
    }

    /** @return array{0:int,1:int} */
    private function querySize(): array
    {
        return self::parseSize(($this->stty)('stty size'));
    }

    /**
     * Parse `stty size` output ("rows cols") into [cols, rows]; fall back to [80, 24].
     *
     * @return array{0:int,1:int}
     */
    public static function parseSize(string $raw): array
    {
        if (preg_match('/^(\d+)\s+(\d+)$/D', trim($raw), $m) === 1) {
            $rows = (int) $m[1];
            $cols = (int) $m[2];
            if ($rows > 0 && $cols > 0) {
                return [$cols, $rows];
            }
        }

        return [80, 24];
    }
}
