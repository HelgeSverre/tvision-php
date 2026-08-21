<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Collections;

use DateTimeImmutable;

/** A filesystem entry, corresponding to Turbo Vision's TSearchRec. */
final readonly class SearchRec
{
    public const int Archive = 0x01;
    public const int Directory = 0x02;
    public const int ReadOnly = 0x04;

    public function __construct(
        public string $name,
        public string $path,
        public int $attributes,
        public int $size,
        public ?DateTimeImmutable $modifiedAt,
    ) {
    }

    public function isDirectory(): bool
    {
        return ($this->attributes & self::Directory) !== 0;
    }

    public function isReadOnly(): bool
    {
        return ($this->attributes & self::ReadOnly) !== 0;
    }

    public static function fromPath(string $path, ?string $name = null): self
    {
        $name ??= basename($path);
        $isDirectory = is_dir($path);
        $attributes = self::Archive;
        if ($isDirectory) {
            $attributes |= self::Directory;
        }
        if (file_exists($path) && !is_writable($path)) {
            $attributes |= self::ReadOnly;
        }

        $mtime = @filemtime($path);
        $modifiedAt = $mtime === false ? null : (new DateTimeImmutable())->setTimestamp($mtime);
        $size = $isDirectory ? 0 : ((@filesize($path)) ?: 0);

        return new self($name, $path, $attributes, $size, $modifiedAt);
    }

    public static function parent(string $directory): self
    {
        return new self('..', dirname(rtrim($directory, DIRECTORY_SEPARATOR)), self::Directory, 0, null);
    }
}
