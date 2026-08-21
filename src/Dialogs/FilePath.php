<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

/** Filesystem-only path helpers for the standard dialogs; never invoke a shell. */
final class FilePath
{
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    public static function hasWildcard(string $path): bool
    {
        return strpbrk($path, '*?[') !== false;
    }

    public static function normalise(string $path, ?string $baseDirectory = null): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $baseDirectory ??= getcwd() ?: '/';
        $baseDirectory = str_replace('\\', '/', $baseDirectory);

        if ($path === '') {
            $path = $baseDirectory;
        } elseif (! self::isAbsolute($path)) {
            $path = rtrim($baseDirectory, '/') . '/' . $path;
        }

        [$prefix, $path] = self::root($path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts !== []) {
                    array_pop($parts);
                }

                continue;
            }
            $parts[] = $part;
        }

        return $prefix . implode('/', $parts);
    }

    /**
     * @return array{0:string,1:string} immutable root prefix and remaining path
     */
    private static function root(string $path): array
    {
        if (preg_match('#^([A-Za-z]:)/+(.*)$#', $path, $matches) === 1) {
            return [$matches[1] . '/', $matches[2]];
        }
        if (str_starts_with($path, '//')) {
            $segments = array_values(array_filter(explode('/', substr($path, 2)), static fn (string $part): bool => $part !== ''));
            if (count($segments) >= 2) {
                $server = array_shift($segments);
                $share = array_shift($segments);

                return ['//' . $server . '/' . $share . '/', implode('/', $segments)];
            }
        }
        if (str_starts_with($path, '/')) {
            return ['/', substr($path, 1)];
        }

        return ['', $path];
    }

    /** @return array{directory: string, pattern: string} */
    public static function splitPattern(string $value, ?string $baseDirectory = null): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['directory' => self::existingDirectory($baseDirectory), 'pattern' => '*'];
        }

        $absolute = self::normalise($value, $baseDirectory);
        if (! self::hasWildcard($absolute) && is_dir($absolute)) {
            return ['directory' => self::existingDirectory($absolute), 'pattern' => '*'];
        }

        $directory = dirname($absolute);
        $pattern = basename($absolute);
        if ($pattern === '' || $pattern === '.' || $pattern === DIRECTORY_SEPARATOR) {
            $pattern = '*';
        }

        return ['directory' => self::existingDirectory($directory), 'pattern' => $pattern];
    }

    public static function existingDirectory(?string $path = null): string
    {
        $path ??= getcwd() ?: '/';
        $resolved = realpath($path);

        return $resolved !== false && is_dir($resolved) ? $resolved : self::normalise($path);
    }

    public static function join(string $directory, string $name): string
    {
        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . ltrim($name, '/\\');
    }

    public static function matches(string $name, string $pattern): bool
    {
        if ($pattern === '' || $pattern === '*') {
            return true;
        }

        return fnmatch($pattern, $name, defined('FNM_PERIOD') ? FNM_PERIOD : 0);
    }
}
