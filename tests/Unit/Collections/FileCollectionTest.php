<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Collections\FileCollection;
use HelgeSverre\TurboVision\Collections\SearchRec;

function fileEntry(string $name, bool $directory = false): SearchRec
{
    return new SearchRec(
        name: $name,
        path: '/tmp/' . $name,
        attributes: SearchRec::Archive | ($directory ? SearchRec::Directory : 0),
        size: 0,
        modifiedAt: null,
    );
}

it('sorts the parent entry before directories and files', function (): void {
    $files = new FileCollection();
    $files->insert(fileEntry('zebra.txt'));
    $files->insert(fileEntry('alpha.txt'));
    $files->insert(fileEntry('Zebra', directory: true));
    $files->insert(fileEntry('alpha', directory: true));
    $files->insert(fileEntry('..', directory: true));

    expect(array_map(static fn (SearchRec $entry): string => $entry->name, $files->all()))
        ->toBe(['..', 'alpha', 'Zebra', 'alpha.txt', 'zebra.txt']);
});

it('inserts at sorted positions and exposes entries by index', function (): void {
    $files = new FileCollection();
    $files->insert(fileEntry('bravo.txt'));
    $index = $files->insert(fileEntry('alpha.txt'));

    expect($index)->toBe(0)
        ->and($files->at(1)?->name)->toBe('bravo.txt')
        ->and($files->at(2))->toBeNull();
});
