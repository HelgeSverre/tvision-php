<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Persistence;

use JsonException;
use SplObjectStorage;

/**
 * JSON graph codec that never invokes PHP unserialize() or resolves a class name
 * from disk. The registry is the complete allow-list of object constructors.
 */
final class StreamCodec
{
    private const string MARKER = '$tvision';

    /** @var SplObjectStorage<Streamable, int> */
    private SplObjectStorage $written;

    /** @var SplObjectStorage<Streamable, null> Objects currently being encoded. */
    private SplObjectStorage $encoding;

    /** @var array<int, Streamable> */
    private array $read = [];

    private int $nextId = 1;

    private int $nodes = 0;

    public function __construct(
        private readonly StreamableRegistry $registry,
        private readonly int $maxDepth = 64,
        private readonly int $maxNodes = 10_000,
        private readonly int $maxBytes = 8_000_000,
    ) {
        if ($maxDepth < 1 || $maxDepth > 500 || $maxNodes < 1 || $maxBytes < 1) {
            throw new \InvalidArgumentException('Stream limits must be positive (maxDepth <= 500).');
        }
        $this->written = new SplObjectStorage;
        $this->encoding = new SplObjectStorage;
    }

    public function encode(Streamable $object): string
    {
        try {
            $json = json_encode(
                $this->encodeDocument($object),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                512,
            );
        } catch (JsonException $exception) {
            throw new PersistenceException('Could not encode persisted JSON.', 0, $exception);
        }
        if (strlen($json) > $this->maxBytes) {
            throw new PersistenceException("Persisted JSON exceeds the {$this->maxBytes}-byte limit.");
        }

        return $json;
    }

    public function decode(string $json): Streamable
    {
        if ($json === '') {
            throw new PersistenceException('Persisted JSON is empty.');
        }
        if (strlen($json) > $this->maxBytes) {
            throw new PersistenceException("Persisted JSON exceeds the {$this->maxBytes}-byte limit.");
        }
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PersistenceException('Persisted JSON is malformed.', 0, $exception);
        }
        if (! is_array($document)) {
            throw new PersistenceException('Persisted document must be an object.');
        }

