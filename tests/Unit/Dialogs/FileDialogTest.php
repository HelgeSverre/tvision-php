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
