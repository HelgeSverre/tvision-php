<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\ChDirDialog;
use HelgeSverre\TurboVision\Events\Cmd;

it('changes directory only after an accepted ok validation', function (): void {
    $original = getcwd();
    if ($original === false) {
        throw new RuntimeException('Unable to read the current working directory.');
    }
    $root = sys_get_temp_dir() . '/tvision-chdir-' . bin2hex(random_bytes(4));
    mkdir($root);

    try {
        $dialog = new ChDirDialog(directory: $root);
        $dialog->dirInput->setText($root);

        expect($dialog->valid(Cmd::Ok))->toBeTrue()
            ->and(getcwd())->toBe(realpath($root));
    } finally {
        chdir($original);
        rmdir($root);
    }
});

it('rejects a missing directory without changing the working directory', function (): void {
    $original = getcwd();
    if ($original === false) {
        throw new RuntimeException('Unable to read the current working directory.');
    }
    $dialog = new ChDirDialog(directory: $original);
    $dialog->dirInput->setText('/path/that/does/not/exist');

    expect($dialog->valid(Cmd::Ok))->toBeFalse()
        ->and(getcwd())->toBe($original);
});
