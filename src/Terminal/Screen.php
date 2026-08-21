<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Terminal;

use Closure;
use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use HelgeSverre\TurboVision\Drivers\Driver;
use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Drivers\ProvidesTerminalCapabilities;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Rendering\DiffPresenter;
use InvalidArgumentException;

/**
 * Integration capstone tying a Driver to the render/input pipeline. Owns a back
 * Buffer (what views draw into), a front Buffer (what is on screen), an AnsiEncoder,
 * an EscapeDecoder (+ its remainder), and a DiffPresenter.
 */
final class Screen
{
    private const int MAX_REMAINDER_BYTES = 4096;

    private const float ESCAPE_TIMEOUT_SECONDS = 0.04;

    private const float SEQUENCE_TIMEOUT_SECONDS = 0.25;

    private Buffer $back;

    private Buffer $front;

    private readonly AnsiEncoder $encoder;

    private readonly EscapeDecoder $decoder;

    private readonly DiffPresenter $presenter;

    private readonly TerminalCapabilities $capabilities;

    /** @var Closure():float */
    private readonly Closure $clock;

    private string $remainder = '';

    private ?float $remainderSince = null;

    private bool $wasResized = false;

    /** Desired hardware cursor position; null keeps it hidden. */
    private ?Point $cursor = null;

    private ?Point $presentedCursor = null;

    private bool $cursorVisible = false;

    /** A resize invalidates terminal cursor state independently of cell buffers. */
    private bool $cursorNeedsReconcile = false;

    /** @param (Closure():float)|null $clock Monotonic seconds, injectable for tests. */
    public function __construct(
        private readonly Driver $driver,
        ?AnsiEncoder $encoder = null,
        ?Closure $clock = null,
        ?TerminalCapabilities $capabilities = null,
    ) {
        $this->encoder = $encoder ?? new AnsiEncoder();
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000_000;
        $this->decoder = new EscapeDecoder($this->clock);
        $this->presenter = new DiffPresenter();
        $this->capabilities = $capabilities
            ?? ($driver instanceof ProvidesTerminalCapabilities
                ? $driver->terminalCapabilities()
                : new TerminalCapabilities());
        // Provisional buffers until init() reads the real size.
        $this->back = new Buffer(0, 0);
        $this->front = new Buffer(0, 0);
    }

    public function init(): void
    {
        try {
            $this->driver->init();
            [$cols, $rows] = $this->driver->size();
            $this->resizeBuffers($cols, $rows);
            $this->remainder = '';
            $this->remainderSince = null;
            $this->wasResized = false;
            $this->cursor = null;
            $this->presentedCursor = null;
            $this->cursorVisible = false;
            $this->cursorNeedsReconcile = false;
        } catch (\Throwable $exception) {
            // Driver::shutdown() is contractually idempotent and must unwind partial init.
            $this->driver->shutdown();

            throw $exception;
        }
    }

    public function shutdown(): void
    {
        $this->driver->shutdown();
    }

    public function back(): Buffer
    {
        return $this->back;
    }

    public function size(): Point
    {
        return new Point($this->back->width, $this->back->height);
    }

    public function cols(): int
    {
        return $this->back->width;
    }

    public function rows(): int
    {
        return $this->back->height;
    }

    /** Reset the back buffer to blank cells. */
    public function clear(): void
    {
        $this->back = new Buffer($this->back->width, $this->back->height);
    }

    /**
     * Set the cursor requested by the focused view. The request is emitted at the
     * end of the next flush so a frame's paint operations cannot displace it.
     */
    public function setCursor(?Point $cursor): void
    {
        if ($cursor !== null
            && ($cursor->x < 0 || $cursor->y < 0 || $cursor->x >= $this->cols() || $cursor->y >= $this->rows())
        ) {
            $cursor = null;
        }

        $this->cursor = $cursor;
    }

    /** Diff front->back, write the minimal ANSI, then copy back into front. */
    public function flush(): void
    {
        $ansi = $this->presenter->present($this->front, $this->back, $this->encoder);
        $cursorAnsi = $this->cursorSequence();
        if ($ansi === '' && $cursorAnsi === '') {
            return;
        }

        $this->driver->write($this->capabilities->synchronizedUpdates
            ? $this->encoder->beginSyncUpdate() . $ansi . $cursorAnsi . $this->encoder->endSyncUpdate()
            : $ansi . $cursorAnsi);
        $this->front = $this->back->copy();
    }

