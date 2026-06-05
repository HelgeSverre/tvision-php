<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Drawing\Cell;
use HelgeSverre\TurboVision\Rendering\HtmlRenderer;

test('renders each cell with its CGA foreground and background colours', function (): void {
    $buf = new Buffer(2, 1);
    $buf->put(0, 0, new Cell('A', 0x71)); // fg Blue(1) on bg LightGray(7)
    $buf->put(1, 0, new Cell('B', 0x07)); // fg LightGray(7) on bg Black(0)

    $html = (new HtmlRenderer())->render($buf);

    expect($html)->toContain('charset')
        ->and($html)->toContain('#0000aa')   // CGA blue fg
        ->and($html)->toContain('#aaaaaa')   // CGA light-gray (bg of A, fg of B)
        ->and($html)->toContain('#000000')   // CGA black (bg of B)
        ->and($html)->toContain('>A<')
        ->and($html)->toContain('>B<');
});

test('HTML-escapes special characters and keeps UTF-8 glyphs', function (): void {
    $buf = new Buffer(2, 1);
    $buf->put(0, 0, new Cell('<', 0x07));
    $buf->put(1, 0, new Cell('░', 0x71)); // U+2591 must survive as UTF-8

    $html = (new HtmlRenderer())->render($buf);

    expect($html)->toContain('&lt;')
        ->and($html)->toContain('░');
});
