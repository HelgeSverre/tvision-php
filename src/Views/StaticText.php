<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Non-interactive fixed text (faithful to TStaticText). Word-wraps to the view width
 * and supports explicit alignment. The classic leading \003 centering marker remains
 * accepted for compatibility.
 */
class StaticText extends View
{
    public function __construct(
        Rect $bounds,
        protected string $text,
        protected TextAlignment $alignment = TextAlignment::Left,
    ) {
        parent::__construct($bounds);

        if (str_starts_with($this->text, "\003")) {
            $this->text = substr($this->text, 1);
            $this->alignment = TextAlignment::Center;
        }
    }

    public static function centered(Rect $bounds, string $text): self
    {
        return new self($bounds, $text, alignment: TextAlignment::Center);
    }

    /** StaticText uses palette index 1 -> its text color. */
    public function getPalette(): ?Palette
    {
        return new Palette([1 => 0x06]);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $attr = $this->mapColor(1);

        // Blank the whole extent first.
        $blank = new DrawBuffer($width);
        $blank->moveChar(0, ' ', $attr, $width);
        for ($y = 0; $y < $height; $y++) {
            $this->writeLine(0, $y, $width, 1, $blank);
        }

        $lines = $this->layout($width);
        foreach ($lines as $y => $line) {
            if ($y >= $height) {
                break;
            }

            $len = TerminalText::length($line);
            $x = match ($this->alignment) {
                TextAlignment::Left => 0,
                TextAlignment::Center => intdiv(max(0, $width - $len), 2),
                TextAlignment::Right => max(0, $width - $len),
            };

            $b = new DrawBuffer($width);
            $b->moveChar(0, ' ', $attr, $width);
            $b->moveStr($x, $line, $attr);
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }

    /** @return list<string> */
    private function layout(int $width): array
    {
        if ($width <= 0) {
            return [];
        }

        /** @var list<string> $lines */
        $lines = [];
        $sourceLines = preg_split('/\R/u', $this->text) ?: [];

        foreach ($sourceLines as $sourceLine) {
            if ($sourceLine === '') {
                $lines[] = '';

                continue;
            }

            $words = preg_split('/\s+/u', trim($sourceLine)) ?: [];
            $current = '';

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if (TerminalText::length($candidate) <= $width) {
                    $current = $candidate;
                } else {
                    if ($current !== '') {
                        $lines[] = $current;
                    }
                    while (TerminalText::length($word) > $width) {
                        $lines[] = TerminalText::slice($word, 0, $width);
                        $word = TerminalText::slice($word, $width);
                    }
                    $current = $word;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines;
    }
}
