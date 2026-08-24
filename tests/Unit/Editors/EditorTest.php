<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Editors\Editor;
use HelgeSverre\TurboVision\Editors\EditorDialogKind;
use HelgeSverre\TurboVision\Editors\EditorDialogRequest;
use HelgeSverre\TurboVision\Editors\FindRequest;
use HelgeSverre\TurboVision\Editors\Memo;
use HelgeSverre\TurboVision\Editors\ReplaceRequest;
use HelgeSverre\TurboVision\Editors\SearchOptions;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Rect;

function editorForTest(string $text = ''): Editor
{
    return new Editor(Rect::of(0, 0, 20, 5), text: $text);
}

test('editor edits, selects, clips and undoes changes', function (): void {
    $editor = editorForTest('hello');
    $editor->setSelect(1, 4);

    expect($editor->selectedText())->toBe('ell')
        ->and($editor->cut())->toBeTrue()
        ->and($editor->text())->toBe('ho')
        ->and(Editor::clipboard())->toBe('ell');

    $editor->paste();
    expect($editor->text())->toBe('hello')
        ->and($editor->undo())->toBeTrue()
        ->and($editor->text())->toBe('ho')
        ->and($editor->redo())->toBeTrue()
        ->and($editor->text())->toBe('hello');
});

test('editor maps cursor positions and pointer targets by grapheme and line', function (): void {
    $editor = editorForTest("a👩‍💻\n\tb");

    expect($editor->length())->toBe(5)
        ->and($editor->positionOf(2)->x)->toBe(2)
        ->and($editor->positionOf(2)->y)->toBe(0)
        ->and($editor->positionOf(4)->x)->toBe(4)
        ->and($editor->positionOf(4)->y)->toBe(1)
        ->and($editor->pointerAt(1, 4))->toBe(4)
        ->and($editor->pointerAt(1, 5))->toBe(5);
});

test('editor search honours Unicode case and whole-word options', function (): void {
    $editor = editorForTest('Café cafe cafeteria');
    $editor->setSelect(0, 0);

    expect($editor->search('CAFÉ', 0))->toBeTrue()
        ->and($editor->selectedText())->toBe('Café');

    $editor->setSelect(0, 0);
    expect($editor->search('cafe', SearchOptions::WholeWordsOnly))->toBeTrue()
        ->and($editor->selectedText())->toBe('cafe');
});

test('editor replacement and standard command dispatch work', function (): void {
    $editor = editorForTest('one one two');

    expect($editor->replaceAll('one', '1'))->toBe(2)
        ->and($editor->text())->toBe('1 1 two');

    $editor->handleEvent(Event::command(Cmd::TextStart));
    $editor->handleEvent(Event::command(Cmd::DelChar));
    expect($editor->text())->toBe(' 1 two');
});

test('editor exposes typed find and replace value APIs', function (): void {
    $editor = editorForTest('pear PEAR');

    expect($editor->find(new FindRequest('PEAR', options: 0)))->toBeTrue()
        ->and($editor->selectedText())->toBe('pear')
        ->and($editor->replace(new ReplaceRequest(
            'pear',
            'apple',
            options: SearchOptions::DoReplace | SearchOptions::ReplaceAll,
        )))->toBe(2)
        ->and($editor->text())->toBe('apple apple');
});

test('typed replacement can search without changing text or replace one match from the cursor', function (): void {
    $editor = editorForTest('one two one');
    $editor->setSelect(4, 4);

    expect($editor->replace(new ReplaceRequest('one', '1', options: 0)))->toBe(0)
        ->and($editor->selectedText())->toBe('one')
        ->and($editor->text())->toBe('one two one');

    $editor->setSelect(4, 4);
    expect($editor->replace(new ReplaceRequest('one', '', options: SearchOptions::DoReplace)))->toBe(1)
        ->and($editor->text())->toBe('one two ')
        ->and($editor->undo())->toBeTrue()
        ->and($editor->text())->toBe('one two one');
});

