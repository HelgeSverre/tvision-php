<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Text;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\IntMath;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\State;
use InvalidArgumentException;

/**
 * Append-only, bounded terminal output with a circular scrollback buffer.
 *
 * Storage is a ring of logical lines, bounded independently by line count and
 * payload bytes. Rendering derives soft-wrapped rows from that ring at the
 * current view width, so a resize reflows old output without retaining a second
 * unbounded display buffer. Every displayed grapheme passes through
 * TerminalText::cellGlyph(), preserving the framework's one-cell invariant.
 */
final class Terminal extends TextDevice
{
    /** cpTerminal: normal text. */
    private const string PALETTE = "\x06";

    /** Keep one write bounded without turning every byte into a trim pass. */
    private const int RETENTION_OVERRUN_BYTES = 4_096;

    /** UTF-8 parsing window; the final grapheme carries into the next window. */
    private const int INPUT_CHUNK_BYTES = 8_192;

    /** @var array<int,string> fixed-size logical-line ring */
    private array $ring;

    private int $head = 0;

    private int $count = 0;

    private int $storedBytes = 0;

    private int $cursorColumn = 0;

    /** Display-cell count in the current tail line, kept to make append O(1). */
    private int $tailColumns = 0;

    private bool $pendingCarriageReturn = false;

    private bool $layoutDirty = true;

    /** Batch retention while writing, with RETENTION_OVERRUN_BYTES as a hard overrun cap. */
    private bool $writing = false;

    /** @var list<string> soft-wrapped, display-safe rows */
    private array $visualRows = [''];

