<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\FileList;
use HelgeSverre\TurboVision\Dialogs\SortedListBox;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Geometry\Rect;

function temporaryFileDialogDirectory(): string
{
    $root = sys_get_temp_dir() . '/tvision-file-list-' . bin2hex(random_bytes(4));
    mkdir($root . '/alpha-dir', recursive: true);
    file_put_contents($root . '/zebra.txt', 'z');
    file_put_contents($root . '/alpha.ics', 'a');
    file_put_contents($root . '/.hidden.txt', 'hidden');

    return $root;
}

function removeTemporaryFileDialogDirectory(string $root): void
{
    unlink($root . '/zebra.txt');
    unlink($root . '/alpha.ics');
    unlink($root . '/.hidden.txt');
    rmdir($root . '/alpha-dir');
    rmdir($root);
}

it('lists parent first, directories second, and wildcard-matched files', function (): void {
    $root = temporaryFileDialogDirectory();
    try {
        $resolvedRoot = realpath($root);
        $list = new FileList(Rect::of(0, 0, 30, 8));
        $list->readDirectory($root, '*.ics');

        expect(array_map(static fn ($entry): string => $entry->name, $list->files()->all()))
            ->toBe(['..', 'alpha-dir', 'alpha.ics'])
            ->and($list->getText(1, 30))->toBe('alpha-dir/')
            ->and($list->entry(2)?->path)->toBe($resolvedRoot . '/alpha.ics');
    } finally {
        removeTemporaryFileDialogDirectory($root);
    }
});

it('does not include dotfiles unless the wildcard requests them', function (): void {
    $root = temporaryFileDialogDirectory();
    try {
        $list = new FileList(Rect::of(0, 0, 30, 8));
        $list->readDirectory($root, '*');
        expect(array_map(static fn ($entry): string => $entry->name, $list->files()->all()))
            ->not->toContain('.hidden.txt');

        $list->readDirectory($root, '.*');
        expect(array_map(static fn ($entry): string => $entry->name, $list->files()->all()))
            ->toContain('.hidden.txt');
    } finally {
        removeTemporaryFileDialogDirectory($root);
    }
});

it('incrementally searches a sorted list from printable keystrokes', function (): void {
    $list = new SortedListBox(Rect::of(0, 0, 20, 4));
    $list->newList(['alpha', 'bravo', 'charlie']);
    $event = Event::keyDown(new KeyDownEvent(0, 'b'));
    $list->handleEvent($event);

    expect($list->focused)->toBe(1)
        ->and($list->searchTerm())->toBe('b')
        ->and($event->isNothing())->toBeTrue();
});
