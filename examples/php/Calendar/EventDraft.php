<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EventDraft
{
    public function __construct(
        public string $uid,
        public string $title,
        public string $startDate,
        public string $startTime,
        public string $endDate,
        public string $endTime,
        public bool $allDay,
        public string $location,
        public string $calendar,
        public RepeatRule $repeat,
        public string $notes,
        public ?DateTimeImmutable $recurrenceUntil = null,
        public ?int $recurrenceCount = null,
    ) {}

    public static function create(DateTimeImmutable $day): self
    {
        return new self(
            uid: CalendarEvent::newUid(),
            title: '',
            startDate: $day->format('Y-m-d'),
            startTime: '09:00',
            endDate: $day->format('Y-m-d'),
            endTime: '10:00',
            allDay: false,
            location: '',
            calendar: 'Personal',
            repeat: RepeatRule::Never,
            notes: '',
        );
    }

    public static function fromEvent(CalendarEvent $event): self
    {
        return new self(
            uid: $event->uid,
            title: $event->title,
            startDate: $event->start->format('Y-m-d'),
            startTime: self::editableTime($event->start),
            endDate: $event->allDay
                ? $event->end->modify('-1 day')->format('Y-m-d')
                : $event->end->format('Y-m-d'),
            endTime: self::editableTime($event->end),
            allDay: $event->allDay,
            location: $event->location,
            calendar: $event->calendar,
            repeat: $event->repeat,
            notes: $event->notes,
            recurrenceUntil: $event->recurrenceUntil,
            recurrenceCount: $event->recurrenceCount,
        );
    }

    public function value(EventField $field): string
    {
        return match ($field) {
            EventField::Title => $this->title,
            EventField::StartDate => $this->startDate,
            EventField::StartTime => $this->startTime,
            EventField::EndDate => $this->endDate,
            EventField::EndTime => $this->endTime,
            EventField::AllDay => $this->allDay ? 'Yes' : 'No',
            EventField::Location => $this->location,
            EventField::Calendar => $this->calendar,
            EventField::Repeat => $this->repeat->label(),
            EventField::Notes => str_replace(["\r", "\n"], ['', ' '], $this->notes),
        };
    }

    public function setValue(EventField $field, string $value): void
    {
        match ($field) {
            EventField::Title => $this->title = $value,
            EventField::StartDate => $this->startDate = $value,
            EventField::StartTime => $this->startTime = $value,
            EventField::EndDate => $this->endDate = $value,
            EventField::EndTime => $this->endTime = $value,
            EventField::Location => $this->location = $value,
            EventField::Calendar => $this->calendar = $value,
            EventField::Notes => $this->notes = $value,
            EventField::AllDay, EventField::Repeat => null,
        };
    }

    public function toEvent(DateTimeZone $timezone): CalendarEvent
    {
        $title = trim($this->title);
        if ($title === '') {
            throw new InvalidArgumentException('Give the event a title.');
        }

        if ($this->allDay) {
            $start = $this->dateValue($this->startDate, $timezone);
            $lastDay = $this->dateValue($this->endDate, $timezone);
            if ($lastDay < $start) {
                throw new InvalidArgumentException('The end date must not be before the start date.');
            }
            $end = $lastDay->modify('+1 day');
        } else {
            $start = $this->dateTimeValue($this->startDate, $this->startTime, $timezone);
            $end = $this->dateTimeValue($this->endDate, $this->endTime, $timezone);
            if ($end <= $start) {
                throw new InvalidArgumentException('The end time must be after the start time.');
            }
        }

        return new CalendarEvent(
            uid: $this->uid,
            title: $title,
            start: $start,
            end: $end,
            allDay: $this->allDay,
            location: trim($this->location),
            notes: trim($this->notes),
            calendar: trim($this->calendar) !== '' ? trim($this->calendar) : 'Personal',
            repeat: $this->repeat,
            recurrenceUntil: $this->recurrenceUntil,
            recurrenceCount: $this->recurrenceCount,
        );
    }

    private function dateValue(string $date, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        return $this->validated($value, "Use a date like 2026-08-20.");
    }

    private function dateTimeValue(string $date, string $time, DateTimeZone $timezone): DateTimeImmutable
    {
        $format = preg_match('/^\d{2}:\d{2}:\d{2}$/D', $time) === 1
            ? '!Y-m-d H:i:s'
            : '!Y-m-d H:i';
        $value = DateTimeImmutable::createFromFormat($format, "{$date} {$time}", $timezone);

        return $this->validated($value, 'Use 24-hour times like 09:30 or 09:30:45.');
    }

    private static function editableTime(DateTimeImmutable $value): string
    {
        return $value->format('s') === '00' ? $value->format('H:i') : $value->format('H:i:s');
    }

    private function validated(DateTimeImmutable|false $value, string $message): DateTimeImmutable
    {
        $errors = DateTimeImmutable::getLastErrors();
        if ($value === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
