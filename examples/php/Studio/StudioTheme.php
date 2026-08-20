<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

use InvalidArgumentException;

final readonly class StudioTheme
{
    public function __construct(
        public string $name,
        public int $canvas = 0x07,
        public int $primary = 0x0F,
        public int $muted = 0x08,
        public int $grid = 0x08,
        public int $accent = 0x0B,
        public int $secondary = 0x0D,
        public int $success = 0x0A,
        public int $warning = 0x0E,
        public int $error = 0x0C,
        public int $shadow = 0x08,
        public string $gridGlyph = '·',
        public string $shadowGlyph = '░',
        public string $focusGlyph = '+',
    ) {
        $attributes = [
            $canvas,
            $primary,
            $muted,
            $grid,
            $accent,
            $secondary,
            $success,
            $warning,
            $error,
            $shadow,
        ];
        foreach ($attributes as $attribute) {
            if ($attribute < 0 || $attribute > 0x0F) {
                throw new InvalidArgumentException('Studio themes may only use foreground terminal colors.');
            }
        }
        foreach ([$gridGlyph, $shadowGlyph, $focusGlyph] as $glyph) {
            if (mb_strlen($glyph) !== 1 || mb_strwidth($glyph) !== 1) {
                throw new InvalidArgumentException('Studio theme glyphs must occupy exactly one terminal column.');
            }
        }
    }

    /** @return list<self> */
    public static function presets(): array
    {
        return [
            new self('Graphite'),
            new self(
                'Ultraviolet',
                canvas: 0x05,
                primary: 0x0D,
                muted: 0x08,
                grid: 0x05,
                accent: 0x0D,
                secondary: 0x0B,
                success: 0x0B,
                warning: 0x0E,
                error: 0x0C,
                shadow: 0x05,
                gridGlyph: ':',
                shadowGlyph: '▒',
                focusGlyph: '*',
            ),
            new self(
                'Amber',
                canvas: 0x06,
                primary: 0x0E,
                muted: 0x08,
                grid: 0x06,
                accent: 0x0E,
                secondary: 0x0C,
                success: 0x0A,
                warning: 0x0F,
                error: 0x0C,
                shadow: 0x06,
                gridGlyph: '.',
                shadowGlyph: '▓',
                focusGlyph: '#',
            ),
        ];
    }
}
