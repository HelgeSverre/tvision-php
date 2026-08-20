# TurboVision Calendar

A full-screen, macOS-inspired calendar example built entirely from TurboVision PHP
views. It combines a six-week month grid with a right-hand event agenda and detail
sidebar, responsive terminal reflow, mouse hit targets, and an in-place event editor.
Its default theme uses the terminal or browser canvas, graphite structure, cool-gray
text, and foreground-only cyan selection markers. Pass a custom `CalendarTheme` to
`CalendarApp` to change the semantic CGA/ANSI attributes without rewriting the view.

Run it with:

    composer demo:calendar
    php examples/php/calendar.php path/to/calendar.ics Europe/Oslo

If the file does not exist, the app starts with sample events and creates the file on
the first edit or save. Writes use a temporary file followed by an atomic rename.

## Controls

- Arrow keys move through days; Page Up/Down moves through months.
- Home or **T** jumps to today.
- Tab switches between the month grid and event agenda.
- Enter opens the agenda or edits its selected event.
- **N**/F2 creates, **E**/F3 edits, and Delete/**D** removes an event.
- Ctrl-S/**S** saves and Ctrl-O/**O** reloads the .ics file.
- In the editor, Tab moves fields, Space toggles all-day/repeat values,
  Ctrl-S/F10 commits, and Esc cancels. Date fields remain directly editable; click
  one to open its anchored mini date picker, then choose a day or use Page Up/Down
  to browse months.
- Hovering marks the day under the pointer. Mouse clicks select dates and agenda
  items; double-click creates/edits. Right-clicking a day opens its context menu.
  The wheel changes months, moves through the focused agenda, or navigates an open
  context menu.
- **Q**, Ctrl-Q, or Alt-X quits.

## iCalendar compatibility

The reader handles RFC 5545 content-line unfolding and text escaping, VEVENT, UID,
SUMMARY, DESCRIPTION, LOCATION, CATEGORIES, DTSTART, DTEND, DURATION, TZID, UTC
timestamps, all-day dates, and daily/weekly/monthly/yearly RRULE values with COUNT or
UNTIL. Unknown provider-specific properties are ignored, so normal Apple Calendar
exports can be opened without preprocessing.

Saving emits a standards-oriented VCALENDAR with CRLF line endings, UTF-8-safe
75-octet folding, escaped text, UTC timed events, all-day dates, and recurrence data.
Complex recurrence exceptions, attendees, alarms, and provider metadata are not
rewritten by this focused demo.
