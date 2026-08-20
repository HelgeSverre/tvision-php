<?php

declare(strict_types=1);

/*
 * TurboVision Calendar — macOS-inspired month calendar and RFC 5545 .ics editor.
 *
 * Usage:
 *   php examples/php/calendar.php [path/to/calendar.ics] [timezone]
 *
 * Keys:
 *   Arrows / PgUp / PgDn   Navigate days and months
 *   Tab                    Switch between month grid and event agenda
 *   N / F2                 New event
 *   E / F3 / Enter         Edit selected event
 *   Delete / D             Delete selected event
 *   Ctrl-S / S             Save the .ics file
 *   Ctrl-O / O             Reload the .ics file
 *   T / Home               Jump to today
 *   Q / Alt-X / Ctrl-Q     Quit
 */

use HelgeSverre\TurboVision\Examples\Calendar\CalendarApp;

require_once __DIR__ . '/../../vendor/autoload.php';

final class CalendarDemoApp extends CalendarApp {}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    $path = $argv[1] ?? getcwd() . '/calendar.ics';
    $timezone = isset($argv[2]) ? new DateTimeZone($argv[2]) : null;
    exit((new CalendarDemoApp(calendarPath: $path, timezone: $timezone))->run());
}