        return $this->decodeDocument($document);
    }

    /** @return array<string, mixed> */
    public function encodeDocument(Streamable $object): array
    {
        $this->resetWriter();
        $document = $this->encodeValue($object, 0);
        if (! is_array($document) || ($document[self::MARKER] ?? null) !== 'object') {
            throw new PersistenceException('Root streamable object could not be encoded.');
        }

        $result = [];
        foreach ($document as $key => $value) {
            if (! is_string($key)) {
                throw new PersistenceException('Root streamable document keys must be strings.');
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /** @param array<mixed> $document */
    public function decodeDocument(array $document): Streamable
    {
        $this->resetReader();
        $object = $this->decodeValue($document, 0);
        if (! $object instanceof Streamable) {
            throw new PersistenceException('Persisted document root must be a streamable object.');
        }

        return $object;
    }

    private function resetWriter(): void
    {
        $this->written = new SplObjectStorage;
        $this->encoding = new SplObjectStorage;
        $this->nextId = 1;
        $this->nodes = 0;
    }

    private function resetReader(): void
    {
        $this->read = [];
        $this->nodes = 0;
    }

    private function countNode(int $depth): void
    {
        if ($depth > $this->maxDepth) {
            throw new PersistenceException("Persisted value exceeds the {$this->maxDepth}-level nesting limit.");
        }
        if (++$this->nodes > $this->maxNodes) {
            throw new PersistenceException("Persisted value exceeds the {$this->maxNodes}-node limit.");
        }
    }

    private function encodeValue(mixed $value, int $depth): mixed
    {
        $this->countNode($depth);
        if ($value instanceof Streamable) {
            if ($this->written->offsetExists($value)) {
                if ($this->encoding->offsetExists($value)) {
                    throw new PersistenceException('Cyclic object graphs are not supported by constructor-based stream factories.');
                }

                return [self::MARKER => 'ref', 'id' => $this->written[$value]];
            }

            $id = $this->nextId++;
            $this->written[$value] = $id;
            $this->encoding[$value] = null;
            try {
                $type = $value::streamType();
                if (trim($type) === '' || ! $this->registry->has($type)) {
                    throw new PersistenceException("Streamable type '{$type}' is not registered.");
                }
                $data = $value->streamData();
                $this->assertDataMap($data, $type);
                $encodedData = $this->encodeValue($data, $depth + 1);
            } finally {
                unset($this->encoding[$value]);
            }

            return [
                self::MARKER => 'object',
                'id' => $id,
                'type' => $type,
                'data' => $encodedData,
            ];
        }
        if (is_array($value)) {
            if (array_key_exists(self::MARKER, $value)) {
                throw new PersistenceException("'" . self::MARKER . "' is reserved in persisted data arrays.");
            }
            $encoded = [];
            $isList = array_is_list($value);
            foreach ($value as $key => $item) {
                // A non-list map with integer keys has no lossless JSON form:
                // json_encode turns it into an object with string keys, and
                // json_decode hands back strings. Reject at the write.
                if (! $isList && ! is_string($key)) {
                    throw new PersistenceException(
                        "Persisted map keys must be strings; integer key {$key} would lose its identity in JSON.",
                    );
                }
                $encoded[$key] = $this->encodeValue($item, $depth + 1);
            }

            return $encoded;
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new PersistenceException('Persisted floats must be finite.');
        }
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        throw new PersistenceException('Only scalars, arrays, and Streamable objects can be persisted.');
    }

    private function decodeValue(mixed $value, int $depth): mixed
    {
        $this->countNode($depth);
        if (! is_array($value)) {
            if (is_null($value) || is_scalar($value)) {
                return $value;
            }
            throw new PersistenceException('Persisted values must be JSON scalars, arrays, or streamable envelopes.');
        }
        if (array_key_exists(self::MARKER, $value)) {
            return $this->decodeEnvelope($value, $depth);
        }

        $decoded = [];
        foreach ($value as $key => $item) {
            $decoded[$key] = $this->decodeValue($item, $depth + 1);
        }

        return $decoded;
    }

    /** @param array<mixed> $envelope */
    private function decodeEnvelope(array $envelope, int $depth): Streamable
    {
        $kind = $envelope[self::MARKER] ?? null;
        if ($kind === 'ref') {
            $this->assertExactKeys($envelope, [self::MARKER, 'id']);
            $id = $this->idFrom($envelope['id'] ?? null);
            $object = $this->read[$id] ?? null;
            if ($object === null) {
                throw new PersistenceException("Persisted object reference {$id} is unknown or circular.");
            }

            return $object;
        }
        if ($kind !== 'object') {
            throw new PersistenceException('Persisted streamable envelope has an invalid kind.');
        }
        $this->assertExactKeys($envelope, [self::MARKER, 'id', 'type', 'data']);
        $id = $this->idFrom($envelope['id'] ?? null);
        if (isset($this->read[$id])) {
            throw new PersistenceException("Persisted object identifier {$id} is duplicated.");
        }
        $type = $envelope['type'] ?? null;
        if (! is_string($type) || trim($type) === '') {
            throw new PersistenceException('Persisted streamable type must be a non-empty string.');
        }
        $rawData = $envelope['data'] ?? null;
        if (! is_array($rawData) || ($rawData !== [] && array_is_list($rawData))) {
            throw new PersistenceException("Persisted data for '{$type}' must be an object.");
        }
        $data = $this->decodeValue($rawData, $depth + 1);
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new PersistenceException("Persisted data for '{$type}' must be an object.");
        }
        /** @var array<string, mixed> $data */
        $this->assertDataMap($data, $type);
        $object = $this->registry->create($type, $data);
        $this->read[$id] = $object;

        return $object;
    }

    /**
     * @param array<mixed> $actual
     * @param list<string> $expected
     */
    private function assertExactKeys(array $actual, array $expected): void
    {
        $keys = array_keys($actual);
        sort($keys);
        $wanted = $expected;
        sort($wanted);
        if ($keys !== $wanted) {
            throw new PersistenceException('Persisted streamable envelope has an invalid shape.');
        }
    }

    private function idFrom(mixed $value): int
    {
        if (! is_int($value) || $value < 1) {
            throw new PersistenceException('Persisted object identifier must be a positive integer.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function assertDataMap(array $data, string $type): void
    {
        if ($data !== [] && array_is_list($data)) {
            throw new PersistenceException("Streamable '{$type}' must return an associative data array.");
        }
        foreach ($data as $key => $_) {
            if (! is_string($key)) {
                throw new PersistenceException("Streamable '{$type}' data keys must be strings.");
            }
        }
    }
}
