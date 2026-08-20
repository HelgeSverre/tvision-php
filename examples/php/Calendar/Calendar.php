<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateTimeImmutable;

final class Calendar
{
    /** @var list<CalendarEvent> */
    private array $events;

    /** @param list<CalendarEvent> $events */
    public function __construct(array $events = [])
    {
        $this->events = $events;
    }

    /** @return list<CalendarEvent> */
    public function all(): array
    {
        return $this->events;
    }

    /** @param list<CalendarEvent> $events */
    public function replace(array $events): void
    {
        $this->events = $events;
    }

    public function upsert(CalendarEvent $event): void
    {
        foreach ($this->events as $index => $candidate) {
            if ($candidate->uid === $event->uid) {
                $this->events[$index] = $event;

                return;
            }
        }

        $this->events[] = $event;
    }

    public function delete(string $uid): bool
    {
        foreach ($this->events as $index => $event) {
            if ($event->uid !== $uid) {
                continue;
            }

            array_splice($this->events, $index, 1);

            return true;
        }

        return false;
    }

    /** @return list<CalendarEvent> */
    public function eventsOn(DateTimeImmutable $day): array
    {
        $events = array_values(array_filter(
            $this->events,
            static fn (CalendarEvent $event): bool => $event->occursOn($day),
        ));
        usort($events, static function (CalendarEvent $left, CalendarEvent $right): int {
            if ($left->allDay !== $right->allDay) {
                return $left->allDay ? -1 : 1;
            }

            return [$left->start->format('Hi'), $left->title]
                <=> [$right->start->format('Hi'), $right->title];
        });

        return $events;
    }
}
