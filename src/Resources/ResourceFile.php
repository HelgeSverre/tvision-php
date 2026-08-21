<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Resources;

use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\Streamable;
use JsonException;

/**
 * A small JSON-backed, name-addressable resource store.
 *
 * Resources stay encoded in memory until requested so an application may list or
 * replace entries without constructing every registered type. flush() takes a
 * stable sibling lock, merges non-conflicting key changes made by other opened
 * instances, then uses a same-directory temporary file plus rename.
 */
final class ResourceFile
{
    private const string FORMAT = 'turbovision-resource-file';

    private const int VERSION = 1;

    private const int MAX_BYTES = 8_000_000;

    private const int WRITE_CHUNK_BYTES = 8192;

    /** @var array<string, array<string, mixed>> */
    private array $resources;

    /** @var array<string, array<string, mixed>> Snapshot observed when opened or last flushed. */
    private array $baseResources;

    /** @var array<string, array<string, mixed>|null> Pending put (document) or remove (null) by key. */
    private array $pendingChanges = [];

    /** @param array<string, array<string, mixed>> $resources */
    private function __construct(
        private readonly string $path,
        private readonly StreamCodec $codec,
        array $resources,
    ) {
        $this->resources = $resources;
        $this->baseResources = $resources;
    }

    public static function open(string $path, StreamCodec $codec): self
    {
        if ($path === '') {
            throw new \InvalidArgumentException('Resource file path must not be empty.');
        }
        return new self($path, $codec, self::readResources($path));
    }

    public function path(): string
    {
        return $this->path;
    }

    public function count(): int
    {
        return count($this->resources);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->resources);
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = array_keys($this->resources);
        sort($keys, SORT_STRING);

