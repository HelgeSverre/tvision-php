<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\Streamable;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Persistence\StreamableType;
use HelgeSverre\TurboVision\Resources\ResourceException;
use HelgeSverre\TurboVision\Resources\ResourceFile;
use HelgeSverre\TurboVision\Resources\StringList;
use HelgeSverre\TurboVision\Resources\StringListMaker;

final readonly class ResourceScalarForTest implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.resource-scalar';

    public function __construct(public int|string $value) {}

    /** @return array{value:int|string} */
    public function streamData(): array
    {
        return ['value' => $this->value];
    }

    public static function fromStreamData(array $data): static
    {
        $value = $data['value'] ?? null;
        if (count($data) !== 1 || (! is_int($value) && ! is_string($value))) {
            throw new PersistenceException('A resource scalar needs exactly one integer or string value.');
        }

        return new self($value);
    }
}

function resourceTestCodec(): StreamCodec
{
    $registry = new StreamableRegistry;
    $registry->registerClass(StringList::STREAM_TYPE, StringList::class);
    $registry->registerClass(ResourceScalarForTest::STREAM_TYPE, ResourceScalarForTest::class);

    return new StreamCodec($registry);
}

test('string lists are immutable and builders make independent indexed lists', function (): void {
    $maker = (new StringListMaker)->add('alpha')->addMany(['beta', 'gamma']);
    $list = $maker->build();
    $maker->clear()->add('later');

    expect($list->all())->toBe(['alpha', 'beta', 'gamma'])
        ->and($list[1])->toBe('beta')
        ->and($list->contains('gamma'))->toBeTrue()
        ->and($list->count())->toBe(3);
    expect(fn () => $list[] = 'nope')->toThrow(LogicException::class, 'immutable');
    expect(fn (): string => $list->get(8))->toThrow(OutOfBoundsException::class, 'out of bounds');
    expect(fn () => new StringList([123]))->toThrow(InvalidArgumentException::class, 'only strings');
});

test('resource files atomically persist named registered resources', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-resource-' . bin2hex(random_bytes(6));
    $path = $directory . '/workspace.json';

    try {
        $file = ResourceFile::open($path, resourceTestCodec());
        $file->put('recent-files', new StringList(['one.php', 'two.php']));
        $file->put('labels', new StringList(['work', 'personal']));
        $file->flush();

        $reopened = ResourceFile::open($path, resourceTestCodec());
        $recent = $reopened->require('recent-files');
        if (! $recent instanceof StringList) {
            throw new LogicException('The decoded resource has the wrong type.');
        }

        expect($reopened->keys())->toBe(['labels', 'recent-files'])
            ->and($recent->all())->toBe(['one.php', 'two.php'])
            ->and($reopened->remove('labels'))->toBeTrue()
            ->and($reopened->remove('missing'))->toBeFalse();
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

test('resource files merge independent concurrent keys and reject conflicting writes', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-resource-concurrent-' . bin2hex(random_bytes(6));
    $path = $directory . '/workspace.json';

    try {
        $seed = ResourceFile::open($path, resourceTestCodec());
        $seed->put('base', new StringList(['original']));
        $seed->flush();

        $first = ResourceFile::open($path, resourceTestCodec());
        $second = ResourceFile::open($path, resourceTestCodec());
        $first->put('first-key', new StringList(['first']));
        $second->put('second-key', new StringList(['second']));
        $first->flush();
        $second->flush();

        $merged = ResourceFile::open($path, resourceTestCodec());
        expect($merged->keys())->toBe(['base', 'first-key', 'second-key']);

        $left = ResourceFile::open($path, resourceTestCodec());
        $right = ResourceFile::open($path, resourceTestCodec());
        $left->put('base', new StringList(['left']));
        $right->put('base', new StringList(['right']));
        $left->flush();
        expect(fn () => $right->flush())
            ->toThrow(ResourceException::class, 'changed in another instance');
    } finally {
        foreach ([$path, $path . '.lock'] as $candidate) {
            if (is_file($candidate)) {
                unlink($candidate);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('resource conflicts compare persisted scalar types strictly', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-resource-strict-' . bin2hex(random_bytes(6));
    $path = $directory . '/workspace.json';

    try {
        $seed = ResourceFile::open($path, resourceTestCodec());
        $seed->put('value', new ResourceScalarForTest(1));
        $seed->flush();

        $left = ResourceFile::open($path, resourceTestCodec());
        $right = ResourceFile::open($path, resourceTestCodec());
        $left->put('value', new ResourceScalarForTest('1'));
        $right->put('value', new ResourceScalarForTest(2));
        $left->flush();

        expect(fn () => $right->flush())
            ->toThrow(ResourceException::class, 'changed in another instance');
    } finally {
        foreach ([$path, $path . '.lock'] as $candidate) {
            if (is_file($candidate)) {
                unlink($candidate);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('resource file size limit includes its trailing newline', function (): void {
    $directory = sys_get_temp_dir() . '/tvision-resource-limit-' . bin2hex(random_bytes(6));
    $path = $directory . '/workspace.json';
    $codec = resourceTestCodec();
    $empty = $codec->encodeDocument(new ResourceScalarForTest(''));
    $emptyJson = json_encode([
        'format' => 'turbovision-resource-file',
        'version' => 1,
        'resources' => ['large' => $empty],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $payloadBytes = 8_000_000 - strlen($emptyJson);

    try {
        $file = ResourceFile::open($path, $codec);
        $file->put('large', new ResourceScalarForTest(str_repeat('x', $payloadBytes)));

        expect(fn () => $file->flush())->toThrow(ResourceException::class, 'exceeds the 8000000-byte limit')
            ->and(is_file($path))->toBeFalse();
    } finally {
        foreach ([$path, $path . '.lock'] as $candidate) {
            if (is_file($candidate)) {
                unlink($candidate);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('resource files fail closed for malformed documents and unavailable resources', function (): void {
    $path = sys_get_temp_dir() . '/tvision-resource-bad-' . bin2hex(random_bytes(6)) . '.json';

    try {
        file_put_contents($path, '{"format":"turbovision-resource-file","version":99,"resources":{}}');
        expect(fn (): ResourceFile => ResourceFile::open($path, resourceTestCodec()))
            ->toThrow(ResourceException::class, 'unsupported schema');

        $file = ResourceFile::open($path . '.new', resourceTestCodec());
        expect(fn () => $file->require('missing'))->toThrow(ResourceException::class, 'does not exist');
        expect(fn () => $file->put('12', new StringList))->toThrow(InvalidArgumentException::class, 'cannot be numeric-only');

        file_put_contents(
            $path,
            '{"format":"turbovision-resource-file","version":1,"resources":{"hostile":{"$tvision":"object","id":1,"type":"DateTime","data":[]}}}',
        );
        $hostile = ResourceFile::open($path, resourceTestCodec());
        expect(fn () => $hostile->require('hostile'))->toThrow(ResourceException::class, 'could not be decoded');
    } finally {
        foreach ([$path, $path . '.new'] as $candidate) {
            if (is_file($candidate)) {
                unlink($candidate);
            }
        }
    }
});
