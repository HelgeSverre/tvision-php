<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\FilePath;

it('splits a wildcard into an existing directory and filename pattern', function (): void {
    $root = sys_get_temp_dir() . '/tvision-file-path-' . bin2hex(random_bytes(4));
    mkdir($root . '/nested', recursive: true);

    try {
        $split = FilePath::splitPattern('nested/*.php', $root);

        expect($split)->toBe([
            'directory' => realpath($root . '/nested'),
            'pattern' => '*.php',
        ]);
    } finally {
        rmdir($root . '/nested');
        rmdir($root);
    }
});

it('treats a directory input as an all-files query', function (): void {
    $root = sys_get_temp_dir() . '/tvision-file-path-' . bin2hex(random_bytes(4));
    mkdir($root);

    try {
        expect(FilePath::splitPattern($root))->toBe([
            'directory' => realpath($root),
            'pattern' => '*',
        ]);
    } finally {
        rmdir($root);
    }
});

it('uses fnmatch semantics without executing a shell', function (): void {
    expect(FilePath::matches('agenda.ics', '*.ics'))->toBeTrue()
        ->and(FilePath::matches('agenda.txt', '*.ics'))->toBeFalse();
});

it('does not permit parent traversal above a Windows drive root', function (): void {
    expect(FilePath::normalise('C:/../../target'))->toBe('C:/target')
        ->and(FilePath::normalise('C:\\work\\..\\..\\target'))->toBe('C:/target');
});

it('preserves UNC host and share roots during lexical normalisation', function (): void {
    expect(FilePath::normalise('\\\\server\\share\\folder\\..\\..\\target'))
        ->toBe('//server/share/target')
        ->and(FilePath::normalise('//server/share/../../target'))
        ->toBe('//server/share/target');
});