    private ?OutputTextStream $outputStream = null;

    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar = null,
        ?ScrollBar $vScrollBar = null,
        private readonly int $maxBytes = 65_536,
        private readonly int $maxLines = 2_048,
        private bool $wrap = true,
        private readonly int $tabWidth = 4,
    ) {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException('Terminal maxBytes must be positive.');
        }
        if ($maxLines < 1) {
            throw new InvalidArgumentException('Terminal maxLines must be positive.');
        }
        if ($tabWidth < 1) {
            throw new InvalidArgumentException('Terminal tabWidth must be positive.');
        }

        parent::__construct($bounds, $hScrollBar, $vScrollBar);
        $this->ring = array_fill(0, $maxLines, '');
        $this->appendEmptyLine();
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->setLimit(max(1, $bounds->width()), 1);
        $this->setCursor(0, 0);
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    /** Number of retained logical (hard-break) lines. */
    public function lineCount(): int
    {
        return $this->count;
    }

    /** Retained UTF-8 byte payload, excluding PHP array overhead. */
    public function scrollbackBytes(): int
    {
        return $this->storedBytes;
    }

    public function maxScrollbackBytes(): int
    {
        return $this->maxBytes;
    }

    public function maxScrollbackLines(): int
    {
        return $this->maxLines;
    }

    /** @return list<string> retained hard-break lines from oldest to newest. */
    public function scrollback(): array
    {
        $lines = [];
        for ($i = 0; $i < $this->count; $i++) {
            $lines[] = $this->ring[($this->head + $i) % $this->maxLines];
        }

        return $lines;
    }

    /** A reusable writer suitable for callback-oriented PHP logging code. */
    public function output(): OutputTextStream
    {
        return $this->outputStream ??= new OutputTextStream($this);
    }

    public function isWrapping(): bool
    {
        return $this->wrap;
    }

    public function setWrapping(bool $wrap): void
    {
        if ($this->wrap === $wrap) {
            return;
        }

        $wasAtBottom = $this->isAtBottom();
        $this->wrap = $wrap;
        $this->layoutDirty = true;
        $this->refreshLayout();
        $this->scrollAfterLayoutChange($wasAtBottom);
        $this->drawView();
    }

    /** Drop every retained line and restore the always-present editable tail. */
    public function clear(): void
    {
        $this->ring = array_fill(0, $this->maxLines, '');
        $this->head = 0;
        $this->count = 0;
        $this->storedBytes = 0;
        $this->cursorColumn = 0;
        $this->tailColumns = 0;
        $this->pendingCarriageReturn = false;
        $this->appendEmptyLine();
        $this->layoutDirty = true;
        $this->refreshLayout();
        $this->scrollToBottom();
        $this->drawView();
    }

    /**
     * Append text using practical TTY controls: LF starts a line, CR rewinds
     * the current line, TAB expands to spaces and backspace rewinds one cell.
     * Other control bytes are ignored. Invalid UTF-8 and wide/zero-width glyphs
     * are rendered as `?`, consistent with all framework drawing APIs.
     */
    public function doSputn(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $accepted = strlen($text);
        $wasAtBottom = $this->isAtBottom();

        $this->writing = true;
        try {
            $this->consumeText($text);
        } finally {
            $this->writing = false;
            $this->enforceByteBudget();
        }

        $this->layoutDirty = true;
        $this->refreshLayout();
        $this->scrollAfterLayoutChange($wasAtBottom);
        $this->drawView();

        return $accepted;
    }

    public function draw(): void
    {
        $this->refreshLayout();
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $attr = $this->getColor(1) & 0xFF;

        for ($y = 0; $y < $height; $y++) {
            $row = $this->visualRows[$this->delta->y + $y] ?? '';
            if (! $this->wrap && $this->delta->x > 0) {
                $row = TerminalText::slice($row, $this->delta->x, $width);
            }
            $buffer = new DrawBuffer($width);
            $buffer->moveChar(0, ' ', $attr, $width);
            $buffer->moveStr(0, $row, $attr);
            $this->writeLine(0, $y, $width, 1, $buffer);
        }

        $cursorY = $this->cursorVisualRow() - $this->delta->y;
        $cursorX = $this->wrap
            ? $this->cursorColumn % max(1, $width)
            : $this->cursorColumn - $this->delta->x;
        $this->setCursor(max(0, $cursorX), max(0, $cursorY));
    }

    public function changeBounds(Rect $bounds): void
    {
        $wasAtBottom = $this->isAtBottom();
        $this->setBounds($bounds);
        $this->layoutDirty = true;
        $this->refreshLayout();
        $this->scrollAfterLayoutChange($wasAtBottom);
        $this->drawView();
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what !== EventType::KeyDown) {
            return;
        }

        $key = $event->asKey()?->keyCode;
        $page = max(1, $this->bounds->height() - 1);
        $delta = match ($key) {
            Key::Up->value => -1,
            Key::Down->value => 1,
            Key::PageUp->value => -$page,
            Key::PageDown->value => $page,
            Key::Home->value => -PHP_INT_MAX,
            Key::End->value => PHP_INT_MAX,
            default => null,
        };
        if ($delta === null) {
            return;
        }

        $this->scrollBy($delta);
        $this->clearEvent($event);
    }

    /** Move the viewport by visual rows, preserving normal Scroller bar sync. */
    public function scrollBy(int $rows): void
    {
        $this->refreshLayout();
        $target = IntMath::add($this->delta->y, $rows);
        $this->scrollToRow($target);
    }

    public function scrollToBottom(): void
    {
        $this->refreshLayout();
        $this->scrollToRow($this->maxScrollY());
    }

    private function scrollToRow(int $row): void
    {
        $row = max(0, min($this->maxScrollY(), $row));
        if ($this->getVScrollBar() !== null) {
            parent::scrollTo($this->delta->x, $row);
            return;
        }

        $hBar = $this->getHScrollBar();
        $hBar?->setValue($this->delta->x);
        $x = $hBar === null ? $this->delta->x : $hBar->value;
        if ($row !== $this->delta->y || $x !== $this->delta->x) {
            $this->delta = new Point($x, $row);
            $this->drawView();
        }
    }

    private function isAtBottom(): bool
    {
        $this->refreshLayout();

        return $this->delta->y >= $this->maxScrollY();
    }

    private function scrollAfterLayoutChange(bool $wasAtBottom): void
    {
        if ($wasAtBottom) {
            $this->scrollToBottom();

            return;
        }
        $this->scrollToRow($this->delta->y);
    }

    private function maxScrollY(): int
    {
        return max(0, count($this->visualRows) - $this->bounds->height());
    }

    private function refreshLayout(): void
    {
        if (! $this->layoutDirty) {
            return;
        }

        $width = max(1, $this->bounds->width());
        $visualRows = [];
        $maxWidth = 0;
        foreach ($this->scrollback() as $line) {
            $glyphs = TerminalText::graphemes($line);
            $maxWidth = max($maxWidth, count($glyphs));
            if ($glyphs === []) {
                $visualRows[] = '';
                continue;
            }
            if (! $this->wrap) {
                $visualRows[] = $line;
                continue;
            }
            foreach (array_chunk($glyphs, $width) as $chunk) {
                $visualRows[] = implode('', $chunk);
            }
        }
        $this->visualRows = $visualRows === [] ? [''] : $visualRows;
        $this->layoutDirty = false;

        $limitX = $this->wrap ? $width : max($width, $maxWidth);
        $this->setLimit($limitX, count($this->visualRows));
    }

    private function carriageReturn(): void
    {
        $this->cursorColumn = 0;
        $this->pendingCarriageReturn = true;
    }

    private function tab(): void
    {
        $spaces = $this->tabWidth - ($this->cursorColumn % $this->tabWidth);
        for ($i = 0; $i < $spaces; $i++) {
            $this->putGlyph(' ');
        }
    }

    private function backspace(): void
    {
        $this->pendingCarriageReturn = false;
        $this->cursorColumn = max(0, $this->cursorColumn - 1);
    }

    private function putGlyph(string $glyph): void
    {
        $this->pendingCarriageReturn = false;
        $glyph = TerminalText::cellGlyph($glyph);

        if ($this->cursorColumn === $this->tailColumns) {
            $slot = ($this->head + $this->count - 1) % $this->maxLines;
            $this->storedBytes -= strlen($this->ring[$slot]);
            $this->ring[$slot] .= $glyph;
            $this->storedBytes += strlen($this->ring[$slot]);
            $this->tailColumns++;
            $this->cursorColumn++;
            if (! $this->writing) {
                $this->enforceByteBudget();
            } elseif ($this->storedBytes > IntMath::add($this->maxBytes, self::RETENTION_OVERRUN_BYTES)) {
                $this->enforceByteBudget();
            }

            return;
        }

        $line = $this->tailLine();
        $cells = TerminalText::graphemes($line);
        while (count($cells) < $this->cursorColumn) {
            $cells[] = ' ';
        }
        $cells[$this->cursorColumn] = $glyph;
        $this->tailColumns = max($this->tailColumns, $this->cursorColumn + 1);
        $this->replaceTail(implode('', $cells));
        $this->cursorColumn++;
    }

    private function newLine(): void
    {
        $this->pendingCarriageReturn = false;
        $this->appendEmptyLine();
        $this->cursorColumn = 0;
        $this->tailColumns = 0;
    }

    private function appendEmptyLine(): void
    {
        if ($this->count === $this->maxLines) {
            $this->evictHead();
        }
        $slot = ($this->head + $this->count) % $this->maxLines;
        $this->ring[$slot] = '';
        $this->count++;
    }

    private function tailLine(): string
    {
        return $this->ring[($this->head + $this->count - 1) % $this->maxLines];
    }

    private function replaceTail(string $line): void
    {
        $slot = ($this->head + $this->count - 1) % $this->maxLines;
        $this->storedBytes -= strlen($this->ring[$slot]);
        $this->ring[$slot] = $line;
        $this->storedBytes += strlen($line);

        if (! $this->writing) {
            $this->enforceByteBudget();
        } elseif ($this->storedBytes > IntMath::add($this->maxBytes, self::RETENTION_OVERRUN_BYTES)) {
            $this->enforceByteBudget();
        }
    }

    private function enforceByteBudget(): void
    {
        while ($this->storedBytes > $this->maxBytes && $this->count > 1) {
            $this->evictHead();
        }
        // A single overlong current line cannot be evicted. Keep its tail at
        // grapheme boundaries so the byte budget remains a real hard limit.
        if ($this->storedBytes > $this->maxBytes) {
            $this->trimTailToBudget();
        }
    }

    private function evictHead(): void
    {
        $this->storedBytes -= strlen($this->ring[$this->head]);
        $this->ring[$this->head] = '';
        $this->head = ($this->head + 1) % $this->maxLines;
        $this->count--;
    }

    private function trimTailToBudget(): void
    {
        $slot = ($this->head + $this->count - 1) % $this->maxLines;
        $glyphs = TerminalText::graphemes($this->ring[$slot]);
        $kept = [];
        $keptBytes = 0;
        for ($index = count($glyphs) - 1; $index >= 0; $index--) {
            $glyph = $glyphs[$index];
            $glyphBytes = strlen($glyph);
            if ($keptBytes + $glyphBytes > $this->maxBytes) {
                break;
            }
            $kept[] = $glyph;
            $keptBytes += $glyphBytes;
        }
        $removed = count($glyphs) - count($kept);
        $this->ring[$slot] = implode('', array_reverse($kept));
        $this->storedBytes = $keptBytes;
        $this->tailColumns = count($kept);
        $this->cursorColumn = max(0, $this->cursorColumn - $removed);
    }

    /** Consume input in bounded windows without materialising a grapheme array for it all. */
    private function consumeText(string $text): void
    {
        if (preg_match('/^[\x20-\x7E]*$/D', $text) === 1) {
            $length = strlen($text);
            for ($offset = 0; $offset < $length; $offset++) {
                $this->consumeGrapheme($text[$offset]);
            }

            return;
        }

        $offset = 0;
        $length = strlen($text);
        $carry = '';
        while ($offset < $length) {
            $chunk = mb_strcut($text, $offset, self::INPUT_CHUNK_BYTES, 'UTF-8');
            if ($chunk === '') {
                // Keep TerminalText's malformed-input contract: a visible fallback,
                // rather than leaking an invalid sequence into the cell model.
                $this->consumeGrapheme('?');

                return;
            }
            $offset += strlen($chunk);
            $matches = preg_match_all('/\X/u', $carry . $chunk, $graphemes);
            if ($matches === false) {
                $this->consumeGrapheme('?');

                return;
            }
            $pending = array_pop($graphemes[0]) ?? '';
            foreach ($graphemes[0] as $grapheme) {
                $this->consumeGrapheme($grapheme);
            }
            $carry = $pending;
        }
        if ($carry !== '') {
            $this->consumeGrapheme($carry);
        }
    }

    private function consumeGrapheme(string $grapheme): void
    {
        if ($this->pendingCarriageReturn) {
            $this->pendingCarriageReturn = false;
            if ($grapheme === "\n") {
                $this->newLine();

                return;
            }
        }

        match ($grapheme) {
            "\n" => $this->newLine(),
            "\r\n" => $this->newLine(),
            "\r" => $this->carriageReturn(),
            "\t" => $this->tab(),
            "\x08" => $this->backspace(),
            default => $this->putGlyph($grapheme),
        };
    }

    private function cursorVisualRow(): int
    {
        $beforeTail = $this->visualRowsForEarlierLines();
        if (! $this->wrap) {
            return $beforeTail;
        }

        return $beforeTail + intdiv($this->cursorColumn, max(1, $this->bounds->width()));
    }

    private function visualRowsForEarlierLines(): int
    {
        $rows = 0;
        $width = max(1, $this->bounds->width());
        $lines = $this->scrollback();
        array_pop($lines);
        foreach ($lines as $line) {
            $glyphCount = max(1, TerminalText::length($line));
            $rows += $this->wrap ? (int) ceil($glyphCount / $width) : 1;
        }

        return $rows;
    }
}
