<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Geometry\Point;

/**
 * An in-memory help topic compatible with the useful THelpTopic semantics. Text is
 * stored as paragraphs and cross references use grapheme offsets, avoiding the old
 * byte-oriented H32 stream format's encoding and platform-width limitations.
 */
final class HelpTopic
{
    /** @var list<HelpParagraph> */
    private array $paragraphs;

    /** @var list<CrossRef> */
    private array $crossRefs;

    private int $width = 0;

    /**
     * @param list<HelpParagraph> $paragraphs
     * @param list<CrossRef> $crossRefs
     */
    public function __construct(array $paragraphs = [], array $crossRefs = [])
    {
        $this->paragraphs = $paragraphs;
        $this->crossRefs = $crossRefs;
    }

    public function addParagraph(HelpParagraph $paragraph): void
    {
        $this->paragraphs[] = $paragraph;
    }

    public function addCrossRef(CrossRef $reference): void
    {
        $this->crossRefs[] = $reference;
    }

    /** @return list<HelpParagraph> */
    public function paragraphs(): array
    {
        return $this->paragraphs;
    }

    /** @return list<CrossRef> */
    public function crossRefs(): array
    {
        return $this->crossRefs;
    }

    public function setWidth(int $width): void
    {
        $this->width = max(1, $width);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getNumCrossRefs(): int
    {
        return count($this->crossRefs);
    }

    public function getCrossRef(int $index): CrossRef
    {
        if (! isset($this->crossRefs[$index])) {
            throw new \OutOfBoundsException("No help cross-reference at index {$index}.");
        }

        return $this->crossRefs[$index];
    }

    /** @return list<string> */
    public function lines(?int $width = null): array
    {
        $width ??= $this->width;
        if ($width < 1) {
            return [];
        }

        return array_map(static fn (array $segment): string => $segment['text'], $this->layoutSegments($width));
    }

    public function numLines(?int $width = null): int
    {
        return count($this->lines($width));
    }

    public function getLine(int $line, ?int $width = null): string
    {
        return $this->lines($width)[$line] ?? '';
    }

    /** Return the 0-based rendered cell location for a cross reference. */
    public function crossRefLocation(int $index, ?int $width = null): Point
    {
        $reference = $this->getCrossRef($index);
        $width ??= $this->width;
        if ($width < 1) {
            return new Point(0, 0);
        }

        foreach ($this->layoutSegments($width) as $line => $segment) {
            $x = array_search($reference->offset, $segment['offsets'], true);
            if ($x !== false) {
                return new Point($x, $line);
            }
        }

        return new Point(0, count($this->layoutSegments($width)));
    }

    /** @return array{paragraphs:list<array{text:string,wrap:bool}>,crossRefs:list<array{ref:int,offset:int,length:int,label:?string}>} */
    public function toArray(): array
    {
        return [
            'paragraphs' => array_map(static fn (HelpParagraph $p): array => $p->toArray(), $this->paragraphs),
            'crossRefs' => array_map(static fn (CrossRef $r): array => $r->toArray(), $this->crossRefs),
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $paragraphs = [];
        $rawParagraphs = $data['paragraphs'] ?? [];
        if (! is_array($rawParagraphs)) {
            throw new \UnexpectedValueException('A help topic paragraphs value must be an array.');
        }
        foreach ($rawParagraphs as $paragraph) {
            if (! is_array($paragraph) || ! is_string($paragraph['text'] ?? null)) {
                throw new \UnexpectedValueException('A help paragraph needs text.');
            }
            $wrap = $paragraph['wrap'] ?? true;
            if (! is_bool($wrap)) {
                throw new \UnexpectedValueException('A help paragraph wrap value must be boolean.');
            }
            $paragraphs[] = new HelpParagraph($paragraph['text'], $wrap);
        }
        $crossRefs = [];
        $rawReferences = $data['crossRefs'] ?? [];
        if (! is_array($rawReferences)) {
            throw new \UnexpectedValueException('A help topic crossRefs value must be an array.');
        }
        foreach ($rawReferences as $reference) {
            if (! is_array($reference)
                || ! is_int($reference['ref'] ?? null)
                || ! is_int($reference['offset'] ?? null)
                || ! is_int($reference['length'] ?? null)
                || (isset($reference['label']) && ! is_string($reference['label']))
            ) {
                throw new \UnexpectedValueException('A help cross-reference is invalid.');
            }
            $crossRefs[] = new CrossRef(
                $reference['ref'],
                $reference['offset'],
                $reference['length'],
                $reference['label'] ?? null,
            );
        }

        return new self($paragraphs, $crossRefs);
    }

    /** @return list<array{text:string,offsets:list<int>}> */
    private function layoutSegments(int $width): array
    {
        $result = [];
        $baseOffset = 0;
        foreach ($this->paragraphs as $paragraph) {
            $text = str_replace(["\r\n", "\r"], "\n", $paragraph->text);
            $parts = explode("\n", $text);
            foreach ($parts as $partIndex => $sourceLine) {
                $chars = TerminalText::graphemes($sourceLine);
                if (! $paragraph->wrap) {
                    $result[] = ['text' => $sourceLine, 'offsets' => array_map(static fn (int $i): int => $baseOffset + $i, array_keys($chars))];
                } else {
                    $tokens = preg_split('/(\s+)/u', $sourceLine, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
                    $currentChars = [];
                    $currentOffsets = [];
                    $sourceOffset = 0;
                    foreach ($tokens as $token) {
                        $tokenChars = TerminalText::graphemes($token);
                        $tokenOffsets = [];
                        foreach ($tokenChars as $i => $_) {
                            $tokenOffsets[] = $baseOffset + $sourceOffset + $i;
                        }
                        $sourceOffset += count($tokenChars);
                        if ($tokenChars === []) {
                            continue;
                        }
                        if (count($currentChars) + count($tokenChars) <= $width || $currentChars === []) {
                            array_push($currentChars, ...$tokenChars);
                            array_push($currentOffsets, ...$tokenOffsets);
                            while (count($currentChars) > $width) {
                                $result[] = ['text' => implode('', array_slice($currentChars, 0, $width)), 'offsets' => array_slice($currentOffsets, 0, $width)];
                                $currentChars = array_slice($currentChars, $width);
                                $currentOffsets = array_slice($currentOffsets, $width);
                            }
                            continue;
                        }
                        while ($currentChars !== [] && preg_match('/\s/u', $currentChars[array_key_last($currentChars)]) === 1) {
                            array_pop($currentChars);
                            array_pop($currentOffsets);
                        }
                        $result[] = ['text' => implode('', $currentChars), 'offsets' => $currentOffsets];
                        while ($tokenChars !== [] && preg_match('/\s/u', $tokenChars[0]) === 1) {
                            array_shift($tokenChars);
                            array_shift($tokenOffsets);
                        }
                        $currentChars = $tokenChars;
                        $currentOffsets = $tokenOffsets;
                    }
                    if ($currentChars !== [] || $sourceLine === '') {
                        while ($currentChars !== [] && preg_match('/\s/u', $currentChars[array_key_last($currentChars)]) === 1) {
                            array_pop($currentChars);
                            array_pop($currentOffsets);
                        }
                        $result[] = ['text' => implode('', $currentChars), 'offsets' => $currentOffsets];
                    }
                }
                $baseOffset += count($chars);
                if ($partIndex < count($parts) - 1) {
                    $baseOffset++;
                }
            }
        }

        return $result;
    }
}
