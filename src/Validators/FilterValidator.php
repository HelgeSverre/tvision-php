<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

use HelgeSverre\TurboVision\Drawing\TerminalText;

/** An allow-list validator for text fields. */
class FilterValidator extends Validator
{
    public function __construct(public private(set) string $validChars)
    {
    }

    public function isValidInput(string &$input, bool $suppressFill = false): bool
    {
        return $this->isValid($input);
    }

    public function isValid(string $input): bool
    {
        foreach (TerminalText::graphemes($input) as $character) {
            if (!str_contains($this->validChars, $character)) {
                return false;
            }
        }

        return true;
    }

    public function error(): void
    {
        $this->setError('Invalid character in input.');
    }
}
