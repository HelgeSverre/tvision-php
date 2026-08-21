<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Validators\FilterValidator;
use HelgeSverre\TurboVision\Validators\RangeValidator;
use HelgeSverre\TurboVision\Validators\PictureValidator;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;

final class InputLineRootForTest extends Group
{
    public function __construct(private readonly Screen $testScreen)
    {
        parent::__construct(Rect::of(0, 0, $testScreen->cols(), $testScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->testScreen;
    }
}

function inputLine(): InputLine
{
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10);
    $line->setState(State::Selected, true);

    return $line;
}

function inputCharacter(InputLine $line, string $character, int $modifiers = 0): Event
{
    $event = Event::keyDown(new KeyDownEvent(0, $character, $modifiers));
    $line->handleEvent($event);

    return $event;
}

test('edits Unicode graphemes, navigation, selection, insert and overwrite modes', function (): void {
    $line = inputLine();
    inputCharacter($line, 'a');
    inputCharacter($line, 'é');
    inputCharacter($line, 'c');

    $line->handleEvent(Event::key(Key::Left));
    inputCharacter($line, 'B');
    expect($line->text())->toBe('aéBc');

    $line->handleEvent(Event::key(Key::Left));
    $line->handleEvent(Event::key(Key::Insert));
    inputCharacter($line, 'X');
    expect($line->text())->toBe('aéXc');

    $line->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value, modifiers: KeyModifier::Shift)));
    expect($line->selStart)->toBe(0)->and($line->selEnd)->toBe(3);
    $line->selectAll();
    inputCharacter($line, 'z');
    expect($line->text())->toBe('z');
});

test('supports copy, cut, paste, clear, and raw terminal Ctrl shortcuts', function (): void {
    $line = inputLine();
    $line->setText('abcdef');
    $line->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value, modifiers: KeyModifier::Shift)));
    $line->handleEvent(Event::command(Cmd::Copy));
    expect(InputLine::clipboard())->toBe('abcdef');

    $line->handleEvent(Event::command(Cmd::Cut));
    expect($line->text())->toBe('');
    $line->handleEvent(Event::command(Cmd::Paste));
    expect($line->text())->toBe('abcdef');

    $line->handleEvent(Event::keyDown(new KeyDownEvent(25))); // Ctrl-Y
    expect($line->text())->toBe('');
    $line->handleEvent(Event::keyDown(new KeyDownEvent(22))); // Ctrl-V
    expect($line->text())->toBe('abcdef');
});

test('filters invalid keystrokes without mutating the edited value', function (): void {
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10, new FilterValidator('0123456789'));
    $line->setState(State::Selected, true);
    inputCharacter($line, '4');
    inputCharacter($line, 'x');

    expect($line->text())->toBe('4')
        ->and($line->valid(Cmd::Ok))->toBeTrue();
});

test('invalid replacement proposals do not delete selected or overwritten text', function (): void {
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10, new FilterValidator('0123456789'));
    $line->setState(State::Selected, true);
    $line->setText('12');
    $line->handleEvent(Event::key(Key::Left));
    $line->handleEvent(Event::key(Key::Insert));
    inputCharacter($line, 'x');
    expect($line->text())->toBe('12')
        ->and($line->curPos)->toBe(1);

    $line->selectAll();
    inputCharacter($line, 'x');
    expect($line->text())->toBe('12')
        ->and($line->selStart)->toBe(0)
        ->and($line->selEnd)->toBe(2);
});

test('deletion commands preserve text and selection when a validator rejects the proposal', function (): void {
    $nonEmpty = new class extends \HelgeSverre\TurboVision\Validators\Validator
    {
        public function isValidInput(string &$input, bool $suppressFill = false): bool
        {
            return $input !== '';
        }
    };
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10, $nonEmpty);
    $line->setState(State::Selected, true);
    $line->setText('1');
    $line->selectAll();
    $line->handleEvent(Event::command(Cmd::Cut));

    expect($line->text())->toBe('1')
        ->and($line->selStart)->toBe(0)
        ->and($line->selEnd)->toBe(1);
});

