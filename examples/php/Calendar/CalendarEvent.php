<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateTimeImmutable;

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
            $this->end = $this->allDay
                ? $this->start->modify('+1 day')
                : $this->start->modify('+1 hour');
        }
        if ($this->recurrenceCount !== null && $this->recurrenceCount < 1) {
            $this->recurrenceCount = null;
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

        $first = $this->start->setTime(0, 0);
        if ($dayStart < $first) {
            return false;
        }
        $occurrenceStart = $dayStart->setTime(
            (int) $this->start->format('H'),
            (int) $this->start->format('i'),
            (int) $this->start->format('s'),
        );
        if ($this->recurrenceUntil !== null
            && $occurrenceStart > $this->recurrenceUntil->setTimezone($timezone)
        ) {
            return false;
        }

        $days = (int) $first->diff($dayStart)->format('%a');
        $months = ((int) $dayStart->format('Y') - (int) $first->format('Y')) * 12
            + (int) $dayStart->format('n') - (int) $first->format('n');
        $years = (int) $dayStart->format('Y') - (int) $first->format('Y');

        $matches = match ($this->repeat) {
            RepeatRule::Daily => true,
            RepeatRule::Weekly => $days % 7 === 0,
            RepeatRule::Monthly => $dayStart->format('j') === $first->format('j'),
            RepeatRule::Yearly => $dayStart->format('m-d') === $first->format('m-d'),
        };
        if (! $matches) {
            return false;
        }

        if ($this->recurrenceCount === null) {
            return true;
        }

        $occurrence = match ($this->repeat) {
            RepeatRule::Daily => $days + 1,
            RepeatRule::Weekly => intdiv($days, 7) + 1,
            RepeatRule::Monthly => $this->monthlyOccurrence($first, $months),
            RepeatRule::Yearly => $this->yearlyOccurrence($first, $years),
        };

        return $occurrence <= $this->recurrenceCount;
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
