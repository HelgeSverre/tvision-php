<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use RuntimeException;

class CalendarApp extends Application
{
    private readonly DateTimeZone $timezone;

    private readonly ICalendarStore $store;

    private Calendar $calendar;

    private ?CalendarView $calendarView = null;

    private readonly CalendarTheme $theme;

    public function __construct(
        ?Screen $screen = null,
        private readonly string $calendarPath = 'calendar.ics',
        ?DateTimeZone $timezone = null,
        private readonly ?DateTimeImmutable $today = null,
        ?CalendarTheme $theme = null,
    ) {
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $this->store = new ICalendarStore($this->timezone);
        $this->calendar = new Calendar();
        $this->theme = $theme ?? CalendarTheme::modernDark();
        parent::__construct($screen ?? new Screen(new AnsiDriver(trackMouseMotion: true)));
    }

    public function calendarView(): CalendarView
    {
        if ($this->calendarView === null) {
            throw new \LogicException('CalendarApp has not been laid out yet. Call bootForTest() first.');
        }

        return $this->calendarView;
    }

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }

    protected function layout(): void
    {
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $bounds = Rect::of(0, 0, $cols, $rows);
        $this->setBounds($bounds);
        $this->clearSubviews();
        $this->desktop = null;
        $this->menuBar = null;
        $this->statusLine = null;

        $loadMessage = '';
        $loadError = false;
        try {
            if (is_file($this->calendarPath)) {
                $events = $this->store->load($this->calendarPath);
                $loadMessage = 'Loaded ' . count($events) . ' events from ' . basename($this->calendarPath) . '.';
            } else {
                $events = $this->sampleEvents();
                $loadMessage = 'Demo calendar — save or edit to create ' . basename($this->calendarPath) . '.';
            }
        } catch (RuntimeException $exception) {
            $events = $this->sampleEvents();
            $loadMessage = $exception->getMessage();
            $loadError = true;
        }
        if (! $loadError) {
            $loadMessage .= '  •  Hover or right-click a day.';
        }
        $this->calendar->replace($events);

        $view = new CalendarView(
            bounds: $bounds,
            calendar: $this->calendar,
            store: $this->store,
            path: $this->calendarPath,
            timezone: $this->timezone,
            today: $this->today,
            theme: $this->theme,
            persistenceBlocked: $loadError,
        );
        $view->showStatus($loadMessage, $loadError);
        $this->calendarView = $view;
        $this->insert($view);
        $this->setCurrent($view);
    }

    public function reflowDesktop(): void
    {
        $bounds = Rect::of(0, 0, $this->screenObj->cols(), $this->screenObj->rows());
        $this->setBounds($bounds);
        $this->calendarView?->changeBounds($bounds);
    }

    public function dispatchForTest(Event $event): void
    {
        $this->handleEvent($event);
        $this->drawAndFlushForTest();
    }

    /** @return list<CalendarEvent> */
    private function sampleEvents(): array
    {
        $today = ($this->today ?? new DateTimeImmutable('today', $this->timezone))
            ->setTimezone($this->timezone)
            ->setTime(0, 0);

        return [
            new CalendarEvent(
                uid: 'planning@tvision-php',
                title: 'Weekly planning',
                start: $today->setTime(9, 30),
                end: $today->setTime(10, 15),
                location: 'Studio',
                notes: 'Set priorities for the week and review the project calendar.',
                calendar: 'Work',
                repeat: RepeatRule::Weekly,
            ),
            new CalendarEvent(
                uid: 'lunch@tvision-php',
                title: 'Lunch with Nora',
                start: $today->setTime(12, 30),
                end: $today->setTime(13, 30),
                location: 'Fuglen',
                calendar: 'Personal',
            ),
            new CalendarEvent(
                uid: 'focus@tvision-php',
                title: 'Deep work',
                start: $today->modify('+1 day')->setTime(10, 0),
                end: $today->modify('+1 day')->setTime(12, 0),
                notes: 'Calendar UI polish and interaction testing.',
                calendar: 'Focus',
                repeat: RepeatRule::Daily,
                recurrenceCount: 3,
            ),
            new CalendarEvent(
                uid: 'release@tvision-php',
                title: 'Calendar demo release',
                start: $today->modify('+3 days'),
                end: $today->modify('+4 days'),
                allDay: true,
                calendar: 'Work',
            ),
        ];
    }
}
