<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateTimeImmutable;
use InvalidArgumentException;

final class CalendarEvent
{
    public function __construct(
        public string $uid,
        public string $title,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay = false,
        public string $location = '',
        public string $notes = '',
        public string $calendar = 'Personal',
        public RepeatRule $repeat = RepeatRule::Never,
        public ?DateTimeImmutable $recurrenceUntil = null,
        public ?int $recurrenceCount = null,
    ) {
        if ($this->end <= $this->start) {
            throw new InvalidArgumentException('Event end must be after event start.');
        }
        if ($this->allDay
            && ($this->start->format('His.u') !== '000000.000000'
                || $this->end->format('His.u') !== '000000.000000')
        ) {
            throw new InvalidArgumentException('All-day event boundaries must be at midnight.');
        }
        if ($this->recurrenceCount !== null && $this->recurrenceCount < 1) {
            throw new InvalidArgumentException('Recurrence count must be a positive integer.');
        }
        if ($this->recurrenceCount !== null && $this->recurrenceUntil !== null) {
            throw new InvalidArgumentException('Recurrence cannot have both a count and an end date.');
        }
    }

    public static function newUid(): string
    {
        return bin2hex(random_bytes(12)) . '@tvision-php';
    }

    public function occursOn(DateTimeImmutable $day): bool
    {
        $timezone = $this->start->getTimezone();
        $dayStart = $day->setTimezone($timezone)->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        if ($this->repeat === RepeatRule::Never) {
            return $this->start < $dayEnd && $this->end > $dayStart;
        }

        $cutoff = $dayEnd->modify('-1 second');
        if ($this->recurrenceUntil !== null) {
            $until = $this->recurrenceUntil->setTimezone($timezone);
            if ($until < $cutoff) {
                $cutoff = $until;
            }
        }

        $occurrenceStart = $this->latestOccurrenceAtOrBefore($cutoff);
        if ($occurrenceStart === null) {
            return false;
        }

        if ($this->recurrenceCount !== null) {
            $occurrenceNumber = $this->occurrenceNumber($occurrenceStart);
            if ($occurrenceNumber > $this->recurrenceCount) {
                $occurrenceStart = $this->occurrenceForNumber($this->recurrenceCount);
            }
        }

        $occurrenceEnd = $occurrenceStart->add($this->start->diff($this->end));

        return $occurrenceStart < $dayEnd && $occurrenceEnd > $dayStart;
    }

    private function latestOccurrenceAtOrBefore(DateTimeImmutable $cutoff): ?DateTimeImmutable
    {
        if ($cutoff < $this->start) {
            return null;
        }

        $hour = (int) $this->start->format('H');
        $minute = (int) $this->start->format('i');
        $second = (int) $this->start->format('s');
        $firstDay = $this->start->setTime(0, 0);
        $cutoffDay = $cutoff->setTime(0, 0);

        if ($this->repeat === RepeatRule::Daily || $this->repeat === RepeatRule::Weekly) {
            $days = (int) $firstDay->diff($cutoffDay)->format('%a');
            $step = $this->repeat === RepeatRule::Daily ? 1 : 7;
            $candidate = $cutoffDay->modify('-' . ($days % $step) . ' days')->setTime($hour, $minute, $second);
            if ($candidate > $cutoff) {
                $candidate = $candidate->modify("-{$step} days");
            }

            return $candidate >= $this->start ? $candidate : null;
        }

        if ($this->repeat === RepeatRule::Monthly) {
            $day = (int) $this->start->format('j');
            $month = $cutoff->modify('first day of this month');
            while ($month >= $firstDay->modify('first day of this month')) {
                $year = (int) $month->format('Y');
                $monthNumber = (int) $month->format('n');
                if (checkdate($monthNumber, $day, $year)) {
                    $candidate = $month->setDate($year, $monthNumber, $day)->setTime($hour, $minute, $second);
                    if ($candidate <= $cutoff && $candidate >= $this->start) {
                        return $candidate;
                    }
                }
                $month = $month->modify('-1 month');
            }

            return null;
        }

        $monthNumber = (int) $this->start->format('n');
        $day = (int) $this->start->format('j');
        $year = (int) $cutoff->format('Y');
        $firstYear = (int) $this->start->format('Y');
        while ($year >= $firstYear) {
            if (checkdate($monthNumber, $day, $year)) {
                $candidate = $cutoff->setDate($year, $monthNumber, $day)->setTime($hour, $minute, $second);
                if ($candidate <= $cutoff && $candidate >= $this->start) {
                    return $candidate;
                }
            }
            $year--;
        }

        return null;
    }

    private function occurrenceNumber(DateTimeImmutable $occurrence): int
    {
        $first = $this->start->setTime(0, 0);
        $occurrenceDay = $occurrence->setTime(0, 0);
        $days = (int) $first->diff($occurrenceDay)->format('%a');
        $months = ((int) $occurrenceDay->format('Y') - (int) $first->format('Y')) * 12
            + (int) $occurrenceDay->format('n') - (int) $first->format('n');
        $years = (int) $occurrenceDay->format('Y') - (int) $first->format('Y');

        return match ($this->repeat) {
            RepeatRule::Daily => $days + 1,
            RepeatRule::Weekly => intdiv($days, 7) + 1,
            RepeatRule::Monthly => $this->monthlyOccurrence($first, $months),
            RepeatRule::Yearly => $this->yearlyOccurrence($first, $years),
            RepeatRule::Never => 1,
        };
    }

    private function occurrenceForNumber(int $number): DateTimeImmutable
    {
        if ($this->repeat === RepeatRule::Daily) {
            return $this->start->modify('+' . ($number - 1) . ' days');
        }
        if ($this->repeat === RepeatRule::Weekly) {
            return $this->start->modify('+' . (($number - 1) * 7) . ' days');
        }

        $month = $this->start->modify('first day of this month');
        $monthNumber = (int) $this->start->format('n');
        $day = (int) $this->start->format('j');
        $year = (int) $this->start->format('Y');
        $seen = 0;
        while (true) {
            if ($this->repeat === RepeatRule::Monthly) {
                $monthNumber = (int) $month->format('n');
                $year = (int) $month->format('Y');
            }
            if (checkdate($monthNumber, $day, $year) && ++$seen === $number) {
                return $this->start->setDate($year, $monthNumber, $day);
            }

            if ($this->repeat === RepeatRule::Monthly) {
                $month = $month->modify('+1 month');
            } else {
                $year++;
            }
        }
    }

    private function monthlyOccurrence(DateTimeImmutable $first, int $months): int
    {
        $day = (int) $first->format('j');
        $occurrence = 0;
        $month = $first->modify('first day of this month');
        for ($offset = 0; $offset <= $months; $offset++) {
            $candidate = $month->modify("+{$offset} months");
            if ($day <= (int) $candidate->format('t')) {
                $occurrence++;
            }
        }

        return $occurrence;
    }

    private function yearlyOccurrence(DateTimeImmutable $first, int $years): int
    {
        $month = (int) $first->format('n');
        $day = (int) $first->format('j');
        $firstYear = (int) $first->format('Y');
        $occurrence = 0;
        for ($offset = 0; $offset <= $years; $offset++) {
            if (checkdate($month, $day, $firstYear + $offset)) {
                $occurrence++;
            }
        }

        return $occurrence;
    }

    public function timeLabel(): string
    {
        if ($this->allDay) {
            return 'all-day';
        }

        return $this->start->format('H:i') . '–' . $this->end->format('H:i');
    }
}