        return $keys;
    }

    public function put(string $key, Streamable $resource): void
    {
        self::assertKey($key);
        $document = $this->codec->encodeDocument($resource);
        $this->resources[$key] = $document;
        $this->pendingChanges[$key] = $document;
    }

    public function get(string $key): ?Streamable
    {
        self::assertKey($key);
        $document = $this->resources[$key] ?? null;
        if ($document === null) {
            return null;
        }

        try {
            return $this->codec->decodeDocument($document);
        } catch (PersistenceException $exception) {
            throw new ResourceException("Resource '{$key}' could not be decoded.", 0, $exception);
        }
    }

    public function require(string $key): Streamable
    {
        $resource = $this->get($key);
        if ($resource === null) {
            throw new ResourceException("Resource '{$key}' does not exist.");
        }

        return $resource;
    }

    public function remove(string $key): bool
    {
        self::assertKey($key);
        if (! array_key_exists($key, $this->resources)) {
            return false;
        }
        unset($this->resources[$key]);
        $this->pendingChanges[$key] = null;

        return true;
    }

    public function flush(): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new ResourceException("Could not create resource directory: {$directory}");
        }
        if (! is_writable($directory)) {
            throw new ResourceException("Resource directory is not writable: {$directory}");
        }
        $lock = @fopen($this->path . '.lock', 'c');
        if ($lock === false) {
            throw new ResourceException("Could not open resource lock file: {$this->path}.lock");
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new ResourceException("Could not lock resource file: {$this->path}");
            }

            $latest = self::readResources($this->path);
            $merged = $this->merge($latest);
            // An untouched handle is a cheap refresh; a new empty file is still
            // materialized on an explicit flush for predictable API behavior.
            if ($this->pendingChanges !== [] || ! is_file($this->path)) {
                $this->writeResources($directory, $merged);
            }
            $this->resources = $merged;
            $this->baseResources = $merged;
            $this->pendingChanges = [];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $latest
     * @return array<string, array<string, mixed>>
     */
    private function merge(array $latest): array
    {
        $merged = $latest;
        foreach ($this->pendingChanges as $key => $change) {
            $baseExists = array_key_exists($key, $this->baseResources);
            $latestExists = array_key_exists($key, $latest);
            $changeExists = $change !== null;
            if (! self::sameEntry($baseExists, $this->baseResources[$key] ?? null, $latestExists, $latest[$key] ?? null)
                && ! self::sameEntry($changeExists, $change, $latestExists, $latest[$key] ?? null)
            ) {
                throw new ResourceException("Resource '{$key}' changed in another instance; reload before resolving the conflict.");
            }
            if ($change === null) {
                unset($merged[$key]);
            } else {
                $merged[$key] = $change;
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private static function sameEntry(bool $leftExists, ?array $left, bool $rightExists, ?array $right): bool
    {
        return $leftExists === $rightExists && (! $leftExists || $left === $right);
    }

    /**
     * @param array<string, array<string, mixed>> $resources
     * @return array{format:string,version:int,resources:array<string,array<string,mixed>>}
     */
    private function document(array $resources): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'resources' => $resources,
        ];
    }

    /** @param array<string, array<string,mixed>> $resources */
    private function writeResources(string $directory, array $resources): void
    {
        try {
            $json = json_encode($this->document($resources), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (JsonException $exception) {
            throw new ResourceException('Could not encode resource file JSON.', 0, $exception);
        }
        $contents = $json . "\n";
        if (strlen($contents) > self::MAX_BYTES) {
            throw new ResourceException("Resource file exceeds the " . self::MAX_BYTES . '-byte limit.');
        }
        $temporary = tempnam($directory, '.tvision-resource-');
        if ($temporary === false) {
            throw new ResourceException("Could not create a temporary resource file in: {$directory}");
        }
        $stream = null;
        try {
            $stream = @fopen($temporary, 'wb');
            if ($stream === false) {
                $stream = null;
                throw new ResourceException("Could not open the temporary resource file: {$temporary}");
            }

            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $chunk = substr($contents, $offset, self::WRITE_CHUNK_BYTES);
                $chunkOffset = 0;
                $chunkLength = strlen($chunk);
                while ($chunkOffset < $chunkLength) {
                    $written = @fwrite($stream, substr($chunk, $chunkOffset));
                    if ($written === false || $written === 0) {
                        throw new ResourceException("Could not completely write the temporary resource file: {$temporary}");
                    }
                    $chunkOffset += $written;
                    $offset += $written;
                }
            }
            if (! @fflush($stream) || (function_exists('fsync') && ! @fsync($stream))) {
                throw new ResourceException("Could not flush the temporary resource file: {$temporary}");
            }
            $closed = @fclose($stream);
            $stream = null;
            if (! $closed || ! @rename($temporary, $this->path)) {
                throw new ResourceException("Could not atomically write resource file: {$this->path}");
            }
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private static function readResources(string $path): array
    {
        if (! file_exists($path)) {
            return self::emptyResources();
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new ResourceException("Resource file is not readable: {$path}");
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new ResourceException("Resource file is too large: {$path}");
        }
        $json = file_get_contents($path);
        if ($json === false || strlen($json) > self::MAX_BYTES) {
            throw new ResourceException("Could not read resource file: {$path}");
        }
        try {
            $document = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ResourceException("Resource file contains malformed JSON: {$path}", 0, $exception);
        }
        if (! is_array($document)) {
            throw new ResourceException('Resource file document must be an object.');
        }

        return self::resourcesFromDocument($document);
    }

    /**
     * @param array<mixed> $document
     * @return array<string, array<string, mixed>>
     */
    private static function resourcesFromDocument(array $document): array
    {
        if (count($document) !== 3
            || ! array_key_exists('format', $document)
            || ! array_key_exists('version', $document)
            || ! array_key_exists('resources', $document)
            || $document['format'] !== self::FORMAT
            || $document['version'] !== self::VERSION
            || ! is_array($document['resources'])
            || array_is_list($document['resources']) && $document['resources'] !== []
        ) {
            throw new ResourceException('Resource file has an unsupported schema or version.');
        }

        $resources = [];
        foreach ($document['resources'] as $key => $encoded) {
            if (! is_string($key)) {
                throw new ResourceException('Resource file keys must be strings.');
            }
            self::assertKey($key);
            if (! is_array($encoded) || array_is_list($encoded)) {
                throw new ResourceException("Resource '{$key}' must contain a streamable object document.");
            }
            $copy = [];
            foreach ($encoded as $encodedKey => $encodedValue) {
                if (! is_string($encodedKey)) {
                    throw new ResourceException("Resource '{$key}' document keys must be strings.");
                }
                $copy[$encodedKey] = $encodedValue;
            }
            $resources[$key] = $copy;
        }

        return $resources;
    }

    /** @return array<string, array<string, mixed>> */
    private static function emptyResources(): array
    {
        return [];
    }

    private static function assertKey(string $key): void
    {
        if ($key === ''
            || strlen($key) > 255
            || str_contains($key, "\0")
            || preg_match('//u', $key) !== 1
            || preg_match('/^(?:0|[1-9][0-9]*)$/', $key) === 1
        ) {
            throw new \InvalidArgumentException('Resource keys must be non-empty UTF-8 strings of at most 255 bytes and cannot be numeric-only.');
        }
    }
}
