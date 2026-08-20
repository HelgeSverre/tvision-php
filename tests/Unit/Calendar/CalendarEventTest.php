<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Calendar\Calendar;
use HelgeSverre\TurboVision\Examples\Calendar\CalendarEvent;
use HelgeSverre\TurboVision\Examples\Calendar\RepeatRule;

test('one-off and all-day events occur on every day they overlap', function (): void {
    $timezone = new DateTimeZone('Europe/Oslo');
    $timed = new CalendarEvent(
        'timed',
        'Late deployment',
        new DateTimeImmutable('2026-08-20 23:30', $timezone),
        new DateTimeImmutable('2026-08-21 00:30', $timezone),
    );
    $allDay = new CalendarEvent(
        'trip',
        'Trip',
        new DateTimeImmutable('2026-08-22', $timezone),
        new DateTimeImmutable('2026-08-24', $timezone),
        true,
    );

    expect($timed->occursOn(new DateTimeImmutable('2026-08-20', $timezone)))->toBeTrue()
        ->and($timed->occursOn(new DateTimeImmutable('2026-08-21', $timezone)))->toBeTrue()
        ->and($timed->occursOn(new DateTimeImmutable('2026-08-22', $timezone)))->toBeFalse()
        ->and($allDay->occursOn(new DateTimeImmutable('2026-08-22', $timezone)))->toBeTrue()
        ->and($allDay->occursOn(new DateTimeImmutable('2026-08-23', $timezone)))->toBeTrue()
        ->and($allDay->occursOn(new DateTimeImmutable('2026-08-24', $timezone)))->toBeFalse();
});

test('weekly recurrence respects its occurrence count', function (): void {
    $timezone = new DateTimeZone('UTC');
    $event = new CalendarEvent(
        uid: 'weekly',
        title: 'Planning',
        start: new DateTimeImmutable('2026-08-03 09:00', $timezone),
        end: new DateTimeImmutable('2026-08-03 10:00', $timezone),
        repeat: RepeatRule::Weekly,
        recurrenceCount: 3,
    );

    expect($event->occursOn(new DateTimeImmutable('2026-08-03', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-10', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-17', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-24', $timezone)))->toBeFalse()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-11', $timezone)))->toBeFalse();
});

test('recurring events occur on every day an instance overlaps', function (): void {
    $timezone = new DateTimeZone('UTC');
    $event = new CalendarEvent(
        uid: 'overnight-weekly',
        title: 'Maintenance window',
        start: new DateTimeImmutable('2026-08-03 23:00', $timezone),
        end: new DateTimeImmutable('2026-08-05 01:00', $timezone),
        repeat: RepeatRule::Weekly,
        recurrenceCount: 2,
    );

    expect($event->occursOn(new DateTimeImmutable('2026-08-03', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-04', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-05', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-12', $timezone)))->toBeTrue()
        ->and($event->occursOn(new DateTimeImmutable('2026-08-13', $timezone)))->toBeFalse();
});

test('monthly and yearly counts skip dates that do not exist', function (): void {
    $timezone = new DateTimeZone('UTC');
    $monthly = new CalendarEvent(
        uid: 'month-end',
        title: 'Month end',
        start: new DateTimeImmutable('2026-01-31 09:00', $timezone),
        end: new DateTimeImmutable('2026-01-31 10:00', $timezone),
        repeat: RepeatRule::Monthly,
        recurrenceCount: 2,
    );
    $yearly = new CalendarEvent(
        uid: 'leap-day',
        title: 'Leap day',
        start: new DateTimeImmutable('2024-02-29 09:00', $timezone),
        end: new DateTimeImmutable('2024-02-29 10:00', $timezone),
        repeat: RepeatRule::Yearly,
        recurrenceCount: 2,
    );

    expect($monthly->occursOn(new DateTimeImmutable('2026-02-28', $timezone)))->toBeFalse()
        ->and($monthly->occursOn(new DateTimeImmutable('2026-03-31', $timezone)))->toBeTrue()
        ->and($monthly->occursOn(new DateTimeImmutable('2026-05-31', $timezone)))->toBeFalse()
        ->and($yearly->occursOn(new DateTimeImmutable('2028-02-29', $timezone)))->toBeTrue()
        ->and($yearly->occursOn(new DateTimeImmutable('2032-02-29', $timezone)))->toBeFalse();
});

test('calendar upserts, sorts all-day events first, and deletes by UID', function (): void {
    $timezone = new DateTimeZone('UTC');
    $timed = new CalendarEvent(
        'same',
        'Original',
        new DateTimeImmutable('2026-08-20 09:00', $timezone),
        new DateTimeImmutable('2026-08-20 10:00', $timezone),
    );
    $allDay = new CalendarEvent(
        'all-day',
        'Deadline',
        new DateTimeImmutable('2026-08-20', $timezone),
        new DateTimeImmutable('2026-08-21', $timezone),
        true,
    );
    $calendar = new Calendar([$timed, $allDay]);
    $replacement = new CalendarEvent(
        'same',
        'Updated',
        new DateTimeImmutable('2026-08-20 08:00', $timezone),
        new DateTimeImmutable('2026-08-20 09:00', $timezone),
    );

    $calendar->upsert($replacement);
    $events = $calendar->eventsOn(new DateTimeImmutable('2026-08-20', $timezone));

    expect($calendar->all())->toHaveCount(2)
        ->and($events[0])->toBe($allDay)
        ->and($events[1]->title)->toBe('Updated')
        ->and($calendar->delete('same'))->toBeTrue()
        ->and($calendar->delete('missing'))->toBeFalse()
        ->and($calendar->all())->toHaveCount(1);
});

test('event construction rejects invalid time and recurrence ranges', function (): void {
    $timezone = new DateTimeZone('UTC');

    expect(fn (): CalendarEvent => new CalendarEvent(
        'invalid-time',
        'Invalid',
        new DateTimeImmutable('2026-08-20 10:00', $timezone),
        new DateTimeImmutable('2026-08-20 09:00', $timezone),
    ))->toThrow(InvalidArgumentException::class, 'end must be after')
        ->and(fn (): CalendarEvent => new CalendarEvent(
            uid: 'invalid-count',
            title: 'Invalid',
            start: new DateTimeImmutable('2026-08-20 09:00', $timezone),
            end: new DateTimeImmutable('2026-08-20 10:00', $timezone),
            repeat: RepeatRule::Daily,
            recurrenceCount: 0,
        ))->toThrow(InvalidArgumentException::class, 'positive integer')
        ->and(fn (): CalendarEvent => new CalendarEvent(
            uid: 'invalid-all-day',
            title: 'Invalid',
            start: new DateTimeImmutable('2026-08-20 09:00', $timezone),
            end: new DateTimeImmutable('2026-08-21 09:00', $timezone),
            allDay: true,
        ))->toThrow(InvalidArgumentException::class, 'midnight');
});
