<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\Streamable;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Persistence\StreamableType;

final class PersistenceTestLeaf implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.leaf';

    public function __construct(public readonly string $name) {}

    public function streamData(): array
    {
        return ['name' => $this->name];
    }

    public static function fromStreamData(array $data): static
    {
        if (count($data) !== 1 || ! isset($data['name']) || ! is_string($data['name'])) {
            throw new PersistenceException('Invalid test leaf.');
        }

        return new self($data['name']);
    }
}

final class PersistenceTestPair implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.pair';

    public function __construct(
        public readonly PersistenceTestLeaf $first,
        public readonly PersistenceTestLeaf $second,
    ) {}

    public function streamData(): array
    {
        return ['first' => $this->first, 'second' => $this->second];
    }

    public static function fromStreamData(array $data): static
    {
        if (! isset($data['first'], $data['second'])
            || ! $data['first'] instanceof PersistenceTestLeaf
            || ! $data['second'] instanceof PersistenceTestLeaf
        ) {
            throw new PersistenceException('Invalid test pair.');
        }

        return new self($data['first'], $data['second']);
    }
}

final class PersistenceTestCycle implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.cycle';

    public function streamData(): array
    {
        return ['self' => $this];
    }

    public static function fromStreamData(array $data): static
    {
        return new self;
    }
}

final class PersistenceTestUnsafe implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.unsafe';

    public function streamData(): array
    {
        return ['object' => new stdClass];
    }

    public static function fromStreamData(array $data): static
    {
        return new self;
    }
}

final class PersistenceTestReservedMarker implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.reserved-marker';

    public function streamData(): array
    {
        return ['$tvision' => 'not an envelope'];
    }

    public static function fromStreamData(array $data): static
    {
        return new self;
    }
}

function persistenceTestCodec(): StreamCodec
{
    $registry = new StreamableRegistry;
    $registry->registerClass(PersistenceTestLeaf::STREAM_TYPE, PersistenceTestLeaf::class);
    $registry->registerClass(PersistenceTestPair::STREAM_TYPE, PersistenceTestPair::class);
    $registry->registerClass(PersistenceTestCycle::STREAM_TYPE, PersistenceTestCycle::class);
    $registry->registerClass(PersistenceTestUnsafe::STREAM_TYPE, PersistenceTestUnsafe::class);
    $registry->registerClass(PersistenceTestReservedMarker::STREAM_TYPE, PersistenceTestReservedMarker::class);
    $registry->registerClass(StreamCodecNumericKeyFixture::STREAM_TYPE, StreamCodecNumericKeyFixture::class);

    return new StreamCodec($registry);
}

test('stream codec round trips registered object graphs without PHP serialization', function (): void {
    $codec = persistenceTestCodec();
    $leaf = new PersistenceTestLeaf('shared leaf');
    $json = $codec->encode(new PersistenceTestPair($leaf, $leaf));
    $decoded = $codec->decode($json);
    if (! $decoded instanceof PersistenceTestPair) {
        throw new LogicException('The decoded test object has the wrong type.');
    }

    expect($json)->toContain('"$tvision":"object"')
        ->and($json)->toContain('"$tvision":"ref"')
        ->and($decoded->first->name)->toBe('shared leaf')
        ->and($decoded->first)->toBe($decoded->second);
});

test('stream codec refuses unregistered types and malformed envelopes before a factory runs', function (): void {
    $codec = persistenceTestCodec();

    expect(fn (): Streamable => $codec->decode('{"$tvision":"object","id":1,"type":"DateTime","data":[]}'))
        ->toThrow(PersistenceException::class, 'not registered');
    expect(fn (): Streamable => $codec->decode('{"$tvision":"object","id":1,"type":"test.leaf","data":{"name":"x","extra":true}}'))
        ->toThrow(PersistenceException::class, 'Invalid test leaf');
    expect(fn (): Streamable => $codec->decode('{"$tvision":"ref","id":1}'))
        ->toThrow(PersistenceException::class, 'unknown or circular');
});

test('stream codec rejects unsafe values, reserved markers, cycles, and malformed JSON', function (): void {
    $codec = persistenceTestCodec();
    expect(fn (): string => $codec->encode(new PersistenceTestCycle))
        ->toThrow(PersistenceException::class, 'Cyclic object graphs');
    expect(fn (): string => $codec->encode(new PersistenceTestUnsafe))
        ->toThrow(PersistenceException::class, 'Only scalars');
    expect(fn (): string => $codec->encode(new PersistenceTestReservedMarker))
        ->toThrow(PersistenceException::class, 'reserved');
    expect(fn (): Streamable => $codec->decode('{not json}'))
        ->toThrow(PersistenceException::class, 'malformed');
    expect(fn (): Streamable => $codec->decode('{"$tvision":"evil"}'))
        ->toThrow(PersistenceException::class, 'invalid kind');
});

test('stream codec rejects oversized JSON before parsing or allocating its graph', function (): void {
    $registry = new StreamableRegistry;
    $registry->registerClass(PersistenceTestLeaf::STREAM_TYPE, PersistenceTestLeaf::class);
    $codec = new StreamCodec($registry, maxBytes: 16);

    expect(fn (): Streamable => $codec->decode(str_repeat('{', 17)))
        ->toThrow(PersistenceException::class, '16-byte limit');
    expect(fn (): string => $codec->encode(new PersistenceTestLeaf('too long')))
        ->toThrow(PersistenceException::class, '16-byte limit');
});

test('streamable registry rejects duplicate and mismatched registrations', function (): void {
    $registry = new StreamableRegistry;
    $registry->registerClass(PersistenceTestLeaf::STREAM_TYPE, PersistenceTestLeaf::class);

    expect(fn (): StreamableRegistry => $registry->registerClass(PersistenceTestLeaf::STREAM_TYPE, PersistenceTestLeaf::class))
        ->toThrow(InvalidArgumentException::class, 'already registered');
    expect(fn (): StreamableRegistry => $registry->registerClass('wrong', PersistenceTestLeaf::class))
        ->toThrow(InvalidArgumentException::class, 'does not match');
});

it('rejects numeric-string array keys at encode time instead of failing the read', function (): void {
    $codec = persistenceTestCodec();

    expect(fn () => $codec->encode(new StreamCodecNumericKeyFixture))
        ->toThrow(HelgeSverre\TurboVision\Persistence\PersistenceException::class, 'lose its identity');
});

final class StreamCodecNumericKeyFixture implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'test.numeric-key';

    public function streamData(): array
    {
        // A nested int-keyed map would silently decode as a JSON list.
        return ['items' => [7 => 'x']];
    }

    public static function fromStreamData(array $data): static
    {
        return new self();
    }
}
