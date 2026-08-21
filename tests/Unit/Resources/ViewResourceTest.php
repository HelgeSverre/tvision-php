<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Resources\ResourceException;
use HelgeSverre\TurboVision\Resources\ResourceFile;
use HelgeSverre\TurboVision\Resources\ViewResource;
use HelgeSverre\TurboVision\Resources\ViewResourceNode;
use HelgeSverre\TurboVision\Resources\ViewResourceRegistry;
use HelgeSverre\TurboVision\Views\StaticText;

function viewResourcePersistenceCodec(): StreamCodec
{
    $registry = new StreamableRegistry;
    $registry->registerClass(ViewResource::STREAM_TYPE, ViewResource::class);

    return new StreamCodec($registry);
}

function demoViewResourceFactories(): ViewResourceRegistry
{
    $registry = new ViewResourceRegistry;
    $registry->register('demo.dialog', static fn (ViewResourceNode $node): Dialog => new Dialog($node->bounds, $node->string('title')));
    $registry->register('demo.text', static fn (ViewResourceNode $node): StaticText => new StaticText($node->bounds, $node->string('text')));
    $registry->register('demo.input', static function (ViewResourceNode $node): InputLine {
        $input = new InputLine($node->bounds, $node->integer('capacity'));
        $input->setText($node->string('text'));

        return $input;
    });
    $registry->register('demo.button', static fn (ViewResourceNode $node): Button => new Button(
        $node->bounds,
        $node->string('title'),
        $node->integer('command'),
    ));

    return $registry;
}

function demoDialogResource(): ViewResource
{
    return new ViewResource(new ViewResourceNode(
        'demo.dialog',
        Rect::of(8, 3, 54, 15),
        ['title' => 'Saved dialog'],
        [
            new ViewResourceNode('demo.text', Rect::of(3, 2, 40, 3), ['text' => 'A named resource tree']),
            new ViewResourceNode('demo.input', Rect::of(3, 5, 35, 6), ['capacity' => 32, 'text' => 'pre-filled']),
            new ViewResourceNode('demo.button', Rect::of(30, 8, 42, 10), ['title' => '~O~K', 'command' => Cmd::Ok]),
        ],
    ));
}

test('a named ResourceFile entry round trips and rebuilds a dialog/control tree', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-view-resource-' . bin2hex(random_bytes(6));
    $path = $directory . '/views.json';

    try {
        $file = ResourceFile::open($path, viewResourcePersistenceCodec());
        $file->put('saved-dialog', demoDialogResource());
        $file->flush();

        $loaded = ResourceFile::open($path, viewResourcePersistenceCodec())->require('saved-dialog');
        if (! $loaded instanceof ViewResource) {
            throw new LogicException('The loaded entry is not a ViewResource.');
        }
        $dialog = $loaded->build(demoViewResourceFactories());
        if (! $dialog instanceof Dialog) {
            throw new LogicException('The root node did not build a Dialog.');
        }

        // Window creates its runtime frame itself. Only the three declarative
        // child controls are reconstructed from the persisted resource.
        $children = $dialog->subviews();
        $input = $children[2] ?? null;
        $button = $children[3] ?? null;
        if (! $input instanceof InputLine || ! $button instanceof Button) {
            throw new LogicException('Persisted controls have the wrong runtime types.');
        }
        expect($dialog->getTitle())->toBe('Saved dialog')
            ->and($dialog->owner)->toBeNull()
            ->and($children)->toHaveCount(4)
            ->and($children[1])->toBeInstanceOf(StaticText::class)
            ->and($input->text())->toBe('pre-filled')
            ->and($button->command)->toBe(Cmd::Ok)
            ->and($input->owner)->toBe($dialog);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        if (is_file($path . '.lock')) {
            unlink($path . '.lock');
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('view resources reject unregistered types and malformed declarative node data', function (): void {
    $resource = new ViewResource(new ViewResourceNode('not.allowed', Rect::of(0, 0, 1, 1)));

    expect(fn () => $resource->build(new ViewResourceRegistry))
        ->toThrow(ResourceException::class, 'not registered');
    expect(fn (): ViewResource => ViewResource::fromStreamData([
        'root' => [
            'type' => 'demo.dialog',
            'bounds' => ['left' => 0, 'top' => 0, 'right' => 1, 'bottom' => 1],
            'properties' => ['title' => 'x'],
            'children' => [['type' => 'demo.text']],
        ],
    ]))->toThrow(PersistenceException::class, 'invalid schema');
});

test('a leaf factory cannot accept child nodes silently', function (): void {
    $resource = new ViewResource(new ViewResourceNode(
        'demo.text',
        Rect::of(0, 0, 10, 1),
        ['text' => 'leaf'],
        [new ViewResourceNode('demo.text', Rect::of(0, 0, 1, 1), ['text' => 'child'])],
    ));

    expect(fn () => $resource->build(demoViewResourceFactories()))
        ->toThrow(ResourceException::class, 'cannot own children');
});
