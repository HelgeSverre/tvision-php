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
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stty = $sttyRunner ?? static fn (string $cmd): string => (string) shell_exec($cmd);
        $this->encoder = new AnsiEncoder();
    }

    public function init(): void
    {
        if ($this->initialised) {
            return;
        }

        // Validate the environment BEFORE mutating any terminal state.
        if (! \function_exists('posix_isatty')
            || ! @posix_isatty($this->stdin)
            || ! @posix_isatty($this->stdout)) {
            throw DriverException::notATty();
        }

        $probe = trim(($this->stty)('command -v stty'));
        if ($probe === '') {
            throw DriverException::sttyUnavailable();
        }

        // Save current settings, then enter raw mode.
        $this->savedStty = trim(($this->stty)('stty -g'));
        ($this->stty)('stty raw -echo');

        // Non-blocking STDIN so pollInput() never blocks past its timeout.
        stream_set_blocking($this->stdin, false);

        // Enter alt screen, clear, hide cursor, enable mouse.
        $this->write(
            $this->encoder->enterAltScreen()
            . $this->encoder->clearScreen()
            . $this->encoder->hideCursor()
            . $this->encoder->enableMouse()
        );

        // Trap SIGWINCH and guarantee teardown on any exit path.
        if (\function_exists('pcntl_signal') && \defined('SIGWINCH')) {
            pcntl_signal(SIGWINCH, function (): void {
                $this->resizeFlag = true;
            });
        }
        register_shutdown_function([$this, 'shutdown']);

        $this->initialised = true;
    }

    public function shutdown(): void
    {
        if (! $this->initialised) {
            return;
        }

        $this->write(
            $this->encoder->disableMouse()
            . $this->encoder->showCursor()
            . $this->encoder->leaveAltScreen()
            . $this->encoder->reset()
        );

        if ($this->savedStty !== null && $this->savedStty !== '') {
            ($this->stty)('stty ' . $this->savedStty);
        } else {
            ($this->stty)('stty sane');
        }

        $this->initialised = false;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        return self::parseSize(($this->stty)('stty size'));
    }

    public function write(string $bytes): void
    {
        fwrite($this->stdout, $bytes);
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
