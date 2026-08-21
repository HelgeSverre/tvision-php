<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Support;

use Closure;

/**
 * Safely replace a file without ever exposing a partially-written target: the
 * payload is written to a temporary sibling, flushed + fsynced, mode-corrected,
 * then renamed over the target atomically.
 */
final class AtomicFileWriter
{
    /**
     * @param (Closure(string):\Throwable)|null $failure builds the thrown exception for
     *                                                    infrastructure failures; defaults to RuntimeException so domain callers can
     *                                                    raise their own type without duplicating the machinery
     */
    public static function write(string $path, string $contents, ?Closure $failure = null): void
    {
        $fail = $failure ?? static fn (string $message): \RuntimeException => new \RuntimeException($message);

        $directory = dirname($path);
        if (! is_dir($directory)) {
            throw $fail("Output directory '{$directory}' does not exist.");
        }
        if (is_dir($path)) {
            throw $fail("Output target '{$path}' is a directory.");
        }

        $temporary = tempnam($directory, '.tvphp-');
        if ($temporary === false) {
            throw $fail("Unable to create a temporary output file in '{$directory}'.");
        }

        try {
            $stream = @fopen($temporary, 'wb');
            if ($stream === false) {
                throw $fail("Unable to open temporary output file '{$temporary}'.");
            }
            try {
                self::writeAll($stream, $contents, $temporary, $fail);
                if (! fflush($stream)) {
                    throw $fail("Unable to flush temporary output file '{$temporary}'.");
                }
                if (function_exists('fsync') && ! fsync($stream)) {
                    throw $fail("Unable to synchronize temporary output file '{$temporary}'.");
                }
            } finally {
                fclose($stream);
            }

            // tempnam() creates the file 0600; a replaced target would silently
            // lose its readability for other users. Preserve the replaced file's
            // mode, or fall back to the ordinary umask-derived permissions.
            $mode = is_file($path)
                ? (fileperms($path) & 0777)
                : (0666 & ~umask());
            if ($mode > 0 && ! @chmod($temporary, $mode)) {
                throw $fail("Unable to set permissions on temporary output file '{$temporary}'.");
            }

            // rename() within one directory is atomic on the supported POSIX targets.
            // Until it succeeds the existing target is left intact.
            if (! @rename($temporary, $path)) {
                throw $fail("Unable to atomically replace '{$path}'.");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * @param resource              $stream
     * @param Closure(string):\Throwable $fail
     */
    private static function writeAll($stream, string $contents, string $temporary, Closure $fail): void
    {
        $length = strlen($contents);
        $written = 0;
        while ($written < $length) {
            $count = fwrite($stream, substr($contents, $written));
            if ($count === false || $count === 0) {
                throw $fail("Unable to write temporary output file '{$temporary}'.");
            }
            $written += $count;
        }
    }
}
