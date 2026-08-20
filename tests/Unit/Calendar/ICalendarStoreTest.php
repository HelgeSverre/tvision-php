<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Calendar\CalendarEvent;
use HelgeSverre\TurboVision\Examples\Calendar\ICalendarStore;
use HelgeSverre\TurboVision\Examples\Calendar\RepeatRule;

test('iCalendar parsing handles folded text, escapes, time zones, all-day events, and recurrence', function (): void {
    $store = new ICalendarStore(new DateTimeZone('Europe/Oslo'));
    $ics = "BEGIN:VCALENDAR\r\n"
        . "VERSION:2.0\r\n"
        . "BEGIN:VEVENT\r\n"
        . "UID:planning@example.test\r\n"
        . "DTSTART;TZID=Europe/Oslo:20260820T093000\r\n"
        . "DTEND;TZID=Europe/Oslo:20260820T103000\r\n"
        . "SUMMARY:Planning\\, review and a deliberately folded\r\n"
        . " continuation\r\n"
        . "DESCRIPTION:Line one\\nLine two\r\n"
        . "LOCATION:Studio\\; west\r\n"
        . "CATEGORIES:Work\r\n"
        . "RRULE:FREQ=WEEKLY;COUNT=3\r\n"
        . "END:VEVENT\r\n"
        . "BEGIN:VEVENT\r\n"
        . "UID:holiday@example.test\r\n"
        . "DTSTART;VALUE=DATE:20260822\r\n"
        . "DTEND;VALUE=DATE:20260824\r\n"
        . "SUMMARY:Cabin trip\r\n"
        . "END:VEVENT\r\n"
        . "END:VCALENDAR\r\n";

    $events = $store->parse($ics);

    expect($events)->toHaveCount(2)
        ->and($events[0]->title)->toBe('Planning, review and a deliberately foldedcontinuation')
        ->and($events[0]->start->format('Y-m-d H:i T'))->toBe('2026-08-20 09:30 CEST')
        ->and($events[0]->notes)->toBe("Line one\nLine two")
        ->and($events[0]->location)->toBe('Studio; west')
        ->and($events[0]->repeat)->toBe(RepeatRule::Weekly)
        ->and($events[0]->recurrenceCount)->toBe(3)
        ->and($events[1]->allDay)->toBeTrue()
        ->and($events[1]->end->format('Y-m-d'))->toBe('2026-08-24');
});

test('iCalendar serialization round-trips Unicode and escaped event data', function (): void {
    $timezone = new DateTimeZone('Europe/Oslo');
    $store = new ICalendarStore($timezone);
    $event = new CalendarEvent(
        uid: 'unicode@example.test',
        title: 'Møte, planning; and an intentionally long title that must fold without corrupting UTF-8 🚀',
        start: new DateTimeImmutable('2026-08-20 09:30', $timezone),
        end: new DateTimeImmutable('2026-08-20 10:45', $timezone),
        location: 'Studio, west',
        notes: "First line\nSecond line",
        calendar: 'Arbeid',
        repeat: RepeatRule::Monthly,
        recurrenceCount: 4,
    );

    $encoded = $store->serialize([$event]);
    $decoded = $store->parse($encoded);

    expect($encoded)->toContain("\r\n ")
        ->and($encoded)->toContain('SUMMARY:Møte\\, planning\\;')
        ->and($decoded)->toHaveCount(1)
        ->and($decoded[0]->title)->toBe($event->title)
        ->and($decoded[0]->location)->toBe($event->location)
        ->and($decoded[0]->notes)->toBe($event->notes)
        ->and($decoded[0]->start->format('Y-m-d H:i'))->toBe('2026-08-20 09:30')
        ->and($decoded[0]->repeat)->toBe(RepeatRule::Monthly);
});