test('prompted replace-all honors yes no and cancel with one reversible edit', function (): void {
    $editor = editorForTest("café CAFÉ\ncafé");
    $decisions = [Cmd::Yes, Cmd::No, Cmd::Cancel];
    $contexts = [];
    $selections = [];
    $editor->setDialogHandler(function (EditorDialogRequest $request) use ($editor, &$decisions, &$contexts, &$selections): int {
        $contexts[] = $request->context;
        $selections[] = $editor->selectedText();

        return array_shift($decisions) ?? Cmd::Cancel;
    });

    $changed = $editor->replace(new ReplaceRequest(
        'CAFÉ',
        '☕',
        SearchOptions::DoReplace | SearchOptions::ReplaceAll | SearchOptions::PromptOnReplace,
    ));

    expect($changed)->toBe(1)
        ->and($editor->text())->toBe("☕ CAFÉ\ncafé")
        ->and($selections)->toBe(['café', 'CAFÉ', 'café'])
        ->and(array_column($contexts, 'offset'))->toBe([0, 5, 10])
        ->and(array_column($contexts, 'line'))->toBe([0, 0, 1])
        ->and(array_column($contexts, 'column'))->toBe([0, 5, 0])
        ->and(array_column($contexts, 'find'))->toBe(['CAFÉ', 'CAFÉ', 'CAFÉ'])
        ->and(array_column($contexts, 'replace'))->toBe(['☕', '☕', '☕'])
        ->and($editor->undoDepth())->toBe(1)
        ->and($editor->undo())->toBeTrue()
        ->and($editor->text())->toBe("café CAFÉ\ncafé");
});

test('prompted replacement defaults to cancel when no dialog handler is installed', function (): void {
    $editor = editorForTest('one one');

    expect($editor->replace(new ReplaceRequest(
        'one',
        '1',
        SearchOptions::DoReplace | SearchOptions::ReplaceAll | SearchOptions::PromptOnReplace,
    )))->toBe(0)
        ->and($editor->text())->toBe('one one')
        ->and($editor->selectedText())->toBe('one')
        ->and($editor->lastDialog?->kind)->toBe(EditorDialogKind::ReplacePrompt);
});

test('prompted replace-all handles adjacent matches without rescanning replacements', function (): void {
    $editor = editorForTest('aaaa');
    $editor->setDialogHandler(static fn (EditorDialogRequest $_request): int => Cmd::Yes);

    expect($editor->replace(new ReplaceRequest(
        'aa',
        'aaa',
        SearchOptions::DoReplace | SearchOptions::ReplaceAll | SearchOptions::PromptOnReplace,
    )))->toBe(2)
        ->and($editor->text())->toBe('aaaaaa');
});

test('replace command dispatch honors the configured prompt flags', function (): void {
    $editor = editorForTest('one two');
    $editor->findStr = 'one';
    $editor->replaceStr = '1';
    $editor->editorFlags = SearchOptions::DoReplace | SearchOptions::PromptOnReplace;
    $editor->setSelect(4, 4);
    $editor->setDialogHandler(static fn (EditorDialogRequest $_request): int => Cmd::Yes);
    $event = Event::command(Cmd::Replace);

    $editor->handleEvent($event);

    expect($event->isNothing())->toBeTrue()
        ->and($editor->text())->toBe('1 two');
});

test('prompted replacement never overwrites a re-entrant handler edit', function (): void {
    $editor = editorForTest('one one');
    $editor->setDialogHandler(function (EditorDialogRequest $_request) use ($editor): int {
        $editor->setText('changed by handler', true);

        return Cmd::Yes;
    });

    expect($editor->replace(new ReplaceRequest(
        'one',
        '1',
        SearchOptions::DoReplace | SearchOptions::ReplaceAll | SearchOptions::PromptOnReplace,
    )))->toBe(0)
        ->and($editor->text())->toBe('changed by handler');
});

test('editor consumes printable and navigation key events', function (): void {
    $editor = editorForTest('ab');
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value)));
    $event = Event::keyDown(new KeyDownEvent(0, 'é'));
    $editor->handleEvent($event);

    expect($event->isNothing())->toBeTrue()
        ->and($editor->text())->toBe('éab');
});

