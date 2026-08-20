<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use UnexpectedValueException;

/**
 * Focused RFC 5545 reader/writer for the event data used by the demo.
 *
 * Unknown calendar and VEVENT properties are intentionally ignored, allowing files
 * exported by Apple Calendar and other providers to retain their useful core data.
 */
final class ICalendarStore
{
    private const int MAX_FILE_BYTES = 2_000_000;

    public function __construct(
        private readonly DateTimeZone $timezone,
    ) {}

    /** @return list<CalendarEvent> */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $size = @filesize($path);
        if ($size !== false && $size > self::MAX_FILE_BYTES) {
            throw new RuntimeException("Calendar file is larger than 2 MB: {$path}");
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read calendar file: {$path}");
        }
        if (strlen($contents) > self::MAX_FILE_BYTES) {
            throw new RuntimeException("Calendar file is larger than 2 MB: {$path}");
        }

        return $this->parse($contents);
    }

    /** @param list<CalendarEvent> $events */
    public function save(string $path, array $events): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create calendar directory: {$directory}");
        }

        $temporary = tempnam($directory, '.calendar-');
        if ($temporary === false) {
            throw new RuntimeException("Unable to create a temporary calendar file in: {$directory}");
        }

        try {
            $bytes = file_put_contents($temporary, $this->serialize($events), LOCK_EX);
            if ($bytes === false || ! rename($temporary, $path)) {
                throw new RuntimeException("Unable to save calendar file: {$path}");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @return list<CalendarEvent> */
    public function parse(string $contents): array
    {
        $lines = $this->unfold($contents);
        if ($lines !== []) {
            $lines[0] = ltrim($lines[0], "\xEF\xBB\xBF");
        }
        if (strtoupper($lines[0] ?? '') !== 'BEGIN:VCALENDAR'
            || strtoupper($lines[array_key_last($lines)] ?? '') !== 'END:VCALENDAR'
        ) {
            throw new UnexpectedValueException('Invalid iCalendar envelope.');
        }

        $events = [];
        $insideEvent = false;
        $eventLines = [];
        $eventNumber = 0;
        $uids = [];
        $componentStack = [];

        foreach (array_slice($lines, 1, -1) as $line) {
            if ($insideEvent) {
                $upper = strtoupper($line);
                if ($upper === 'BEGIN:VEVENT') {
                    throw new UnexpectedValueException('Nested VEVENT components are not supported.');
                }
                if ($upper !== 'END:VEVENT') {
                    $eventLines[] = $line;

                    continue;
                }

                $eventNumber++;
                $event = $this->parseEventNumber($eventLines, $eventNumber);
                if (isset($uids[$event->uid])) {
                    throw new UnexpectedValueException("Duplicate iCalendar UID: {$event->uid}");
                }
                $uids[$event->uid] = true;
                $events[] = $event;
                $insideEvent = false;
                $eventLines = [];

                continue;
            }

            $content = $this->parseContentLine($line);
            if ($content === null) {
                throw new UnexpectedValueException("Malformed iCalendar content line: {$line}");
            }
            $component = strtoupper($content['value']);
            if ($content['name'] === 'BEGIN') {
                if ($component === 'VCALENDAR') {
                    throw new UnexpectedValueException('Nested VCALENDAR components are not supported.');
                }
                if ($component === 'VEVENT') {
                    if ($componentStack !== []) {
                        throw new UnexpectedValueException('VEVENT must be a direct child of VCALENDAR.');
                    }
                    $insideEvent = true;
                    $eventLines = [];
                } else {
                    $componentStack[] = $component;
                }

                continue;
            }
            if ($content['name'] === 'END') {
                if ($component === 'VEVENT') {
                    throw new UnexpectedValueException('Unexpected END:VEVENT.');
                }
                if ($component === 'VCALENDAR') {
                    throw new UnexpectedValueException('Unexpected END:VCALENDAR.');
                }
                $expected = array_pop($componentStack);
                if ($expected === null || $expected !== $component) {
                    throw new UnexpectedValueException("Mismatched iCalendar component end: {$content['value']}");
                }
            }
        }
        if ($insideEvent) {
            throw new UnexpectedValueException('Unterminated VEVENT component.');
        }
        if ($componentStack !== []) {
            throw new UnexpectedValueException('Unterminated iCalendar component.');
        }

        return $events;
    }

    /** @param list<string> $lines */
    private function parseEventNumber(array $lines, int $number): CalendarEvent
    {
        try {
            return $this->parseEvent($lines);
        } catch (\Throwable $exception) {
            throw new UnexpectedValueException(
                "Invalid VEVENT #{$number}: {$exception->getMessage()}",
                0,
                $exception,
            );
        }
    }

    /** @param list<CalendarEvent> $events */
    public function serialize(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//HelgeSverre//TurboVision PHP Calendar//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:TurboVision Calendar',
        ];

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $this->escapeText($event->uid);
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escapeText($event->title);

            if ($event->allDay) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $event->start->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . $event->end->format('Ymd');
            } else {
                $lines[] = 'DTSTART:' . $event->start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
                $lines[] = 'DTEND:' . $event->end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            }

            if ($event->location !== '') {
                $lines[] = 'LOCATION:' . $this->escapeText($event->location);
            }
            if ($event->notes !== '') {
                $lines[] = 'DESCRIPTION:' . $this->escapeText($event->notes);
            }
            if ($event->calendar !== '') {
                $lines[] = 'CATEGORIES:' . $this->escapeText($event->calendar);
            }
            if ($event->repeat !== RepeatRule::Never) {
                $rule = 'FREQ=' . $event->repeat->value;
                if ($event->recurrenceCount !== null) {
                    $rule .= ';COUNT=' . $event->recurrenceCount;
                } elseif ($event->recurrenceUntil !== null) {
                    $rule .= ';UNTIL=' . $event->recurrenceUntil
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format('Ymd\THis\Z');
                }
                $lines[] = 'RRULE:' . $rule;
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $folded = array_map($this->foldLine(...), $lines);

        return implode("\r\n", $folded) . "\r\n";
    }

    /** @return list<string> */
    private function unfold(string $contents): array
    {
        $physical = preg_split('/\r\n|\n|\r/', $contents);
        if ($physical === false) {
            return [];
        }

        $logical = [];
        foreach ($physical as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $logical !== []) {
                $last = array_key_last($logical);
                $logical[$last] .= substr($line, 1);
            } elseif ($line !== '') {
                $logical[] = $line;
            }
        }

        return $logical;
    }

    /** @param list<string> $lines */
    private function parseEvent(array $lines): CalendarEvent
    {
        $uid = '';
        $title = '(Untitled Event)';
        $location = '';
        $notes = '';
        $calendar = 'Imported';
        $start = null;
        $end = null;
        $endAllDay = false;
        $duration = null;
        $allDay = false;
        $repeat = RepeatRule::Never;
        $recurrenceUntil = null;
        $recurrenceCount = null;
        $nestedComponents = [];
        $seen = [];

        foreach ($lines as $line) {
            $content = $this->parseContentLine($line);
            if ($content === null) {
                throw new UnexpectedValueException("Malformed iCalendar content line: {$line}");
            }

            if ($content['name'] === 'BEGIN') {
                $nestedComponents[] = strtoupper($content['value']);

                continue;
            }
            if ($content['name'] === 'END') {
                $component = strtoupper($content['value']);
                $expected = array_pop($nestedComponents);
                if ($expected === null || $expected !== $component) {
                    throw new UnexpectedValueException("Unexpected nested component end: {$content['value']}");
                }

                continue;
            }
            if ($nestedComponents !== []) {
                continue;
            }

            if (in_array($content['name'], [
                'UID',
                'SUMMARY',
                'LOCATION',
                'DESCRIPTION',
                'CATEGORIES',
                'DTSTART',
                'DTEND',
                'DURATION',
                'RRULE',
            ], true)) {
                if (isset($seen[$content['name']])) {
                    throw new UnexpectedValueException("Duplicate VEVENT property: {$content['name']}");
                }
                $seen[$content['name']] = true;
            }

            switch ($content['name']) {
                case 'UID':
                    $uid = $this->unescapeText($content['value']);
                    break;
                case 'SUMMARY':
                    $title = $this->unescapeText($content['value']);
                    break;
                case 'LOCATION':
                    $location = $this->unescapeText($content['value']);
                    break;
                case 'DESCRIPTION':
                    $notes = $this->unescapeText($content['value']);
                    break;
                case 'CATEGORIES':
                    $calendar = $this->unescapeText($this->firstListValue($content['value']));
                    break;
                case 'DTSTART':
                    [$start, $allDay] = $this->parseDateValue($content['value'], $content['params']);
                    break;
                case 'DTEND':
                    [$end, $endAllDay] = $this->parseDateValue($content['value'], $content['params']);
                    break;
                case 'DURATION':
                    $duration = new DateInterval($content['value']);
                    break;
                case 'RRULE':
                    [$repeat, $recurrenceUntil, $recurrenceCount] = $this->parseRecurrence($content['value']);
                    break;
                case 'EXDATE':
                case 'RDATE':
                case 'RECURRENCE-ID':
                case 'EXRULE':
                    throw new UnexpectedValueException("Unsupported recurrence property: {$content['name']}");
            }
        }

        if ($nestedComponents !== []) {
            throw new UnexpectedValueException('Unterminated nested iCalendar component.');
        }
        if ($start === null) {
            throw new UnexpectedValueException('VEVENT is missing DTSTART.');
        }
        if ($uid === '') {
            throw new UnexpectedValueException('VEVENT is missing UID.');
        }
        if ($end !== null && $duration !== null) {
            throw new UnexpectedValueException('VEVENT must not contain both DTEND and DURATION.');
        }
        if ($end !== null && $allDay !== $endAllDay) {
            throw new UnexpectedValueException('DTSTART and DTEND must use the same value type.');
        }
        if ($end === null && $duration !== null) {
            $end = $start->add($duration);
        }
        $end ??= $allDay ? $start->modify('+1 day') : $start->modify('+1 hour');

        return new CalendarEvent(
            uid: $uid,
            title: $title,
            start: $start,
            end: $end,
            allDay: $allDay,
            location: $location,
            notes: $notes,
            calendar: $calendar,
            repeat: $repeat,
            recurrenceUntil: $recurrenceUntil,
            recurrenceCount: $recurrenceCount,
        );
    }

    /**
     * @return array{name:string, params:array<string,string>, value:string}|null
     */
    private function parseContentLine(string $line): ?array
    {
        $separator = strpos($line, ':');
        if ($separator === false) {
            return null;
        }

        $left = substr($line, 0, $separator);
        $value = substr($line, $separator + 1);
        $parts = explode(';', $left);
        $name = strtoupper((string) array_shift($parts));
        $params = [];
        foreach ($parts as $part) {
            $equals = strpos($part, '=');
            if ($equals === false) {
                continue;
            }
            $key = strtoupper(substr($part, 0, $equals));
            $params[$key] = trim(substr($part, $equals + 1), "\"");
        }

        return ['name' => $name, 'params' => $params, 'value' => $value];
    }

    /**
     * @param array<string,string> $params
     * @return array{DateTimeImmutable, bool}
     */
    private function parseDateValue(string $value, array $params): array
    {
        $allDay = ($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value) === 1;
        if ($allDay) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', $value, $this->timezone);

            return [$this->validDate($date, $value), true];
        }

        $isUtc = str_ends_with($value, 'Z');
        $timezone = $isUtc ? new DateTimeZone('UTC') : $this->timezoneFor($params['TZID'] ?? null);
        $format = $isUtc ? '!Ymd\THis\Z' : '!Ymd\THis';
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        $date = $this->validDate($date, $value);

        return [$isUtc ? $date->setTimezone($this->timezone) : $date, false];
    }

    private function validDate(DateTimeImmutable|false $date, string $value): DateTimeImmutable
    {
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new UnexpectedValueException("Invalid iCalendar date: {$value}");
        }

        return $date;
    }

    private function timezoneFor(?string $name): DateTimeZone
    {
        if ($name === null || $name === '') {
            return $this->timezone;
        }

        try {
            return new DateTimeZone($name);
        } catch (\Throwable $exception) {
            throw new UnexpectedValueException("Unsupported iCalendar timezone: {$name}", 0, $exception);
        }
    }

    /** @return array{RepeatRule, ?DateTimeImmutable, ?int} */
    private function parseRecurrence(string $value): array
    {
        $parts = [];
        foreach (explode(';', $value) as $part) {
            $equals = strpos($part, '=');
            if ($equals === false) {
                throw new UnexpectedValueException("Invalid recurrence rule part: {$part}");
            }
            $name = strtoupper(substr($part, 0, $equals));
            if (! in_array($name, ['FREQ', 'UNTIL', 'COUNT'], true)) {
                throw new UnexpectedValueException("Unsupported recurrence rule part: {$name}");
            }
            if (array_key_exists($name, $parts)) {
                throw new UnexpectedValueException("Duplicate recurrence rule part: {$name}");
            }
            $parts[$name] = substr($part, $equals + 1);
        }

        $repeat = RepeatRule::tryFrom(strtoupper($parts['FREQ'] ?? ''));
        if ($repeat === null || $repeat === RepeatRule::Never) {
            throw new UnexpectedValueException('Unsupported or missing recurrence frequency.');
        }
        if (isset($parts['UNTIL'], $parts['COUNT'])) {
            throw new UnexpectedValueException('A recurrence rule cannot contain both UNTIL and COUNT.');
        }
        $until = null;
        if (($parts['UNTIL'] ?? '') !== '') {
            [$until] = $this->parseDateValue($parts['UNTIL'], []);
        }
        $count = null;
        if (isset($parts['COUNT'])) {
            if (! ctype_digit($parts['COUNT']) || (int) $parts['COUNT'] < 1) {
                throw new UnexpectedValueException('Recurrence COUNT must be a positive integer.');
            }
            $count = (int) $parts['COUNT'];
        }

        return [$repeat, $until, $count];
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\r", "\n", ';', ','],
            ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    private function unescapeText(string $value): string
    {
        return preg_replace_callback(
            '/\\\\([nN,;\\\\])/',
            static fn (array $match): string => match ($match[1]) {
                'n', 'N' => "\n",
                default => $match[1],
            },
            $value,
        ) ?? $value;
    }

    private function firstListValue(string $value): string
    {
        $escaped = false;
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $char = $value[$index];
            if ($char === ',' && ! $escaped) {
                return substr($value, 0, $index);
            }
            if ($char === '\\' && ! $escaped) {
                $escaped = true;
            } else {
                $escaped = false;
            }
        }

        return $value;
    }

    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $folded[] = $chunk;
            $line = substr($line, strlen($chunk));
            $limit = 74;
        }
        $folded[] = $line;

        return implode("\r\n ", $folded);
    }
}
