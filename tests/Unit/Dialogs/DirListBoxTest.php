<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\DirListBox;
use HelgeSverre\TurboVision\Geometry\Rect;

it('renders a path spine followed by sorted child directories', function (): void {
    $root = sys_get_temp_dir() . '/tvision-dir-list-' . bin2hex(random_bytes(4));
    mkdir($root . '/current/zeta', recursive: true);
    mkdir($root . '/current/alpha', recursive: true);

    try {
        $resolvedRoot = realpath($root);
        $list = new DirListBox(Rect::of(0, 0, 40, 10));
        $list->newDirectory($root . '/current');

        $entries = $list->directories()->all();
        expect($entries[0]->dir)->toBe('/')
            ->and($entries[$list->focused]->dir)->toBe($resolvedRoot . '/current')
            ->and(array_map(static fn ($entry): string => $entry->dir, array_slice($entries, -2)))
            ->toBe([$resolvedRoot . '/current/alpha', $resolvedRoot . '/current/zeta']);
    } finally {
        rmdir($root . '/current/zeta');
        rmdir($root . '/current/alpha');
        rmdir($root . '/current');
        rmdir($root);
    }
});
