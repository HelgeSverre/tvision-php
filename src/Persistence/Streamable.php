<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Persistence;

/**
 * Opt-in contract for values that may be written by StreamCodec.
 *
 * Implementations must return only scalars, arrays, and other Streamable
 * instances from streamData(). They must also validate their own schema in
 * fromStreamData(); a persisted document is input, not trusted application data.
 */
interface Streamable
{
    /** A stable, application-defined identifier. Never use a PHP class name. */
    public static function streamType(): string;

    /** @return array<string, mixed> */
    public function streamData(): array;

    /** @param array<string, mixed> $data */
    public static function fromStreamData(array $data): static;
}
