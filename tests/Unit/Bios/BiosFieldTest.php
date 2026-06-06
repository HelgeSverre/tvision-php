<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Bios\BiosField;
use HelgeSverre\TurboVision\Examples\Bios\FieldKind;

// ── FieldKind ──────────────────────────────────────────────────────────────

test('FieldKind Info is not editable', function (): void {
    $f = new BiosField('Label', FieldKind::Info, 'val');
    expect($f->editable())->toBeFalse();
});

test('FieldKind Text is editable', function (): void {
    $f = new BiosField('Label', FieldKind::Text, 'hello');
    expect($f->editable())->toBeTrue();
});

test('FieldKind Number is editable', function (): void {
    $f = new BiosField('Label', FieldKind::Number, '42');
    expect($f->editable())->toBeTrue();
});

test('FieldKind Cycle is not editable', function (): void {
    $f = new BiosField('Label', FieldKind::Cycle, 'Enabled', ['Enabled', 'Disabled']);
    expect($f->editable())->toBeFalse();
});

test('FieldKind Action is not editable', function (): void {
    $f = new BiosField('Label', FieldKind::Action, '');
    expect($f->editable())->toBeFalse();
});

// ── Cycle ──────────────────────────────────────────────────────────────────

test('cycle forward increments optionIndex', function (): void {
    $f = new BiosField('Mode', FieldKind::Cycle, 'Auto', ['Auto', 'Full', 'Silent']);
    $f->cycle(1);
    expect($f->optionIndex)->toBe(1)
        ->and($f->value)->toBe('Full');
});

test('cycle wraps forward past end', function (): void {
    $f = new BiosField('Mode', FieldKind::Cycle, 'Silent', ['Auto', 'Full', 'Silent'], 2);
    $f->cycle(1);
    expect($f->optionIndex)->toBe(0)
        ->and($f->value)->toBe('Auto');
});

test('cycle backward decrements optionIndex', function (): void {
    $f = new BiosField('Mode', FieldKind::Cycle, 'Full', ['Auto', 'Full', 'Silent'], 1);
    $f->cycle(-1);
    expect($f->optionIndex)->toBe(0)
        ->and($f->value)->toBe('Auto');
});

test('cycle wraps backward past start', function (): void {
    $f = new BiosField('Mode', FieldKind::Cycle, 'Auto', ['Auto', 'Full', 'Silent'], 0);
    $f->cycle(-1);
    expect($f->optionIndex)->toBe(2)
        ->and($f->value)->toBe('Silent');
});

// ── Text editing ────────────────────────────────────────────────────────────

test('insertChar appends to value', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'abc');
    $f->setCursorToEnd();
    $f->insertChar('d');
    expect($f->value)->toBe('abcd');
});

test('insertChar inserts at mid-cursor', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'ac');
    $f->setCursorToEnd();
    $f->setCursor(1);
    $f->insertChar('b');
    expect($f->value)->toBe('abc');
});

test('backspace removes last char', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'hello');
    $f->setCursorToEnd();
    $f->backspace();
    expect($f->value)->toBe('hell');
});

test('backspace at position 0 does nothing', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'hi');
    $f->setCursor(0);
    $f->backspace();
    expect($f->value)->toBe('hi');
});

test('backspace on empty string does nothing', function (): void {
    $f = new BiosField('Host', FieldKind::Text, '');
    $f->setCursorToEnd();
    $f->backspace();
    expect($f->value)->toBe('');
});

// ── Number field ────────────────────────────────────────────────────────────

test('number field accepts digit insertions', function (): void {
    $f = new BiosField('Multiplier', FieldKind::Number, '8');
    $f->setCursorToEnd();
    $f->insertChar('4');
    expect($f->value)->toBe('84');
});

test('number field rejects non-digit insertions', function (): void {
    $f = new BiosField('Multiplier', FieldKind::Number, '8');
    $f->setCursorToEnd();
    $f->insertChar('x');
    expect($f->value)->toBe('8');
});

test('number field rejects letter insertions', function (): void {
    $f = new BiosField('Multiplier', FieldKind::Number, '');
    $f->setCursorToEnd();
    $f->insertChar('a');
    $f->insertChar('5');
    expect($f->value)->toBe('5');
});

// ── Snapshot / restore ──────────────────────────────────────────────────────

test('snapshot and restore work', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'original');
    $f->setCursorToEnd();
    $snap = $f->snapshot();
    $f->insertChar('X');
    expect($f->value)->toBe('originalX');
    $f->restore($snap);
    expect($f->value)->toBe('original');
});

// ── Cursor position accessors ───────────────────────────────────────────────

test('setCursorToEnd places cursor at length', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'hello');
    $f->setCursorToEnd();
    expect($f->cursorPos())->toBe(5);
});

test('setCursor clamps to valid range', function (): void {
    $f = new BiosField('Host', FieldKind::Text, 'hello');
    $f->setCursorToEnd();
    $f->setCursor(100);
    expect($f->cursorPos())->toBe(5);
    $f->setCursor(-5);
    expect($f->cursorPos())->toBe(0);
});
