<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Bios;

/**
 * A named group of BiosField objects representing one tab in the BIOS UI.
 */
final class BiosTab
{
    /**
     * @param list<BiosField> $fields
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields = [],
    ) {}
}