test('picture auto-fill advances the cursor past inserted literal separators', function (): void {
    $line = new InputLine(Rect::of(0, 0, 12, 1), 12, new PictureValidator('#####-###', true));
    $line->setState(State::Selected, true);
    foreach (str_split('12345678') as $character) {
        inputCharacter($line, $character);
    }

    expect($line->text())->toBe('12345-678')
        ->and($line->curPos)->toBe(9);
});

test('range validators provide typed transfer and reject incomplete or out-of-range data', function (): void {
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10, new RangeValidator(-10, 10));
    $line->setData(-7);
    expect($line->text())->toBe('-7')
        ->and($line->getData())->toBe(-7)
        ->and($line->dataSize())->toBe(PHP_INT_SIZE)
        ->and($line->valid(Cmd::Ok))->toBeTrue();

    $line->setText('11');
    expect($line->getData())->toBe('11')
        ->and($line->valid(Cmd::Ok))->toBeFalse()
        ->and($line->validator()?->lastError)->toContain('-10 to 10');
});

test('range fields allow incomplete edit states while retaining strict final validation', function (): void {
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10, new RangeValidator(10, 20));
    $line->setState(State::Selected, true);

    inputCharacter($line, '1');
    expect($line->text())->toBe('1')
        ->and($line->valid(Cmd::Ok))->toBeFalse();

    inputCharacter($line, '0');
    expect($line->text())->toBe('10')
        ->and($line->valid(Cmd::Ok))->toBeTrue();

    $line->handleEvent(Event::key(Key::Backspace));
    $line->handleEvent(Event::key(Key::Backspace));
    expect($line->text())->toBe('')
        ->and($line->valid(Cmd::Ok))->toBeFalse();
});

test('mouse click and drag position and select by grapheme offsets', function (): void {
    $line = inputLine();
    $line->setText('abcdef');
    $line->handleEvent(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(3, 0))));
    expect($line->curPos)->toBe(2);

    $line->handleEvent(Event::mouse(EventType::MouseMove, new MouseEvent(new Point(6, 0))));
    $line->handleEvent(Event::mouse(EventType::MouseUp, new MouseEvent(new Point(6, 0))));
    expect($line->selStart)->toBe(2)->and($line->selEnd)->toBe(5);
});

test('mounted input lines subscribe to the full mouse mask for drag selection', function (): void {
    $screen = new Screen(new HeadlessDriver(12, 1));
    $screen->init();
    $root = new InputLineRootForTest($screen);
    $line = new InputLine(Rect::of(0, 0, 10, 1), 10);
    $root->insert($line);
    $line->setText('abcdef');

    $root->handleEvent(Event::mouse(EventType::MouseDown, new MouseEvent(new Point(2, 0))));
    $root->handleEvent(Event::mouse(EventType::MouseMove, new MouseEvent(new Point(6, 0))));
    $root->handleEvent(Event::mouse(EventType::MouseUp, new MouseEvent(new Point(6, 0))));

    expect($line->selStart)->toBe(1)
        ->and($line->selEnd)->toBe(5)
        ->and($line->hasMouseCapture())->toBeFalse();
});

test('draws scroll indicators, selection attr, and exposes the requested cursor position', function (): void {
    $screen = new Screen(new HeadlessDriver(12, 1));
    $screen->init();
    $root = new InputLineRootForTest($screen);
    $line = new InputLine(Rect::of(1, 0, 11, 1), 12);
    $root->insert($line);
    $line->setText('abcdefghijk');
    $line->setState(State::Selected, true);
    $line->handleEvent(Event::key(Key::End));
    $line->draw();

    expect($screen->back()->rows()[0])->toContain('‹')
        ->and($line->cursorPosition())->toEqual(new Point(10, 0));
});
