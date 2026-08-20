<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Calendar\CalendarEvent;
use HelgeSverre\TurboVision\Examples\Calendar\EventDraft;
use HelgeSverre\TurboVision\Examples\Calendar\EventField;
use HelgeSverre\TurboVision\Examples\Calendar\RepeatRule;

test('event drafts support timed events spanning multiple dates', function (): void {
    $timezone = new DateTimeZone('Europe/Oslo');
    $draft = EventDraft::create(new DateTimeImmutable('2026-08-20', $timezone));
    $draft->title = 'Overnight train';
    $draft->startTime = '22:30';
    $draft->endDate = '2026-08-21';
    $draft->endTime = '07:15';

    $event = $draft->toEvent($timezone);

    expect($event->start->format('Y-m-d H:i'))->toBe('2026-08-20 22:30')
        ->and($event->end->format('Y-m-d H:i'))->toBe('2026-08-21 07:15')
        ->and($event->allDay)->toBeFalse();
});

test('all-day draft end dates are inclusive for editing and exclusive in iCalendar data', function (): void {
    $timezone = new DateTimeZone('UTC');
    $draft = EventDraft::create(new DateTimeImmutable('2026-08-20', $timezone));
    $draft->title = 'Conference';
    $draft->allDay = true;
    $draft->endDate = '2026-08-22';

    $event = $draft->toEvent($timezone);
    $roundTrip = EventDraft::fromEvent($event);

    expect($event->start->format('Y-m-d'))->toBe('2026-08-20')
        ->and($event->end->format('Y-m-d'))->toBe('2026-08-23')
        ->and($roundTrip->endDate)->toBe('2026-08-22');
});

test('draft validation rejects events ending before they start', function (): void {
    $timezone = new DateTimeZone('UTC');
    $draft = EventDraft::create(new DateTimeImmutable('2026-08-20', $timezone));
    $draft->title = 'Impossible';
    $draft->endTime = '08:00';

    expect(fn (): CalendarEvent => $draft->toEvent($timezone))
        ->toThrow(InvalidArgumentException::class, 'end time');
});

test('editing an event preserves recurrence limits and untouched multiline notes', function (): void {
    $timezone = new DateTimeZone('UTC');
    $until = new DateTimeImmutable('2026-09-30 23:59', $timezone);
    $event = new CalendarEvent(
        uid: 'finite-series',
        title: 'Planning',
        start: new DateTimeImmutable('2026-08-20 09:00', $timezone),
        end: new DateTimeImmutable('2026-08-20 10:00', $timezone),
        notes: "First line\nSecond line",
        repeat: RepeatRule::Weekly,
        recurrenceUntil: $until,
    );
    $draft = EventDraft::fromEvent($event);
    $draft->title = 'Updated planning';

    $updated = $draft->toEvent($timezone);

    expect($draft->value(EventField::Notes))->toBe('First line Second line')
        ->and($updated->notes)->toBe($event->notes)
        ->and($updated->repeat)->toBe(RepeatRule::Weekly)
        ->and($updated->recurrenceUntil)->toEqual($until);
});

test('editing metadata preserves second precision in event times', function (): void {
    $timezone = new DateTimeZone('UTC');
    $event = new CalendarEvent(
        uid: 'second-precision',
        title: 'Precise event',
        start: new DateTimeImmutable('2026-08-20 09:00:45', $timezone),
        end: new DateTimeImmutable('2026-08-20 10:15:30', $timezone),
    );
    $draft = EventDraft::fromEvent($event);
    $draft->title = 'Updated precise event';

    $updated = $draft->toEvent($timezone);

    expect($draft->startTime)->toBe('09:00:45')
        ->and($draft->endTime)->toBe('10:15:30')
        ->and($updated->start->format('H:i:s'))->toBe('09:00:45')
        ->and($updated->end->format('H:i:s'))->toBe('10:15:30');
});
