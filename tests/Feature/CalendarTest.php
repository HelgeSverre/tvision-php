<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Examples\Calendar\CalendarApp;
use HelgeSverre\TurboVision\Examples\Calendar\CalendarTheme;
use HelgeSverre\TurboVision\Examples\Calendar\ICalendarStore;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Terminal\Screen;

/** @return array{CalendarApp, HeadlessDriver, string} */
function calendarAppForTest(?CalendarTheme $theme = null): array
{
    $driver = new HeadlessDriver(120, 34);
    $path = sys_get_temp_dir() . '/tvision-calendar-feature-' . bin2hex(random_bytes(6)) . '.ics';
    $timezone = new DateTimeZone('Europe/Oslo');
    $app = new CalendarApp(
        new Screen($driver),
        $path,
        $timezone,
        new DateTimeImmutable('2026-08-20', $timezone),
        $theme,
    );
    $app->bootForTest();

    return [$app, $driver, $path];
}

test('calendar renders a polished month grid and event agenda', function (): void {
    [$app] = calendarAppForTest();
    $app->drawAndFlushForTest();
    $screen = implode("\n", $app->backRowsForTest());

    expect($screen)->toContain('August 2026')
        ->and($screen)->toContain('Thursday, August 20')
        ->and($screen)->toContain('Weekly planning')
        ->and($screen)->toContain('Lunch with Nora')
        ->and($screen)->toContain('[ Today ]')
        ->and($screen)->toContain('[Save]');
});

test('calendar uses dark semantic colors and connected border glyphs', function (): void {
    [$app] = calendarAppForTest();
    $app->drawAndFlushForTest();
    $buffer = $app->screen()?->back();
    $explicitBackgroundCells = 0;
    if ($buffer !== null) {
        for ($y = 0; $y < $buffer->height; $y++) {
            for ($x = 0; $x < $buffer->width; $x++) {
                $explicitBackgroundCells += (($buffer->at($x, $y)->attr >> 4) & 0x07) === 0 ? 0 : 1;
            }
        }
    }

    expect($buffer?->at(0, 2)->attr)->toBe(0x07)       // canvas
        ->and($buffer?->at(34, 0)->attr)->toBe(0x0F)  // month heading
        ->and($buffer?->at(36, 17)->attr)->toBe(0x0B) // selected date
        ->and($buffer?->at(1, 33)->attr)->toBe(0x0B)  // status accent
        ->and($buffer?->at(35, 17)->char)->toBe('[')  // foreground-only day marker
        ->and($buffer?->at(81, 4)->char)->toBe('›')   // foreground-only agenda marker
        ->and($explicitBackgroundCells)->toBe(0)
        ->and($buffer?->at(10, 6)->char)->toBe('┼')  // internal grid junction
        ->and($buffer?->at(80, 6)->char)->toBe('┤')  // grid meets sidebar
        ->and($buffer?->at(10, 3)->char)->toBe('│')  // ordinary vertical line
        ->and($buffer?->at(0, 6)->char)->toBe('─');  // ordinary horizontal line
});

test('calendar error status uses the destructive theme role', function (): void {
    [$app] = calendarAppForTest();
    $app->calendarView()->showStatus('Could not save', true);
    $app->drawAndFlushForTest();

    expect($app->screen()?->back()->at(1, 33)->attr)->toBe(0x0C);
});

test('calendar accepts a custom semantic theme', function (): void {
    [$app] = calendarAppForTest(new CalendarTheme(canvas: 0x0E));
    $app->drawAndFlushForTest();

    expect($app->screen()?->back()->at(0, 2)->attr)->toBe(0x0E);
});

test('keyboard and mouse navigation select dates across the month grid', function (): void {
    [$app] = calendarAppForTest();

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Right->value)));
    expect($app->calendarView()->selectedDate()->format('Y-m-d'))->toBe('2026-08-21');

    // August 25 is row 5, Tuesday in the six-week grid displayed for August 2026.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(17, 24), 1),
    ));

    expect($app->calendarView()->selectedDate()->format('Y-m-d'))->toBe('2026-08-25');
});

test('pointer motion highlights day cells without painting a background', function (): void {
    [$app] = calendarAppForTest();

    $app->dispatchForTest(Event::mouse(
        EventType::MouseMove,
        new MouseEvent(new Point(17, 24)),
    ));

    expect($app->calendarView()->hoveredDate()?->format('Y-m-d'))->toBe('2026-08-25')
        ->and(implode("\n", $app->backRowsForTest()))->toContain('‹25›');

    $app->dispatchForTest(Event::mouse(
        EventType::MouseMove,
        new MouseEvent(new Point(90, 20)),
    ));

    expect($app->calendarView()->hoveredDate())->toBeNull();
});

test('right-click opens a day context menu whose actions work with the mouse', function (): void {
    [$app] = calendarAppForTest();

    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(17, 24), 4),
    ));
    $screen = implode("\n", $app->backRowsForTest());

    expect($app->calendarView()->selectedDate()->format('Y-m-d'))->toBe('2026-08-25')
        ->and($app->calendarView()->contextMenuOpen())->toBeTrue()
        ->and($screen)->toContain('Tue, Aug 25')
        ->and($screen)->toContain('New Event')
        ->and($screen)->toContain('Show Day in Agenda');

    // The menu opens at (18,24); its first action is on row 25.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(20, 25), 1),
    ));

    expect($app->calendarView()->contextMenuOpen())->toBeFalse()
        ->and($app->calendarView()->isEditing())->toBeTrue();
});

