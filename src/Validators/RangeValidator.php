<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

use InvalidArgumentException;

/** Validates a signed PHP integer in an inclusive range and owns int transfer. */
final class RangeValidator extends FilterValidator
{
    public function __construct(
        public readonly int $min,
        public readonly int $max,
    ) {
        if ($min > $max) {
            throw new InvalidArgumentException('RangeValidator minimum cannot exceed maximum.');
        }

        parent::__construct($min < 0 ? '+-0123456789' : '+0123456789');
        $this->options |= self::OptionTransfer;
    }

    public function isValid(string $input): bool
    {
        if (!parent::isValid($input) || preg_match('/^[+-]?\d+$/D', $input) !== 1) {
            return false;
        }

        $negative = $input[0] === '-';
        $digits = ltrim($input, '+-');
        $unsigned = ltrim($digits, '0');
        $unsigned = $unsigned === '' ? '0' : $unsigned;
        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($unsigned) > strlen($limit)
            || (strlen($unsigned) === strlen($limit) && strcmp($unsigned, $limit) > 0)
        ) {
            return false;
        }
        $value = (int) $input;

        return $value >= $this->min && $value <= $this->max;
    }

    /**
     * Editing accepts syntactically valid integer prefixes; range enforcement is
     * deliberately deferred to final validation. This lets a user clear a field,
     * type a sign, or pass through `1` while entering a range such as 10..20.
     */
    public function isValidInput(string &$input, bool $suppressFill = false): bool
    {
        if (preg_match('/^[+]?\d*$/D', $input) === 1) {
            return true;
        }

        return $this->min < 0 && preg_match('/^-\d*$/D', $input) === 1;
    }

    /** @throws \InvalidArgumentException when the stored value is not scalar during a Valid transfer */
    public function transfer(string &$input, mixed &$value, ValidatorTransfer $operation): int
    {
        if ($operation === ValidatorTransfer::DataSize) {
            // PHP ints are platform-sized. The size is only a logical form-layout
            // hint here; unlike C++, callers never pointer-walk a byte record.
            return PHP_INT_SIZE;
        }

        if ($operation === ValidatorTransfer::SetData) {
            if (!is_int($value)) {
                throw new InvalidArgumentException('RangeValidator expects an int when setting data.');
            }
            $input = (string) $value;

            return PHP_INT_SIZE;
        }

        if (!$this->isValid($input)) {
            return 0;
        }
        $value = (int) $input;

        return PHP_INT_SIZE;
    }

    public function error(): void
    {
        $this->setError(sprintf('Value is not in the range %d to %d.', $this->min, $this->max));
    }
}
