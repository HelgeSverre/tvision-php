<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

use HelgeSverre\TurboVision\Support\IntMath;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use OutOfRangeException;

/**
 * A Unicode-grapheme gap buffer.
 *
 * The two arrays are the text before and after the gap; the latter is held in
 * reverse order, so moving the gap one grapheme in either direction is O(1).
 * This gives editor operations their expected local-editing behaviour without
 * exposing byte offsets to callers.
 */
final class EditorBuffer
{
    /** @var list<string> */
    private array $before = [];

    /** @var list<string> Reversed: array_pop() is the next grapheme. */
    private array $after = [];

    /** Increments only when document content changes, not when the gap moves. */
    private int $version = 0;

    public function __construct(string $text = '')
    {
        $this->setText($text);
    }

    public function setText(string $text): void
    {
        $this->before = TerminalText::graphemes($text);
        $this->after = [];
        $this->version++;
    }

    public function length(): int
    {
        return count($this->before) + count($this->after);
    }

    public function cursor(): int
    {
        return count($this->before);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function text(): string
    {
        return implode('', $this->before) . implode('', array_reverse($this->after));
    }

    public function moveTo(int $position): void
    {
        $position = IntMath::clamp($position, 0, $this->length());
        while (count($this->before) > $position) {
            $moved = array_pop($this->before);
            \assert($moved !== null);
            $this->after[] = $moved;
        }
        while (count($this->before) < $position && $this->after !== []) {
            $this->before[] = array_pop($this->after);
        }
    }

    public function insert(string $text): int
    {
        $graphemes = TerminalText::graphemes($text);
        if ($graphemes !== []) {
            array_push($this->before, ...$graphemes);
            $this->version++;
        }

        return count($graphemes);
    }

    /** Delete $length graphemes from the current gap position and return them. */
    public function deleteForward(int $length): string
    {
        if ($length <= 0 || $this->after === []) {
            return '';
        }

        $deleted = [];
        while ($length-- > 0 && $this->after !== []) {
            $deleted[] = array_pop($this->after);
        }

        $this->version++;

        return implode('', $deleted);
    }

    /** Delete up to $length graphemes immediately before the current gap. */
    public function deleteBackward(int $length): string
    {
        if ($length <= 0 || $this->before === []) {
            return '';
        }

        $deleted = [];
        while ($length-- > 0 && $this->before !== []) {
            $deleted[] = array_pop($this->before);
        }

        $this->version++;

        return implode('', array_reverse($deleted));
    }

    public function slice(int $offset, ?int $length = null): string
    {
        $offset = IntMath::clamp($offset, 0, $this->length());
        $available = $this->length() - $offset;
        $length = min($available, max(0, $length ?? $available));
        if ($length <= 0) {
            return '';
        }
        $end = $offset + $length;
        $result = [];
        for ($position = $offset; $position < $end; $position++) {
            $result[] = $this->graphemeAt($position);
        }

        return implode('', $result);
    }

    public function graphemeAt(int $offset): string
    {
        if ($offset < 0 || $offset >= $this->length()) {
            throw new OutOfRangeException('Grapheme offset is outside the editor buffer.');
        }

        if ($offset < count($this->before)) {
            return $this->before[$offset];
        }

        return $this->after[$this->length() - 1 - $offset];
    }
}
