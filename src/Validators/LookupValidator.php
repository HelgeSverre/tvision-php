<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/** Base class for validators backed by a set or other lookup source. */
abstract class LookupValidator extends Validator
{
    public function isValid(string $input): bool
    {
        return $this->lookup($input);
    }

    abstract public function lookup(string $input): bool;
}