    private function cursorSequence(): string
    {
        if ($this->cursor === null) {
            if (! $this->cursorVisible && ! $this->cursorNeedsReconcile) {
                return '';
            }
            $this->cursorVisible = false;
            $this->presentedCursor = null;
            $this->cursorNeedsReconcile = false;

            return $this->encoder->hideCursor();
        }

        $sequence = '';
        if ($this->presentedCursor === null
            || $this->presentedCursor->x !== $this->cursor->x
            || $this->presentedCursor->y !== $this->cursor->y
        ) {
            $sequence .= $this->encoder->moveCursor($this->cursor->x, $this->cursor->y);
            $this->presentedCursor = $this->cursor;
        }
        if (! $this->cursorVisible || $this->cursorNeedsReconcile) {
            $sequence .= $this->encoder->showCursor();
            $this->cursorVisible = true;
        }
        $this->cursorNeedsReconcile = false;

        return $sequence;
    }

    /**
     * Poll the driver for input, decode it (reassembling across calls via the held
     * remainder), surface resize, and emit a pending ESC on a quiet tick.
     *
     * @return list<Event>
     */
    public function pollEvents(int $timeoutMs): array
    {
        if ($timeoutMs < 0) {
            throw new InvalidArgumentException('The poll timeout must be non-negative.');
        }

        if ($this->driver->resized()) {
            [$cols, $rows] = $this->driver->size();
            $this->resizeBuffers($cols, $rows, invalidateFront: true);
            $this->wasResized = true;
        }

        $bytes = $this->driver->pollInput($timeoutMs);

        if ($bytes === '') {
            if ($this->remainder === '') {
                return [];
            }

            $now = ($this->clock)();
            $this->remainderSince ??= $now;
            $onlyEscapes = strspn($this->remainder, "\e") === strlen($this->remainder);
            $timeout = $onlyEscapes
                ? self::ESCAPE_TIMEOUT_SECONDS
                : self::SEQUENCE_TIMEOUT_SECONDS;
            if ($now - $this->remainderSince < $timeout) {
                return [];
            }

            $pending = $this->decoder->flushPendingEvents($this->remainder);
            $this->remainder = '';
            $this->remainderSince = null;

            return $pending;
        }

        $previousRemainder = $this->remainder;
        $result = $this->decoder->decode($previousRemainder . $bytes);
        $this->remainder = $result->remainder;
        if (strlen($this->remainder) > self::MAX_REMAINDER_BYTES) {
            $this->remainder = '';
            $this->remainderSince = null;
        } elseif ($this->remainder === '') {
            $this->remainderSince = null;
        } else {
            // The expiry is an inter-fragment timeout. Any new bytes that make
            // progress buy the sequence a fresh window to complete.
            $this->remainderSince = ($this->clock)();
        }

        return $result->events;
    }

    /** True once since the last call if the terminal was resized (clears the flag). */
    public function wasResized(): bool
    {
        $was = $this->wasResized;
        $this->wasResized = false;

        return $was;
    }

    private function resizeBuffers(int $cols, int $rows, bool $invalidateFront = false): void
    {
        if ($cols < 0
            || $rows < 0
            || ($cols !== 0 && $rows > intdiv(Buffer::MAX_CELLS, $cols))
        ) {
            throw new InvalidArgumentException('Terminal dimensions are outside the safe screen-buffer limit.');
        }

        $this->back = new Buffer($cols, $rows);
        $frontFill = $invalidateFront ? new Cell("\0", -1) : null;
        $this->front = new Buffer($cols, $rows, $frontFill);
        if ($this->cursor !== null
            && ($this->cursor->x < 0 || $this->cursor->y < 0 || $this->cursor->x >= $cols || $this->cursor->y >= $rows)
        ) {
            $this->cursor = null;
        }
        $this->presentedCursor = null;
        $this->cursorNeedsReconcile = true;
    }

}
