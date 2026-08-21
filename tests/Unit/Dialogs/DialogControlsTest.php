<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\ListBox;
use HelgeSverre\TurboVision\Dialogs\MessageBox;
use HelgeSverre\TurboVision\Dialogs\MsgBoxFlag;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Dialogs\SItem;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;

test('SItem builds an ordered compatibility linked list', function (): void {
    expect(SItem::list('one', 'two', 'three')?->values())->toBe(['one', 'two', 'three']);
});

test('a modal dialog turns escape into a cancel result', function (): void {
    $host = new Group(Rect::of(0, 0, 80, 25));
    $host->putEvent(Event::key(Key::Esc));

    expect($host->execView(new Dialog(Rect::of(10, 5, 40, 15), 'Test')))->toBe(Cmd::Cancel);
});

test('a modal dialog validates an accepted command exactly once', function (): void {
    $host = new Group(Rect::of(0, 0, 80, 25));
    $dialog = new class(Rect::of(10, 5, 40, 15), 'Validate once') extends Dialog {
        public int $validationCalls = 0;

        public function valid(int $command): bool
        {
            $this->validationCalls++;

            return parent::valid($command);
        }
    };
    $host->putEvent(Event::command(Cmd::Ok));

    expect($host->execView($dialog))->toBe(Cmd::Ok)
        ->and($dialog->validationCalls)->toBe(1);
});

test('a default button handles cmDefault and queues its command', function (): void {
    $host = new Group(Rect::of(0, 0, 80, 25));
    $dialog = new Dialog(Rect::of(10, 5, 40, 15), 'Test');
    $button = new Button(Rect::of(10, 7, 20, 9), '~O~K', Cmd::Ok, ButtonFlag::Default);
    $dialog->insert($button);
    $host->putEvent(Event::broadcast(Cmd::Default));

    expect($host->execView($dialog))->toBe(Cmd::Ok);
});

test('dialog mnemonics require Alt and never consume ordinary input text', function (): void {
    $host = new Group(Rect::of(0, 0, 80, 25));
    $dialog = new Dialog(Rect::of(10, 5, 50, 15), 'Mnemonic');
    $input = new InputLine(Rect::of(2, 2, 28, 3), 40);
    $dialog->insert(new Label(Rect::of(2, 1, 20, 2), '~F~ile name', $input));
    $dialog->insert($input);
    $dialog->insert(new Button(Rect::of(28, 2, 38, 4), '~O~pen', Cmd::Ok));
    $dialog->setCurrent($input);
    $host->insert($dialog);

    foreach (str_split('folder') as $character) {
        $dialog->handleEvent(Event::keyDown(new KeyDownEvent(ord($character), $character)));
    }
    expect($input->text())->toBe('folder')
        ->and($host->pumpEvent())->toBeNull();

    $dialog->handleEvent(Event::key(Key::AltO, KeyModifier::Alt));
    expect($host->pumpEvent()?->isCommand(Cmd::Ok))->toBeTrue();

    $dialog->handleEvent(Event::keyDown(new KeyDownEvent(ord('o'), 'o', KeyModifier::Alt)));
    expect($host->pumpEvent()?->isCommand(Cmd::Ok))->toBeTrue();
});

test('MessageBox builds a standard default-button dialog', function (): void {
    $host = new Group(Rect::of(0, 0, 80, 25));
    $host->putEvent(Event::broadcast(Cmd::Default));

    expect(MessageBox::show($host, 'Saved.', MsgBoxFlag::Information | MsgBoxFlag::OkButton))->toBe(Cmd::Ok);
});

test('checkbox clusters toggle independent bits and radio clusters retain one index', function (): void {
    $items = SItem::list('~O~ne', '~T~wo', '~T~hree');
    $checks = new CheckBoxes(Rect::of(0, 0, 20, 3), $items);
    $checks->setState(State::Focused, true);
    $checks->handleEvent(Event::keyDown(new \HelgeSverre\TurboVision\Events\KeyDownEvent(0, ' ')));
    expect($checks->getData())->toBe(1);

    $radios = new RadioButtons(Rect::of(0, 0, 20, 3), SItem::list('One', 'Two', 'Three'));
    $radios->setState(State::Focused, true);
    $radios->handleEvent(Event::key(Key::Down));
    expect($radios->getData())->toBe(1)
        ->and($radios->mark(0))->toBeFalse()
        ->and($radios->mark(1))->toBeTrue();
});

test('ListBox replaces its collection and round-trips selected item data', function (): void {
    $list = new ListBox(Rect::of(0, 0, 20, 4));
    $list->setData(['collection' => ['A', 'B', 'C'], 'selection' => 2]);

    expect($list->list())->toBe(['A', 'B', 'C'])
        ->and($list->getData())->toBe(['collection' => ['A', 'B', 'C'], 'selection' => 2]);
});

test('radio mnemonics fire via Alt while a text field owns the cursor', function (): void {
    $host = new Group(Rect::of(0, 0, 80, 25));
    $dialog = new Dialog(Rect::of(10, 5, 50, 15), 'Pick');
    $input = new InputLine(Rect::of(2, 2, 28, 3), 40);
    $radios = new RadioButtons(Rect::of(2, 5, 24, 8), SItem::list('~O~ne', '~T~wo'));
    $dialog->insert($input);
    $dialog->insert($radios);
    $dialog->setCurrent($input);
    $host->insert($dialog);

    // Plain letters stay with the focused input.
    $dialog->handleEvent(Event::keyDown(new KeyDownEvent(ord('o'), 'o')));
    expect($input->text())->toBe('o')
        ->and($radios->value)->toBe(0);

    // Alt+letter reaches the unfocused cluster through pre-process routing.
    $dialog->handleEvent(Event::keyDown(new KeyDownEvent(ord('t'), 't', KeyModifier::Alt)));
    expect($radios->value)->toBe(1)
        ->and($host->pumpEvent())->toBeNull();
});
