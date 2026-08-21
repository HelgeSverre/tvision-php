<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Editors\EditorBuffer;

test('gap buffer moves and edits by Unicode grapheme rather than byte', function (): void {
    $buffer = new EditorBuffer('a👩‍💻b');

    expect($buffer->length())->toBe(3)
        ->and($buffer->cursor())->toBe(3);

    $buffer->moveTo(1);
    $buffer->insert('é');
    expect($buffer->text())->toBe('aé👩‍💻b')
        ->and($buffer->cursor())->toBe(2);

    expect($buffer->deleteForward(1))->toBe('👩‍💻')
        ->and($buffer->text())->toBe('aéb');
});

test('gap buffer slices and deletes in document order', function (): void {
    $buffer = new EditorBuffer('abcdef');
    $buffer->moveTo(2);

    expect($buffer->deleteForward(2))->toBe('cd')
        ->and($buffer->text())->toBe('abef')
        ->and($buffer->slice(1, 2))->toBe('be');

    expect($buffer->deleteBackward(2))->toBe('ab')
        ->and($buffer->text())->toBe('ef');
});
