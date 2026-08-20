<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Examples\Calendar\CalendarEvent;
use HelgeSverre\TurboVision\Examples\Calendar\EventDraft;

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
