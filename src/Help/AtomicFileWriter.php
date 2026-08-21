<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

/** Safely replace generated artifacts without exposing a partially-written target. */
final class AtomicFileWriter
{
    public static function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            throw new \RuntimeException("Output directory '{$directory}' does not exist.");
        }
        if (is_dir($path)) {
            throw new \RuntimeException("Output target '{$path}' is a directory.");
        }

        $temporary = tempnam($directory, '.tvphp-');
        if ($temporary === false) {
            throw new \RuntimeException("Unable to create a temporary output file in '{$directory}'.");
        }

        try {
            $stream = @fopen($temporary, 'wb');
            if ($stream === false) {
                throw new \RuntimeException("Unable to open temporary output file '{$temporary}'.");
            }
            try {
                self::writeAll($stream, $contents, $temporary);
                if (! fflush($stream)) {
                    throw new \RuntimeException("Unable to flush temporary output file '{$temporary}'.");
                }
                if (function_exists('fsync') && ! fsync($stream)) {
                    throw new \RuntimeException("Unable to synchronize temporary output file '{$temporary}'.");
                }
            } finally {
                fclose($stream);
            }

            // rename() within one directory is atomic on the supported POSIX targets.
            // Until it succeeds the existing target is left intact.
            if (! @rename($temporary, $path)) {
                throw new \RuntimeException("Unable to atomically replace '{$path}'.");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @param resource $stream */
    private static function writeAll($stream, string $contents, string $temporary): void
    {
        $length = strlen($contents);
        $written = 0;
        while ($written < $length) {
            $count = fwrite($stream, substr($contents, $written));
            if ($count === false || $count === 0) {
                throw new \RuntimeException("Unable to write temporary output file '{$temporary}'.");
            }
            $written += $count;
        }
    }
}