test('editor handles original word and clipboard key bindings', function (): void {
    $editor = editorForTest('one two');
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::Home->value)));
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::CtrlRight->value)));
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::Left->value, modifiers: KeyModifier::Shift)));
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::CtrlInsert->value)));
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::End->value)));
    $editor->handleEvent(Event::keyDown(new KeyDownEvent(Key::ShiftInsert->value)));

    expect(Editor::clipboard())->toBe(' ')
        ->and($editor->text())->toBe('one two ');
});

test('editor caches document metrics until its buffer version changes', function (): void {
    $editor = editorForTest(str_repeat("column\tvalue\n", 250));
    $initialBuilds = $editor->metricBuilds();

    $editor->positionOf(1200);
    $editor->pointerAt(100, 7);
    $editor->lineStart(1200);
    $editor->lineEnd(1200);
    $editor->draw();

    expect($editor->metricBuilds())->toBe($initialBuilds);

    $editor->insertText('x');
    expect($editor->metricBuilds())->toBe($initialBuilds + 1);
});

test('editor retains a bounded delta undo history instead of document snapshots', function (): void {
    $editor = editorForTest(str_repeat("long line\n", 500));
    for ($i = 0; $i < 130; $i++) {
        $editor->insertText('x');
    }

    expect($editor->undoDepth())->toBe(100)
        ->and($editor->undoByteSize())->toBeLessThanOrEqual($editor->undoByteBudget());

    for ($i = 0; $i < 100; $i++) {
        expect($editor->undo())->toBeTrue();
    }
    expect($editor->undo())->toBeFalse();
});

test('memo has string form data and leaves tab for dialog traversal', function (): void {
    $memo = new Memo(Rect::of(0, 0, 10, 2), text: 'notes');
    $event = Event::keyDown(new KeyDownEvent(Key::Tab->value));
    $memo->handleEvent($event);

    expect($memo->getData())->toBe('notes')
        ->and($memo->dataSize())->toBe(5)
        ->and($event->isNothing())->toBeFalse();
});

it('preserves the goal column when moving vertically across shorter lines', function (): void {
    $editor = new Editor(Rect::of(0, 0, 40, 10), text: "long-line-here\nab\nanother-long-line");

    // Cursor to the start, then the end of line 0 (column 14).
    $editor->handleEvent(Event::key(Key::CtrlHome));
    $editor->handleEvent(Event::key(Key::End));
    expect($editor->positionOf($editor->curPtr)->x)->toBe(14);

    $editor->handleEvent(Event::key(Key::Down));
    expect($editor->positionOf($editor->curPtr)->x)->toBe(2);

    // The classic bug: coming from the short line, the column must recover to 14.
    $editor->handleEvent(Event::key(Key::Down));
    expect($editor->positionOf($editor->curPtr)->x)->toBe(14);

    // Horizontal motion redefines the goal column...
    $editor->handleEvent(Event::key(Key::Home));
    for ($i = 0; $i < 4; $i++) {
        $editor->handleEvent(Event::key(Key::Right));
    }

    // ...even though the next vertical hop clamps to the short line...
    $editor->handleEvent(Event::key(Key::Up));
    expect($editor->positionOf($editor->curPtr)->x)->toBe(2);

    // ...the new goal survives the clamp and restores on the way back down.
    $editor->handleEvent(Event::key(Key::Down));
    expect($editor->positionOf($editor->curPtr)->x)->toBe(4);
});

it('notifies SearchFailed through the dialog handler when a find misses', function (): void {
    $editor = new Editor(Rect::of(0, 0, 40, 10), text: 'abc');
    $kinds = [];
    $editor->setDialogHandler(function (EditorDialogRequest $request) use (&$kinds): int {
        $kinds[] = $request->kind;

        return Cmd::Cancel;
    });

    expect($editor->find(new FindRequest('zzz')))->toBeFalse()
        ->and($editor->find(new FindRequest('b')))->toBeTrue()
        ->and($kinds)->toBe([EditorDialogKind::SearchFailed]);
});
