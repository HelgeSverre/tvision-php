<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\FileCommand;
use HelgeSverre\TurboVision\Dialogs\FileDialog;

it('expands selected filenames relative to its browsed directory', function (): void {
    $root = sys_get_temp_dir() . '/tvision-file-dialog-' . bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents($root . '/agenda.ics', 'BEGIN:VCALENDAR');

    try {
        $dialog = new FileDialog($root . '/*.ics');
        $dialog->fileName->setText('agenda.ics');

        expect($dialog->getFileName())->toBe(realpath($root) . '/agenda.ics')
            ->and($dialog->valid(FileCommand::Open))->toBeTrue();
    } finally {
        unlink($root . '/agenda.ics');
        rmdir($root);
    }
});

it('refreshes rather than closes when a wildcard is entered', function (): void {
    $root = sys_get_temp_dir() . '/tvision-file-dialog-' . bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents($root . '/agenda.ics', 'BEGIN:VCALENDAR');
    file_put_contents($root . '/notes.txt', 'notes');

    try {
        $dialog = new FileDialog($root . '/*');
        $dialog->fileName->setText('*.ics');
        $valid = $dialog->valid(FileCommand::Open);
        $names = array_map(static fn ($entry): string => $entry->name, $dialog->fileList->files()->all());

        expect($valid)->toBeFalse()
            ->and($dialog->wildCard)->toBe('*.ics')
            ->and(in_array('agenda.ics', $names, true))->toBeTrue()
            ->and(in_array('notes.txt', $names, true))->toBeFalse();
    } finally {
        unlink($root . '/agenda.ics');
        unlink($root . '/notes.txt');
        rmdir($root);
    }
});

use HelgeSverre\TurboVision\Collections\SearchRec;
use HelgeSverre\TurboVision\Dialogs\FileInputLine;
use HelgeSverre\TurboVision\Dialogs\FileInfoPane;
use HelgeSverre\TurboVision\Dialogs\FileList;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;

it('mirrors focused file-list rows into the input line and info pane via group routing', function (): void {
    $root = sys_get_temp_dir() . '/tvision-file-dialog-' . bin2hex(random_bytes(4));
    mkdir($root . '/alpha-dir', 0777, true);
    file_put_contents($root . '/alpha.ics', 'BEGIN:VCALENDAR');

    try {
        $group = new Group(Rect::of(0, 0, 60, 20));
        $list = new FileList(Rect::of(0, 0, 30, 8));
        $input = new FileInputLine(Rect::of(0, 9, 30, 10), 80);
        $pane = new FileInfoPane(Rect::of(31, 0, 59, 3));
        $group->insert($list);
        $group->insert($input);
        $group->insert($pane);

        // Row order: '..', 'alpha-dir', 'alpha.ics'. Focusing the file row must
        // broadcast through the Group to both consumers.
        $list->readDirectory($root, '*');
        $list->focusItem(2);

        expect($input->text())->toBe('alpha.ics')
            ->and($pane->file()?->name)->toBe('alpha.ics');
    } finally {
        unlink($root . '/alpha.ics');
        rmdir($root . '/alpha-dir');
        rmdir($root);
    }
});
