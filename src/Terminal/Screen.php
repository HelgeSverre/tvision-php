<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Terminal;

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Drivers\AnsiEncoder;
use HelgeSverre\TurboVision\Drivers\Driver;
use HelgeSverre\TurboVision\Drivers\EscapeDecoder;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Rendering\DiffPresenter;

/**
 * Integration capstone tying a Driver to the render/input pipeline. Owns a back
 * Buffer (what views draw into), a front Buffer (what is on screen), an AnsiEncoder,
 * an EscapeDecoder (+ its remainder), and a DiffPresenter.
 */
final class Screen
{
    private Buffer $back;

    private Buffer $front;

    private readonly AnsiEncoder $encoder;

    private readonly EscapeDecoder $decoder;

    private readonly DiffPresenter $presenter;

    private string $remainder = '';

    private bool $wasResized = false;

    public function __construct(private readonly Driver $driver, ?AnsiEncoder $encoder = null)
    {
        $this->encoder = $encoder ?? new AnsiEncoder();
        $this->decoder = new EscapeDecoder();
        $this->presenter = new DiffPresenter();
        // Provisional buffers until init() reads the real size.
        $this->back = new Buffer(0, 0);
        $this->front = new Buffer(0, 0);
    }

    public function init(): void
    {
        $this->driver->init();
        [$cols, $rows] = $this->driver->size();
        $this->resizeBuffers($cols, $rows);
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

    /** Diff front->back, write the minimal ANSI, then copy back into front. */
    public function flush(): void
    {
        $ansi = $this->presenter->present($this->front, $this->back, $this->encoder);
        if ($ansi !== '') {
            // Wrap the frame so modern terminals present it atomically (no tearing).
            $this->driver->write(
                $this->encoder->beginSyncUpdate() . $ansi . $this->encoder->endSyncUpdate()
            );
        }
        $this->front = $this->copyOf($this->back);
    }

    /**
     * Poll the driver for input, decode it (reassembling across calls via the held
     * remainder), surface resize, and emit a pending ESC on a quiet tick.
     *
     * @return list<Event>
     */
    public function pollEvents(int $timeoutMs): array
    {
        if ($this->driver->resized()) {
            [$cols, $rows] = $this->driver->size();
            $this->resizeBuffers($cols, $rows, invalidateFront: true);
            $this->wasResized = true;
        }

        $bytes = $this->driver->pollInput($timeoutMs);

        if ($bytes === '') {
            // Quiet tick: a stranded ESC in the remainder becomes Key::Esc.
            $pending = $this->decoder->flushPending($this->remainder);
            if ($pending !== null) {
                $this->remainder = '';

                return [$pending];
            }

            return [];
        }

        $result = $this->decoder->decode($this->remainder . $bytes);
        $this->remainder = $result->remainder;

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
        $this->back = new Buffer($cols, $rows);
        $frontFill = $invalidateFront ? new Cell("\0", -1) : null;
        $this->front = new Buffer($cols, $rows, $frontFill);
    }

    private function copyOf(Buffer $source): Buffer
    {
        $copy = new Buffer($source->width, $source->height);
        for ($y = 0; $y < $source->height; $y++) {
            for ($x = 0; $x < $source->width; $x++) {
                $copy->put($x, $y, $source->at($x, $y));
            }
        }

        return $copy;
    }
}
