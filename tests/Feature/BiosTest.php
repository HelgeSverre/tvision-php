<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../../examples/php/bios.php';

use HelgeSverre\TurboVision\Examples\Bios\BiosApp;
use HelgeSverre\TurboVision\Examples\Bios\FieldKind;

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Build an 80×25 headless BiosApp, boot + draw. */
function biosApp(): BiosApp
{
    $driver = new HeadlessDriver(80, 25);
    $app    = new BiosApp(new Screen($driver));
    $app->bootForTest();
    $app->drawAndFlushForTest();

    return $app;
}

/** Feed a special-key event through the app. */
function feedKey(BiosApp $app, Key $key): void
{
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent($key->value)));
}

/** Feed a printable-char event through the app. */
function feedChar(BiosApp $app, string $char): void
{
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord($char), $char)));
}

// ── Initial render ────────────────────────────────────────────────────────────

test('BIOS renders the title bar', function (): void {
    $app  = biosApp();
    $rows = $app->backRowsForTest();
    $text = implode("\n", $rows);

    expect($text)->toContain('BIOS Setup Utility');
});

test('BIOS renders the Main tab name in the tab bar', function (): void {
    $app  = biosApp();
    $rows = $app->backRowsForTest();
    $text = implode("\n", $rows);

    expect($text)->toContain('Main');
});

test('BIOS renders all tab names', function (): void {
    $app  = biosApp();
    $text = implode("\n", $app->backRowsForTest());

    expect($text)->toContain('Main')
        ->and($text)->toContain('Advanced')
        ->and($text)->toContain('Boot')
        ->and($text)->toContain('Security')
        ->and($text)->toContain('Save & Exit');
});

test('BIOS renders Main tab fields on startup', function (): void {
    $app  = biosApp();
    $text = implode("\n", $app->backRowsForTest());

    expect($text)->toContain('System Date')
        ->and($text)->toContain('System Time')
        ->and($text)->toContain('Hostname');
});

test('BIOS renders the key legend at the bottom', function (): void {
    $app  = biosApp();
    $text = implode("\n", $app->backRowsForTest());

    expect($text)->toContain('F10')
        ->and($text)->toContain('Esc');
});

// ── Tab switching ─────────────────────────────────────────────────────────────

test('Right arrow switches to Advanced tab', function (): void {
    $app = biosApp();

    feedKey($app, Key::Right);

    expect($app->biosScreen()->tabName())->toBe('Advanced');
});

test('Right arrow twice switches to Boot tab', function (): void {
    $app = biosApp();

    feedKey($app, Key::Right);
    feedKey($app, Key::Right);

    expect($app->biosScreen()->tabName())->toBe('Boot');
});

test('Left arrow wraps from Main to last tab', function (): void {
    $app = biosApp();

    feedKey($app, Key::Left);

    // Should wrap to the last tab (Save & Exit)
    expect($app->biosScreen()->tabName())->toBe('Save & Exit');
});

test('Right switches tab and re-renders Advanced fields', function (): void {
    $app = biosApp();
    feedKey($app, Key::Right);
    $text = implode("\n", $app->backRowsForTest());

    expect($text)->toContain('Virtualization')
        ->and($text)->toContain('Hyper-Threading');
});

// ── Row navigation ────────────────────────────────────────────────────────────

test('Down arrow moves to next field', function (): void {
    $app = biosApp();

    $before = $app->biosScreen()->currentField()->label;
    feedKey($app, Key::Down);
    $after = $app->biosScreen()->currentField()->label;

    expect($before)->not->toBe($after);
});

test('Down then Up returns to original field', function (): void {
    $app = biosApp();

    $original = $app->biosScreen()->currentField()->label;
    feedKey($app, Key::Down);
    feedKey($app, Key::Up);
    $back = $app->biosScreen()->currentField()->label;

    expect($back)->toBe($original);
});

// ── Cycle fields ──────────────────────────────────────────────────────────────

test('plus key cycles a Cycle field forward', function (): void {
    $app = biosApp();
    // Navigate to Advanced tab which has Cycle fields
    feedKey($app, Key::Right);

    $field = $app->biosScreen()->currentField();
    expect($field->kind)->toBe(FieldKind::Cycle);

    $before = $field->value;
    feedChar($app, '+');
    $after = $field->value;

    expect($after)->not->toBe($before);
});

test('minus key cycles a Cycle field backward', function (): void {
    $app = biosApp();
    feedKey($app, Key::Right); // Advanced

    $field  = $app->biosScreen()->currentField();
    $before = $field->value;
    feedChar($app, '+'); // move forward
    feedChar($app, '-'); // move back
    $back   = $field->value;

    expect($back)->toBe($before);
});

test('Enter cycles a Cycle field forward', function (): void {
    $app = biosApp();
    feedKey($app, Key::Right); // Advanced

    $field  = $app->biosScreen()->currentField();
    $before = $field->value;
    feedKey($app, Key::Enter);
    $after  = $field->value;

    expect($after)->not->toBe($before);
});

// ── Text editing ──────────────────────────────────────────────────────────────

test('Enter on a Text field starts editing', function (): void {
    $app = biosApp();
    // Main tab row 0 = System Date (Text)
    expect($app->biosScreen()->currentField()->kind)->toBe(FieldKind::Text);

    feedKey($app, Key::Enter);

    expect($app->biosScreen()->isEditing())->toBeTrue();
});

test('typing while editing appends chars', function (): void {
    $app   = biosApp();
    $field = $app->biosScreen()->currentField();
    expect($field->kind)->toBe(FieldKind::Text);

    feedKey($app, Key::Enter); // start editing
    feedChar($app, 'X');

    expect($field->value)->toContain('X');
});

test('Enter again commits editing', function (): void {
    $app = biosApp();
    feedKey($app, Key::Enter); // start editing
    feedChar($app, 'Z');
    feedKey($app, Key::Enter); // commit

    expect($app->biosScreen()->isEditing())->toBeFalse();
});

test('Esc cancels editing and reverts value', function (): void {
    $app   = biosApp();
    $field = $app->biosScreen()->currentField();
    $original = $field->value;

    feedKey($app, Key::Enter); // start editing
    feedChar($app, 'Z');
    feedKey($app, Key::Esc); // cancel

    expect($app->biosScreen()->isEditing())->toBeFalse()
        ->and($field->value)->toBe($original);
});

// ── F10 save ──────────────────────────────────────────────────────────────────

test('F10 sets a status message without quitting', function (): void {
    $app = biosApp();
    feedKey($app, Key::F10);

    expect($app->biosScreen()->statusMessage())->not->toBeEmpty()
        ->and($app->ended())->toBeFalse();
});

// ── Esc quit ─────────────────────────────────────────────────────────────────

test('Esc when not editing queues Quit and ends the app', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app    = new BiosApp(new Screen($driver));
    // Feed Esc as scripted input so run() picks it up
    $driver->feedInput("\x1b"); // ESC byte
    $code = $app->run();

    expect($code)->toBe(0)
        ->and($app->ended())->toBeTrue();
});
