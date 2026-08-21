<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Editors\Editor;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Support\Clipboard;

it('shares copied text between Editor and InputLine instead of keeping isolated clipboards', function (): void {
    $editor = new Editor(Rect::of(0, 0, 40, 10), text: 'hello world');
    $editor->setSelect(0, 5);
    $editor->copy();

    expect(Editor::clipboard())->toBe('hello')
        ->and(Clipboard::get())->toBe('hello');

    $line = new InputLine(Rect::of(0, 0, 20, 1), 40);
    $line->setText('');
    $line->handleEvent(Event::command(HelgeSverre\TurboVision\Events\Cmd::Paste));

    expect($line->text())->toBe('hello');

    // And the reverse direction: copy in the InputLine, paste in the Editor.
    $line->setText('shared text');
    $line->selectAll();
    $line->handleEvent(Event::command(HelgeSverre\TurboVision\Events\Cmd::Copy));

    $second = new Editor(Rect::of(0, 0, 40, 10), text: '');
    $second->paste();

    expect($second->text())->toBe('shared text');
});
