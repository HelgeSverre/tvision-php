<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Text;

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\Scroller;

/**
 * A scrollable text output device (the PHP counterpart of Turbo Vision's
 * TTextDevice). Concrete devices accept bytes through doSputn(); write() is
 * the pleasant PHP-facing spelling. The return value is always strlen($text):
 * consumption is total (invalid bytes degrade to '?' glyphs), never partial —
 * unlike fwrite(), which can accept only a prefix.
 */
abstract class TextDevice extends Scroller
{
    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar = null,
        ?ScrollBar $vScrollBar = null,
    ) {
        parent::__construct($bounds, $hScrollBar, $vScrollBar);
    }

    public function write(string $text): int
    {
        return $this->doSputn($text);
    }

    /** Accept a byte string written by an output adapter. */
    abstract public function doSputn(string $text): int;

    /** Flush is intentionally lightweight: writes are immediately retained. */
    public function flush(): void
    {
        $this->drawView();
    }
}
