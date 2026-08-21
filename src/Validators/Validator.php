<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/**
 * Base contract for InputLine validators, modelled after Turbo Vision's
 * TValidator but without its pointer-sized record API.
 */
class Validator
{
    public const int StatusOk = 0;

    public const int StatusSyntax = 1;

    /** Picture validators insert literal separators as input is completed. */
    public const int OptionFill = 0x0001;

    /** The validator owns InputLine's native data conversion. */
    public const int OptionTransfer = 0x0002;

    public int $status = self::StatusOk;

    public int $options = 0;

    public private(set) ?string $lastError = null;

    /**
     * Validate an editable, possibly incomplete value. Implementations may format
     * it in place (notably PictureValidator's auto-fill mode).
     */
    public function isValidInput(string &$input, bool $suppressFill = false): bool
    {
        return true;
    }

    /** Validate a completed value. */
    public function isValid(string $input): bool
    {
        return true;
    }

    /** Validate and record an error suitable for a host dialog to present. */
    public function validate(string $input): bool
    {
        $this->lastError = null;
        if ($this->isValid($input)) {
            return true;
        }

        $this->error();

        return false;
    }

    /** Override for a custom error presentation. Default is deliberately non-UI. */
    public function error(): void {}

    /**
     * Give validators a chance to convert between rendered text and native data.
     * Return zero to have InputLine use ordinary string transfer.
     *
     * `$value` is intentionally by-reference: SetData reads it, GetData writes it.
     */
    public function transfer(string &$input, mixed &$value, ValidatorTransfer $operation): int
    {
        return 0;
    }

    protected function setError(string $message): void
    {
        $this->lastError = $message;
    }
}
