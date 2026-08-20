<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

enum RepeatRule: string
{
    case Never = 'NONE';
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Yearly = 'YEARLY';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
            self::Monthly => 'Every month',
            self::Yearly => 'Every year',
        };
    }

    public function next(int $direction = 1): self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);
        $index = is_int($index) ? $index : 0;
        $count = count($cases);

        return $cases[(($index + $direction) % $count + $count) % $count];
    }
}