test('iCalendar store saves atomically and loads the resulting file', function (): void {
    $timezone = new DateTimeZone('UTC');
    $store = new ICalendarStore($timezone);
    $path = sys_get_temp_dir() . '/tvision-calendar-' . bin2hex(random_bytes(6)) . '.ics';
    $event = new CalendarEvent(
        'saved@example.test',
        'Saved event',
        new DateTimeImmutable('2026-08-20 14:00', $timezone),
        new DateTimeImmutable('2026-08-20 15:00', $timezone),
    );

    try {
        $store->save($path, [$event]);
        $loaded = $store->load($path);

        expect($loaded)->toHaveCount(1)
            ->and($loaded[0]->uid)->toBe('saved@example.test')
            ->and(file_get_contents($path))->toContain('BEGIN:VCALENDAR');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('iCalendar parsing fails closed for malformed calendars and events', function (): void {
    $store = new ICalendarStore(new DateTimeZone('UTC'));

    $cases = [
        ['garbage', 'envelope'],
        [
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:bad\r\nDTSTART:nope\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'Invalid VEVENT #1',
        ],
        [
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:bad\r\nDTSTART:20260820T090000Z\r\nEND:VCALENDAR\r\n",
            'Unterminated VEVENT',
        ],
        [
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:20260820T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'missing UID',
        ],
    ];

    foreach ($cases as [$ics, $message]) {
        expect(fn (): array => $store->parse($ics))
            ->toThrow(UnexpectedValueException::class, $message);
    }
});

test('iCalendar parsing rejects recurrence semantics the demo cannot preserve', function (): void {
    $store = new ICalendarStore(new DateTimeZone('UTC'));
    $ics = "BEGIN:VCALENDAR\r\n"
        . "BEGIN:VEVENT\r\n"
        . "UID:complex-series\r\n"
        . "DTSTART:20260820T090000Z\r\n"
        . "RRULE:FREQ=WEEKLY;BYDAY=MO,WE\r\n"
        . "END:VEVENT\r\n"
        . "END:VCALENDAR\r\n";

    expect(fn (): array => $store->parse($ics))
        ->toThrow(UnexpectedValueException::class, 'Unsupported recurrence rule part: BYDAY');
});

test('nested alarm properties do not overwrite event properties', function (): void {
    $store = new ICalendarStore(new DateTimeZone('UTC'));
    $ics = "BEGIN:VCALENDAR\r\n"
        . "BEGIN:VEVENT\r\n"
        . "UID:with-alarm\r\n"
        . "DTSTART:20260820T090000Z\r\n"
        . "DESCRIPTION:Event notes\r\n"
        . "BEGIN:VALARM\r\n"
        . "DESCRIPTION:Alarm notes\r\n"
        . "END:VALARM\r\n"
        . "END:VEVENT\r\n"
        . "END:VCALENDAR\r\n";

    $events = $store->parse($ics);

    expect($events)->toHaveCount(1)
        ->and($events[0]->notes)->toBe('Event notes');
});

test('iCalendar parsing validates component structure and unique event properties', function (): void {
    $store = new ICalendarStore(new DateTimeZone('UTC'));
    $nestedEvent = "BEGIN:VCALENDAR\r\n"
        . "BEGIN:VTIMEZONE\r\n"
        . "BEGIN:VEVENT\r\nUID:nested\r\nDTSTART:20260820T090000Z\r\nEND:VEVENT\r\n"
        . "END:VTIMEZONE\r\nEND:VCALENDAR\r\n";
    $duplicateStart = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:duplicate\r\n"
        . "DTSTART:20260820T090000Z\r\nDTSTART:20260821T090000Z\r\n"
        . "END:VEVENT\r\nEND:VCALENDAR\r\n";

    expect(fn (): array => $store->parse($nestedEvent))
        ->toThrow(UnexpectedValueException::class, 'direct child')
        ->and(fn (): array => $store->parse($duplicateStart))
        ->toThrow(UnexpectedValueException::class, 'Duplicate VEVENT property: DTSTART');
});
