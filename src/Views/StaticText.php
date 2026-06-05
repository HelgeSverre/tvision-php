<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Geometry\Rect;

/**
 * Non-interactive fixed text (faithful to TStaticText). Word-wraps to the view width
 * and supports the TV centering control char \003 (a line starting with it is centered).
 */
class StaticText extends View
{
    public function __construct(Rect $bounds, protected string $text)
    {
        parent::__construct($bounds);
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
            $centered = false;
            if ($line !== '' && $line[0] === "\003") {
                $centered = true;
                $line = substr($line, 1);
            }

            $len = mb_strlen($line);
            $x = $centered ? intdiv(max(0, $width - $len), 2) : 0;

            $b = new DrawBuffer($width);
            $b->moveChar(0, ' ', $attr, $width);
            $b->moveStr($x, $line, $attr);
            $this->writeLine(0, $y, $width, 1, $b);
        }
    }

    /**
     * Word-wrap $text to $width. A \003 prefix is preserved on its line so draw()
     * can center it.
     *
     * @return list<string>
     */
    private function layout(int $width): array
    {
        if ($width <= 0) {
            return [];
        }

        $centerPrefix = '';
        $body = $this->text;
        if ($body !== '' && $body[0] === "\003") {
            $centerPrefix = "\003";
            $body = substr($body, 1);
        }

        $words = preg_split('/\s+/', trim($body)) ?: [];
        /** @var list<string> $lines */
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $width) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $centerPrefix . $current;
                }
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $centerPrefix . $current;
        }

        return $lines;
    }
}