test('new events can be edited, saved to iCalendar, and deleted', function (): void {
    [$app, $driver, $path] = calendarAppForTest();
    $view = $app->calendarView();

    try {
        $app->handleEvent(Event::keyDown(new KeyDownEvent(ord('n'), 'n')));
        expect($view->isEditing())->toBeTrue();

        foreach (mb_str_split('Demo event') as $character) {
            $app->handleEvent(Event::keyDown(new KeyDownEvent(ord($character), $character)));
        }
        $app->handleEvent(Event::keyDown(new KeyDownEvent(0x13)));

        expect($view->isEditing())->toBeFalse()
            ->and($view->calendar()->all())->toHaveCount(5)
            ->and(is_file($path))->toBeTrue();

        $stored = (new ICalendarStore(new DateTimeZone('Europe/Oslo')))->load($path);
        expect(array_map(static fn ($event): string => $event->title, $stored))->toContain('Demo event');

        $app->handleEvent(Event::keyDown(new KeyDownEvent(ord('d'), 'd')));
        $app->handleEvent(Event::keyDown(new KeyDownEvent(ord('y'), 'y')));

        expect($view->calendar()->all())->toHaveCount(4)
            ->and($view->statusMessage())->toContain('Deleted');
    } finally {
        $driver->shutdown();
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('the event editor supports clickable fields, toggles, and save controls', function (): void {
    [$app, $driver, $path] = calendarAppForTest();
    $view = $app->calendarView();

    try {
        $app->handleEvent(Event::keyDown(new KeyDownEvent(ord('n'), 'n')));
        // At 120x34 the 72x16 editor begins at (24,9); title is row 11.
        $app->handleEvent(Event::mouse(
            EventType::MouseDown,
            new MouseEvent(new Point(39, 11), 1),
        ));
        foreach (mb_str_split('Mouse event') as $character) {
            $app->handleEvent(Event::keyDown(new KeyDownEvent(ord($character), $character)));
        }
        // All-day is the sixth editor row.
        $app->handleEvent(Event::mouse(
            EventType::MouseDown,
            new MouseEvent(new Point(39, 16), 1),
        ));
        // Click the Save button.
        $app->handleEvent(Event::mouse(
            EventType::MouseDown,
            new MouseEvent(new Point(86, 22), 1),
        ));

        expect($view->isEditing())->toBeFalse()
            ->and($view->selectedEvent()?->title)->toBe('Mouse event')
            ->and($view->selectedEvent()?->allDay)->toBeTrue()
            ->and(is_file($path))->toBeTrue();
    } finally {
        $driver->shutdown();
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('date fields open an anchored picker while remaining directly editable', function (): void {
    [$app] = calendarAppForTest();
    $view = $app->calendarView();

    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(ord('n'), 'n')));
    // The start-date input is at (39,12); its picker opens immediately below it.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(39, 12), 1),
    ));
    $screen = implode("\n", $app->backRowsForTest());

    expect($view->datePickerOpen())->toBeTrue()
        ->and($screen)->toContain('Mo Tu We Th Fr Sa Su')
        ->and($screen)->toContain('•20');

    // Keyboard editing still targets the visible ISO date field while the picker is open.
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::End->value)));
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Backspace->value)));

    expect($view->datePickerOpen())->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('2026-08-2');

    // Browse to September with the popover chevron, then choose September 8.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(61, 14), 1),
    ));
    expect(implode("\n", $app->backRowsForTest()))->toContain('September 2026');

    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(45, 17), 1),
    ));

    expect($view->datePickerOpen())->toBeFalse()
        ->and($view->isEditing())->toBeTrue()
        ->and(implode("\n", $app->backRowsForTest()))->toContain('2026-09-08');

    // Escape dismisses an open picker first, preserving the surrounding editor.
    $app->dispatchForTest(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(39, 12), 1),
    ));
    $app->dispatchForTest(Event::keyDown(new KeyDownEvent(Key::Esc->value)));

    expect($view->datePickerOpen())->toBeFalse()
        ->and($view->isEditing())->toBeTrue();
});

test('calendar reflows both grid and sidebar after a terminal resize', function (): void {
    [$app, $driver] = calendarAppForTest();

    $driver->resizeTo(100, 28);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();
    $rows = $app->backRowsForTest();

    expect($app->calendarView()->getBounds()->width())->toBe(100)
        ->and($app->calendarView()->getBounds()->height())->toBe(28)
        ->and($rows)->toHaveCount(28)
        ->and(mb_strlen($rows[0]))->toBe(100)
        ->and(implode("\n", $rows))->toContain('Events')
        ->and($app->screen()?->back()->at(8, 5)->char)->toBe('┼')
        ->and($app->screen()?->back()->at(67, 5)->char)->toBe('┤');
});

test('hover and selection markers fit at the minimum supported size', function (): void {
    [$app, $driver] = calendarAppForTest();

    $driver->resizeTo(72, 20);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();

    expect(implode("\n", $app->backRowsForTest()))->toContain('[20]');
});
