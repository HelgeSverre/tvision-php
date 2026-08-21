<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\Glyphs;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * A complete month calendar with a macOS-inspired toolbar, agenda sidebar, event
 * details, cell hover, a right-click day menu, mouse navigation, and an in-place
 * editor sheet.
 */
final class CalendarView extends View
{
    private DateTimeImmutable $today;

    private DateTimeImmutable $selectedDate;

    private CalendarFocus $focus = CalendarFocus::Grid;

    private int $agendaIndex = 0;

    private ?EventDraft $draft = null;

    private bool $editingExisting = false;

    private int $editorField = 0;

    private int $editorCursor = 0;

    private ?EventField $datePickerField = null;

    private ?DateTimeImmutable $datePickerMonth = null;

    private bool $confirmDelete = false;

    private string $status = '';

    private bool $statusIsError = false;

    private readonly CalendarTheme $theme;

    private ?DateTimeImmutable $hoveredDate = null;

    private ?DateTimeImmutable $contextDate = null;

    private ?Point $contextOrigin = null;

    private int $contextIndex = 0;

    public function __construct(
        Rect $bounds,
        private readonly Calendar $calendar,
        private readonly ICalendarStore $store,
        private readonly string $path,
        private readonly DateTimeZone $timezone,
        ?DateTimeImmutable $today = null,
        ?CalendarTheme $theme = null,
        private bool $persistenceBlocked = false,
    ) {
        parent::__construct($bounds);
        $this->eventMask |= EventMask::Mouse;
        $this->theme = $theme ?? CalendarTheme::modernDark();
        $this->options |= State::Selectable | State::FirstClick;
        $this->today = ($today ?? new DateTimeImmutable('today', $timezone))
            ->setTimezone($timezone)
            ->setTime(0, 0);
        $this->selectedDate = $this->today;
        $this->status = 'Tab agenda  •  N new  •  E edit  •  Del delete  •  Ctrl-S save  •  Q quit';
    }

    public function calendar(): Calendar
    {
        return $this->calendar;
    }

    public function selectedDate(): DateTimeImmutable
    {
        return $this->selectedDate;
    }

    public function selectedEvent(): ?CalendarEvent
    {
        $events = $this->eventsForSelectedDate();

        return $events[$this->agendaIndex] ?? null;
    }

    public function isEditing(): bool
    {
        return $this->draft !== null;
    }

    public function statusMessage(): string
    {
        return $this->status;
    }

    public function hoveredDate(): ?DateTimeImmutable
    {
        return $this->hoveredDate;
    }

    public function contextMenuOpen(): bool
    {
        return $this->contextDate !== null;
    }

    public function datePickerOpen(): bool
    {
        return $this->datePickerField !== null;
    }

    public function showStatus(string $message, bool $error = false): void
    {
        $this->setStatus($message, $error);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $this->fillRect(0, 0, $width, $height, ' ', $this->theme->canvas);
        if ($width < 72 || $height < 20) {
            $this->drawCompactWarning($width, $height);

            return;
        }

        $mainWidth = $this->mainWidth();
        $this->drawToolbar($mainWidth, $width);
        $this->drawMonthGrid($mainWidth, $height);
        $this->drawAgenda($mainWidth, $width, $height);
        $this->drawStatus($width, $height);

        if ($this->draft !== null) {
            $this->drawEditor();
        } elseif ($this->confirmDelete) {
            $this->drawDeleteConfirmation();
        } elseif ($this->contextDate !== null) {
            $this->drawContextMenu();
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($this->draft !== null) {
            if ($event->what === EventType::KeyDown) {
                $this->handleEditorKey($event);
            } elseif ($event->what === EventType::MouseDown) {
                $this->handleEditorMouse($event);
            }

            return;
        }
        if ($this->confirmDelete) {
            if ($event->what === EventType::KeyDown) {
                $this->handleDeleteConfirmation($event);
            } elseif ($event->what === EventType::MouseDown) {
                $this->handleDeleteMouse($event);
            }

            return;
        }
        if ($this->contextDate !== null) {
            $this->handleContextMenuEvent($event);

            return;
        }

        if ($event->what === EventType::KeyDown) {
            $this->handleKey($event);

            return;
        }
        if ($event->what === EventType::MouseDown || $event->what === EventType::MouseMove) {
            $this->handleMouse($event);
        }
    }

    private function handleKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        if ($key->is(Key::AltX) || $key->keyCode === 0x11 || strtolower($key->char) === 'q') {
            if ($this->owner instanceof Group) {
                $this->owner->putEvent(Event::command(Cmd::Quit));
            }
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::Tab) || $key->is(Key::ShiftTab)) {
            $this->focus = $this->focus === CalendarFocus::Grid
                ? CalendarFocus::Agenda
                : CalendarFocus::Grid;
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::PageUp)) {
            $this->changeMonth(-1);
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::PageDown)) {
            $this->changeMonth(1);
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::Home) || strtolower($key->char) === 't') {
            $this->selectDate($this->today);
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::F2) || $key->keyCode === 0x0E || strtolower($key->char) === 'n') {
            $this->beginNewEvent();
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::F3) || strtolower($key->char) === 'e') {
            $this->beginEditEvent();
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::Delete) || strtolower($key->char) === 'd') {
            $this->requestDelete();
            $this->clearEvent($event);

            return;
        }
        if ($key->keyCode === 0x13 || strtolower($key->char) === 's') {
            $this->persist('Saved');
            $this->clearEvent($event);

            return;
        }
        if ($key->keyCode === 0x0F || strtolower($key->char) === 'o') {
            $this->reload();
            $this->clearEvent($event);

            return;
        }

        if ($this->focus === CalendarFocus::Grid) {
            $this->handleGridKey($key);
        } else {
            $this->handleAgendaKey($key);
        }
        $this->clearEvent($event);
    }

    private function handleGridKey(KeyDownEvent $key): void
    {
        if ($key->is(Key::Left)) {
            $this->moveDate(-1);
        } elseif ($key->is(Key::Right)) {
            $this->moveDate(1);
        } elseif ($key->is(Key::Up)) {
            $this->moveDate(-7);
        } elseif ($key->is(Key::Down)) {
            $this->moveDate(7);
        } elseif ($key->is(Key::Enter)) {
            if ($this->eventsForSelectedDate() === []) {
                $this->beginNewEvent();
            } else {
                $this->focus = CalendarFocus::Agenda;
            }
        }
    }

    private function handleAgendaKey(KeyDownEvent $key): void
    {
        $events = $this->eventsForSelectedDate();
        if ($key->is(Key::Left) || $key->is(Key::Esc)) {
            $this->focus = CalendarFocus::Grid;
        } elseif ($key->is(Key::Up)) {
            $this->agendaIndex = max(0, $this->agendaIndex - 1);
        } elseif ($key->is(Key::Down)) {
            $this->agendaIndex = min(max(0, count($events) - 1), $this->agendaIndex + 1);
        } elseif ($key->is(Key::Enter)) {
            $this->beginEditEvent();
        }
    }

    private function handleMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }

        if ($event->what === EventType::MouseMove && $mouse->wheel !== 0) {
            if ($this->focus === CalendarFocus::Agenda) {
                $this->moveAgenda($mouse->wheel > 0 ? 1 : -1);
            } else {
                $this->changeMonth($mouse->wheel > 0 ? 1 : -1);
            }
            $this->clearEvent($event);

            return;
        }

        $local = $this->makeLocal($mouse->where);
        $mainWidth = $this->mainWidth();
        if ($event->what === EventType::MouseMove) {
            $this->updateHoveredDate($local, $mainWidth);
            $this->clearEvent($event);

            return;
        }
        if ($event->what !== EventType::MouseDown) {
            return;
        }

        if (($mouse->buttons & 4) !== 0) {
            if ($local->x < $mainWidth && $local->y >= 2 && $local->y < $this->bounds->height() - 1) {
                $date = $this->dateAtGridPoint($local->x, $local->y, $mainWidth);
                if ($date !== null) {
                    $this->openContextMenu($date, $local);
                }
            }
            $this->clearEvent($event);

            return;
        }
        if (($mouse->buttons & 1) === 0) {
            return;
        }

        if ($local->y === 0) {
            $this->activateToolbar($local->x, $mainWidth);
            $this->clearEvent($event);

            return;
        }
        if ($local->x < $mainWidth && $local->y >= 2 && $local->y < $this->bounds->height() - 1) {
            $date = $this->dateAtGridPoint($local->x, $local->y, $mainWidth);
            if ($date !== null) {
                $this->selectDate($date);
                $this->focus = CalendarFocus::Grid;
                if ($mouse->doubleClick) {
                    $this->beginNewEvent();
                }
            }
            $this->clearEvent($event);

            return;
        }
        if ($local->x > $mainWidth && $local->y >= 4) {
            $index = intdiv($local->y - 4, 2);
            $events = $this->eventsForSelectedDate();
            if (isset($events[$index])) {
                $this->agendaIndex = $index;
                $this->focus = CalendarFocus::Agenda;
                if ($mouse->doubleClick) {
                    $this->beginEditEvent();
                }
            }
            $this->clearEvent($event);
        }
    }

    private function updateHoveredDate(Point $local, int $mainWidth): void
    {
        $hoveredDate = null;
        if ($local->x < $mainWidth && $local->y >= 2 && $local->y < $this->bounds->height() - 1) {
            $hoveredDate = $this->dateAtGridPoint($local->x, $local->y, $mainWidth);
        }
        if ($this->hoveredDate?->format('Y-m-d') === $hoveredDate?->format('Y-m-d')) {
            return;
        }

        $this->hoveredDate = $hoveredDate;
    }

    private function openContextMenu(DateTimeImmutable $date, Point $origin): void
    {
        $this->selectDate($date);
        $this->focus = CalendarFocus::Grid;
        $this->hoveredDate = $this->selectedDate;
        $this->contextDate = $this->selectedDate;
        $this->contextOrigin = $origin;
        $this->contextIndex = 0;
    }

    private function closeContextMenu(): void
    {
        $this->contextDate = null;
        $this->contextOrigin = null;
        $this->contextIndex = 0;
    }

    private function handleContextMenuEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $key = $event->asKey();
            if ($key === null) {
                return;
            }
            if ($key->is(Key::Esc)) {
                $this->closeContextMenu();
            } elseif ($key->is(Key::Up)) {
                $this->moveContextSelection(-1);
            } elseif ($key->is(Key::Down)) {
                $this->moveContextSelection(1);
            } elseif ($key->is(Key::Enter)) {
                $this->activateContextAction($this->contextActions()[$this->contextIndex]);
            } else {
                $action = match (strtolower($key->char)) {
                    'n' => CalendarContextAction::NewEvent,
                    'e' => CalendarContextAction::EditFirstEvent,
                    'a' => CalendarContextAction::ShowAgenda,
                    't' => CalendarContextAction::GoToToday,
                    default => null,
                };
                if ($action !== null && $this->contextActionEnabled($action)) {
                    $this->activateContextAction($action);
                }
            }
            $this->clearEvent($event);

            return;
        }
        if ($event->what !== EventType::MouseDown && $event->what !== EventType::MouseMove) {
            return;
        }

        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        if ($event->what === EventType::MouseMove && $mouse->wheel !== 0) {
            $this->moveContextSelection($mouse->wheel > 0 ? 1 : -1);
            $this->clearEvent($event);

            return;
        }

        $local = $this->makeLocal($mouse->where);
        $actionIndex = $this->contextActionIndexAt($local);
        if ($event->what === EventType::MouseMove) {
            if ($actionIndex !== null && $this->contextActionEnabled($this->contextActions()[$actionIndex])) {
                $this->contextIndex = $actionIndex;
            }
            $this->clearEvent($event);

            return;
        }

        if (($mouse->buttons & 4) !== 0) {
            $this->closeContextMenu();
            $this->handleMouse($event);

            return;
        }
        if (($mouse->buttons & 1) !== 0 && $actionIndex !== null) {
            $action = $this->contextActions()[$actionIndex];
            if ($this->contextActionEnabled($action)) {
                $this->activateContextAction($action);
            }
        } else {
            $this->closeContextMenu();
        }
        $this->clearEvent($event);
    }

    /** @return list<CalendarContextAction> */
    private function contextActions(): array
    {
        return CalendarContextAction::cases();
    }

    private function contextActionEnabled(CalendarContextAction $action): bool
    {
        $events = $this->contextDate === null ? [] : $this->calendar->eventsOn($this->contextDate);

        return match ($action) {
            CalendarContextAction::EditFirstEvent, CalendarContextAction::ShowAgenda => $events !== [],
            default => true,
        };
    }

    private function moveContextSelection(int $delta): void
    {
        $actions = $this->contextActions();
        $count = count($actions);
        for ($step = 0; $step < $count; $step++) {
            $this->contextIndex = ($this->contextIndex + $delta + $count) % $count;
            if ($this->contextActionEnabled($actions[$this->contextIndex])) {
                return;
            }
        }
    }

    private function activateContextAction(CalendarContextAction $action): void
    {
        $date = $this->contextDate;
        if ($date === null || ! $this->contextActionEnabled($action)) {
            return;
        }

        $this->closeContextMenu();
        $this->selectDate($date);
        switch ($action) {
            case CalendarContextAction::NewEvent:
                $this->beginNewEvent();
                break;
            case CalendarContextAction::EditFirstEvent:
                $this->beginEditEvent();
                break;
            case CalendarContextAction::ShowAgenda:
                $this->focus = CalendarFocus::Agenda;
                break;
            case CalendarContextAction::GoToToday:
                $this->selectDate($this->today);
                break;
        }
    }

    private function contextActionIndexAt(Point $local): ?int
    {
        [$x, $y, $width] = $this->contextMenuGeometry();
        if ($local->x <= $x || $local->x >= $x + $width - 1) {
            return null;
        }
        $index = $local->y - $y - 1;

        return isset($this->contextActions()[$index]) ? $index : null;
    }

    private function activateToolbar(int $x, int $mainWidth): void
    {
        if ($x >= 1 && $x <= 3) {
            $this->changeMonth(-1);
        } elseif ($x >= 4 && $x <= 6) {
            $this->changeMonth(1);
        } elseif ($x >= 8 && $x <= 16) {
            $this->selectDate($this->today);
        } elseif ($mainWidth >= 70) {
            if ($x >= $mainWidth - 23 && $x < $mainWidth - 18) {
                $this->beginNewEvent();
            } elseif ($x >= $mainWidth - 16 && $x < $mainWidth - 8) {
                $this->reload();
            } elseif ($x >= $mainWidth - 7 && $x < $mainWidth) {
                $this->persist('Saved');
            }
        } elseif ($x >= $mainWidth - 11 && $x < $mainWidth - 8) {
            $this->beginNewEvent();
        } elseif ($x >= $mainWidth - 7 && $x < $mainWidth - 4) {
            $this->reload();
        } elseif ($x >= $mainWidth - 3 && $x < $mainWidth) {
            $this->persist('Saved');
        }
    }

    private function moveDate(int $days): void
    {
        $this->selectDate($this->selectedDate->modify(($days >= 0 ? '+' : '') . $days . ' days'));
    }

    private function changeMonth(int $months): void
    {
        $targetMonth = $this->selectedDate
            ->modify('first day of this month')
            ->modify(($months >= 0 ? '+' : '') . $months . ' months');
        $day = min((int) $this->selectedDate->format('j'), (int) $targetMonth->format('t'));
        $this->selectDate($targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('n'),
            $day,
        ));
    }

    private function selectDate(DateTimeImmutable $date): void
    {
        $this->selectedDate = $date->setTimezone($this->timezone)->setTime(0, 0);
        $this->agendaIndex = 0;
        $this->confirmDelete = false;
    }

    private function moveAgenda(int $direction): void
    {
        $count = count($this->eventsForSelectedDate());
        $this->agendaIndex = max(0, min(max(0, $count - 1), $this->agendaIndex + $direction));
    }

    private function beginNewEvent(): void
    {
        $this->draft = EventDraft::create($this->selectedDate);
        $this->editingExisting = false;
        $this->editorField = 0;
        $this->editorCursor = 0;
        $this->closeDatePicker();
        $this->confirmDelete = false;
        $this->setStatus('New event — Tab moves between fields, Ctrl-S saves, Esc cancels.');
    }

    private function beginEditEvent(): void
    {
        $event = $this->selectedEvent();
        if ($event === null) {
            $this->setStatus('There is no event selected to edit.', true);

            return;
        }

        $this->draft = EventDraft::fromEvent($event);
        $this->editingExisting = true;
        $this->editorField = 0;
        $this->editorCursor = mb_strlen($this->draft->title);
        $this->closeDatePicker();
        $this->confirmDelete = false;
        $this->setStatus('Editing event — changes apply to the complete recurring series.');
    }

    private function requestDelete(): void
    {
        if ($this->selectedEvent() === null) {
            $this->setStatus('There is no event selected to delete.', true);

            return;
        }
        $this->confirmDelete = true;
        $this->setStatus('Delete this event? Press Y to delete or N/Esc to cancel.', true);
    }

    private function handleDeleteConfirmation(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        if (strtolower($key->char) === 'y') {
            $this->deleteSelectedEvent();
        } elseif (strtolower($key->char) === 'n' || $key->is(Key::Esc)) {
            $this->cancelDelete();
        }
        $this->clearEvent($event);
    }

    private function handleDeleteMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return;
        }
        $local = $this->makeLocal($mouse->where);
        [$x, $y, $width] = $this->deleteBoxGeometry();
        $buttonY = $y + 4;
        if ($local->y === $buttonY && $local->x >= $x + 3 && $local->x < $x + 15) {
            $this->deleteSelectedEvent();
        } elseif ($local->y === $buttonY
            && $local->x >= $x + 20
            && $local->x < min($x + $width - 2, $x + 34)
        ) {
            $this->cancelDelete();
        }
        $this->clearEvent($event);
    }

    private function deleteSelectedEvent(): void
    {
        if (! $this->persistenceAvailable()) {
            $this->confirmDelete = false;

            return;
        }

        $selected = $this->selectedEvent();
        if ($selected !== null) {
            $before = $this->calendar->all();
            $this->calendar->delete($selected->uid);
            try {
                $this->store->save($this->path, $this->calendar->all());
                $this->agendaIndex = max(0, $this->agendaIndex - 1);
                $this->setStatus('Deleted “' . $selected->title . '” and saved the calendar.');
            } catch (RuntimeException $exception) {
                $this->calendar->replace($before);
                $this->setStatus($exception->getMessage(), true);
            }
        }
        $this->confirmDelete = false;
    }

    private function cancelDelete(): void
    {
        $this->confirmDelete = false;
        $this->setStatus('Delete cancelled.');
    }

    private function handleEditorKey(Event $event): void
    {
        $key = $event->asKey();
        $draft = $this->draft;
        if ($key === null || $draft === null) {
            return;
        }

        if ($key->is(Key::Esc)) {
            if ($this->datePickerField !== null) {
                $this->closeDatePicker();
                $this->clearEvent($event);

                return;
            }
            $this->draft = null;
            $this->closeDatePicker();
            $this->setStatus('Changes cancelled.');
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::F10) || $key->keyCode === 0x13) {
            $this->saveDraft();
            $this->clearEvent($event);

            return;
        }
        if ($this->datePickerField !== null && ($key->is(Key::PageUp) || $key->is(Key::PageDown))) {
            $this->changeDatePickerMonth($key->is(Key::PageUp) ? -1 : 1);
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::Tab) || $key->is(Key::Down) || $key->is(Key::Enter)) {
            $this->advanceEditorField(1);
            $this->clearEvent($event);

            return;
        }
        if ($key->is(Key::ShiftTab) || $key->is(Key::Up)) {
            $this->advanceEditorField(-1);
            $this->clearEvent($event);

            return;
        }

        $field = $this->editorFields()[$this->editorField];
        if (! $field->acceptsText()) {
            if ($field === EventField::AllDay
                && ($key->char === ' ' || $key->is(Key::Left) || $key->is(Key::Right))
            ) {
                $draft->allDay = ! $draft->allDay;
            } elseif ($field === EventField::Repeat
                && ($key->char === ' ' || $key->is(Key::Left) || $key->is(Key::Right))
            ) {
                $draft->repeat = $draft->repeat->next($key->is(Key::Left) ? -1 : 1);
            }
            $this->clearEvent($event);

            return;
        }

        $value = $draft->value($field);
        $length = mb_strlen($value);
        if ($key->is(Key::Left)) {
            $this->editorCursor = max(0, $this->editorCursor - 1);
        } elseif ($key->is(Key::Right)) {
            $this->editorCursor = min($length, $this->editorCursor + 1);
        } elseif ($key->is(Key::Home)) {
            $this->editorCursor = 0;
        } elseif ($key->is(Key::End)) {
            $this->editorCursor = $length;
        } elseif ($key->is(Key::Backspace) && $this->editorCursor > 0) {
            $value = mb_substr($value, 0, $this->editorCursor - 1)
                . mb_substr($value, $this->editorCursor);
            $this->editorCursor--;
            $draft->setValue($field, $value);
        } elseif ($key->is(Key::Delete) && $this->editorCursor < $length) {
            $value = mb_substr($value, 0, $this->editorCursor)
                . mb_substr($value, $this->editorCursor + 1);
            $draft->setValue($field, $value);
        } elseif ($key->char !== '') {
            $value = mb_substr($value, 0, $this->editorCursor)
                . $key->char
                . mb_substr($value, $this->editorCursor);
            $this->editorCursor += mb_strlen($key->char);
            $draft->setValue($field, $value);
        }
        $this->syncDatePickerToDraft();
        $this->clearEvent($event);
    }

    private function handleEditorMouse(Event $event): void
    {
        $mouse = $event->asMouse();
        $draft = $this->draft;
        if ($mouse === null || $draft === null) {
            return;
        }
        $local = $this->makeLocal($mouse->where);
        if (($mouse->buttons & 1) === 0) {
            $this->clearEvent($event);

            return;
        }
        if ($this->handleDatePickerMouse($local)) {
            $this->clearEvent($event);

            return;
        }

        [$x, $y, $width, $height] = $this->editorGeometry();
        $buttonY = $y + $height - 3;
        if ($local->y === $buttonY) {
            $this->closeDatePicker();
            if ($local->x >= $x + $width - 24 && $local->x < $x + $width - 14) {
                $this->draft = null;
                $this->setStatus('Changes cancelled.');
            } elseif ($local->x >= $x + $width - 11 && $local->x < $x + $width - 3) {
                $this->saveDraft();
            }
            $this->clearEvent($event);

            return;
        }

        $index = $local->y - ($y + 2);
        $fields = $this->editorFields();
        if ($index >= 0 && isset($fields[$index])) {
            $field = $fields[$index];
            $this->editorField = $index;
            if ($field === EventField::AllDay) {
                $this->closeDatePicker();
                $draft->allDay = ! $draft->allDay;
            } elseif ($field === EventField::Repeat) {
                $this->closeDatePicker();
                $draft->repeat = $draft->repeat->next();
            } elseif ($field->acceptsText()) {
                $valueX = $x + 15;
                $this->editorCursor = min(
                    mb_strlen($draft->value($field)),
                    max(0, $local->x - $valueX),
                );
                if ($this->isDateField($field) && $local->x >= $valueX) {
                    $this->openDatePicker($field);
                } else {
                    $this->closeDatePicker();
                }
            }
        } else {
            $this->closeDatePicker();
        }
        $this->clearEvent($event);
    }

    private function advanceEditorField(int $direction): void
    {
        $this->closeDatePicker();
        $fields = $this->editorFields();
        $count = count($fields);
        do {
            $this->editorField = (($this->editorField + $direction) % $count + $count) % $count;
            $field = $fields[$this->editorField];
        } while ($this->draft?->allDay === true
            && ($field === EventField::StartTime || $field === EventField::EndTime));

        $this->editorCursor = mb_strlen($this->draft?->value($field) ?? '');
    }

    private function isDateField(EventField $field): bool
    {
        return $field === EventField::StartDate || $field === EventField::EndDate;
    }

    private function openDatePicker(EventField $field): void
    {
        $draft = $this->draft;
        if ($draft === null || ! $this->isDateField($field)) {
            return;
        }

        $date = $this->parseDate($draft->value($field)) ?? $this->selectedDate;
        $this->datePickerField = $field;
        $this->datePickerMonth = $date->modify('first day of this month')->setTime(0, 0);
    }

    private function closeDatePicker(): void
    {
        $this->datePickerField = null;
        $this->datePickerMonth = null;
    }

    private function syncDatePickerToDraft(): void
    {
        $field = $this->datePickerField;
        $draft = $this->draft;
        if ($field === null || $draft === null) {
            return;
        }

        $date = $this->parseDate($draft->value($field));
        if ($date !== null) {
            $this->datePickerMonth = $date->modify('first day of this month')->setTime(0, 0);
        }
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    private function changeDatePickerMonth(int $months): void
    {
        if ($this->datePickerMonth === null) {
            return;
        }

        $this->datePickerMonth = $this->datePickerMonth->modify(($months >= 0 ? '+' : '') . $months . ' months');
    }

    private function handleDatePickerMouse(Point $local): bool
    {
        $field = $this->datePickerField;
        $draft = $this->draft;
        if ($field === null || $draft === null) {
            return false;
        }

        [$x, $y, $width, $height] = $this->datePickerGeometry();
        if ($local->x < $x || $local->x >= $x + $width || $local->y < $y || $local->y >= $y + $height) {
            return false;
        }

        if ($local->y === $y + 1) {
            if ($local->x >= $x + 1 && $local->x <= $x + 3) {
                $this->changeDatePickerMonth(-1);
            } elseif ($local->x >= $x + $width - 4 && $local->x <= $x + $width - 2) {
                $this->changeDatePickerMonth(1);
            }

            return true;
        }

        $date = $this->datePickerDateAt($local);
        if ($date !== null) {
            $draft->setValue($field, $date->format('Y-m-d'));
            $this->editorCursor = 10;
            $this->closeDatePicker();
        }

        return true;
    }

    private function datePickerDateAt(Point $local): ?DateTimeImmutable
    {
        if ($this->datePickerMonth === null) {
            return null;
        }

        [$x, $y] = $this->datePickerGeometry();
        $columnOffset = $local->x - ($x + 2);
        $row = $local->y - ($y + 3);
        if ($columnOffset < 0 || $columnOffset >= 21 || $row < 0 || $row >= 6) {
            return null;
        }

        $column = intdiv($columnOffset, 3);

        return $this->datePickerGridStart()->modify('+' . ($row * 7 + $column) . ' days');
    }

    private function datePickerGridStart(): DateTimeImmutable
    {
        $month = $this->datePickerMonth ?? $this->selectedDate->modify('first day of this month');

        return $month->modify('-' . ((int) $month->format('N') - 1) . ' days');
    }

    private function saveDraft(): void
    {
        $draft = $this->draft;
        if ($draft === null) {
            return;
        }
        if (! $this->persistenceAvailable()) {
            return;
        }

        try {
            $calendarEvent = $draft->toEvent($this->timezone);
            $before = $this->calendar->all();
            $this->calendar->upsert($calendarEvent);
            try {
                $this->store->save($this->path, $this->calendar->all());
            } catch (RuntimeException $exception) {
                $this->calendar->replace($before);

                throw $exception;
            }
            $this->selectedDate = $calendarEvent->start->setTime(0, 0);
            $this->agendaIndex = $this->indexOfEvent($calendarEvent->uid);
            $verb = $this->editingExisting ? 'Updated' : 'Created';
            $this->draft = null;
            $this->closeDatePicker();
            $this->focus = CalendarFocus::Agenda;
            $this->setStatus("{$verb} “{$calendarEvent->title}” and saved to " . basename($this->path) . '.');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->setStatus($exception->getMessage(), true);
        }
    }

    private function persist(string $verb): void
    {
        if (! $this->persistenceAvailable()) {
            return;
        }

        try {
            $this->store->save($this->path, $this->calendar->all());
            $this->setStatus("{$verb} " . count($this->calendar->all()) . ' events to ' . basename($this->path) . '.');
        } catch (RuntimeException $exception) {
            $this->setStatus($exception->getMessage(), true);
        }
    }

    private function reload(): void
    {
        try {
            $events = $this->store->load($this->path);
            $this->calendar->replace($events);
            $this->persistenceBlocked = false;
            $this->agendaIndex = 0;
            $this->setStatus('Loaded ' . count($events) . ' events from ' . basename($this->path) . '.');
        } catch (RuntimeException $exception) {
            $this->persistenceBlocked = true;
            $this->setStatus($exception->getMessage(), true);
        }
    }

    private function persistenceAvailable(): bool
    {
        if (! $this->persistenceBlocked) {
            return true;
        }

        $this->setStatus(
            'Saving is disabled because the existing calendar could not be loaded. Repair it, then reload.',
            true,
        );

        return false;
    }

    private function setStatus(string $message, bool $error = false): void
    {
        $this->status = $message;
        $this->statusIsError = $error;
    }

    /** @return list<CalendarEvent> */
    private function eventsForSelectedDate(): array
    {
        return $this->calendar->eventsOn($this->selectedDate);
    }

    private function indexOfEvent(string $uid): int
    {
        foreach ($this->eventsForSelectedDate() as $index => $event) {
            if ($event->uid === $uid) {
                return $index;
            }
        }

        return 0;
    }

    /** @return list<EventField> */
    private function editorFields(): array
    {
        return EventField::cases();
    }

    private function mainWidth(): int
    {
        $width = $this->bounds->width();
        $sidebar = max(29, min(42, intdiv($width, 3)));

        return $width - $sidebar;
    }

    private function gridStartDate(): DateTimeImmutable
    {
        $first = $this->selectedDate->modify('first day of this month');
        $weekday = (int) $first->format('N');

        return $first->modify('-' . ($weekday - 1) . ' days');
    }

    private function dateAtGridPoint(int $x, int $y, int $mainWidth): ?DateTimeImmutable
    {
        $gridHeight = $this->bounds->height() - 3;
        if ($mainWidth <= 0 || $gridHeight <= 0) {
            return null;
        }
        $column = min(6, intdiv(max(0, $x) * 7, $mainWidth));
        $row = min(5, intdiv(max(0, $y - 2) * 6, $gridHeight));

        return $this->gridStartDate()->modify('+' . ($row * 7 + $column) . ' days');
    }

    /** @return list<int> */
    private function gridColumnSeparators(int $mainWidth): array
    {
        $separators = [];
        for ($boundary = 1; $boundary < 7; $boundary++) {
            $separators[] = intdiv($boundary * $mainWidth, 7) - 1;
        }

        return $separators;
    }

    /** @return list<int> */
    private function gridRowSeparators(int $height): array
    {
        $gridTop = 2;
        $gridHeight = $height - 3;
        $separators = [];
        for ($boundary = 1; $boundary < 6; $boundary++) {
            $separators[] = $gridTop + intdiv($boundary * $gridHeight, 6) - 1;
        }

        return $separators;
    }

    private function drawToolbar(int $mainWidth, int $width): void
    {
        $this->fillRect(0, 0, $width, 1, ' ', $this->theme->canvas);
        $this->writeClipped(1, 0, '‹', 3, $this->theme->canvas);
        $this->writeClipped(4, 0, '›', 3, $this->theme->canvas);
        $this->writeClipped(8, 0, '[ Today ]', 9, $this->theme->canvas);

        $month = $this->selectedDate->format('F Y');
        $monthX = max(18, intdiv($mainWidth - mb_strlen($month), 2));
        $controlsWidth = $mainWidth >= 70 ? 26 : 13;
        $this->writeClipped($monthX, 0, $month, max(0, $mainWidth - $monthX - $controlsWidth), $this->theme->primary);

        if ($mainWidth >= 70) {
            $this->writeClipped($mainWidth - 23, 0, '[ + ]', 5, $this->theme->canvas);
            $this->writeClipped($mainWidth - 16, 0, '[ Load ]', 8, $this->theme->canvas);
            $this->writeClipped($mainWidth - 7, 0, '[Save]', 7, $this->theme->canvas);
        } else {
            $this->writeClipped($mainWidth - 11, 0, '[+]', 3, $this->theme->canvas);
            $this->writeClipped($mainWidth - 7, 0, '[↻]', 3, $this->theme->canvas);
            $this->writeClipped($mainWidth - 3, 0, '[S]', 3, $this->theme->canvas);
        }
        $this->writeClipped($mainWidth + 2, 0, 'Events', max(0, $width - $mainWidth - 3), $this->theme->primary);
    }

    private function drawMonthGrid(int $mainWidth, int $height): void
    {
        $weekdays = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
        for ($column = 0; $column < 7; $column++) {
            $x0 = intdiv($column * $mainWidth, 7);
            $x1 = intdiv(($column + 1) * $mainWidth, 7);
            $label = $weekdays[$column];
            $x = $x0 + max(0, intdiv(($x1 - $x0 - mb_strlen($label)), 2));
            $attr = $column >= 5 ? $this->theme->weekend : $this->theme->muted;
            $this->writeClipped($x, 1, $label, max(0, $x1 - $x), $attr);
        }

        $gridTop = 2;
        $gridHeight = $height - 3;
        $start = $this->gridStartDate();
        for ($row = 0; $row < 6; $row++) {
            $y0 = $gridTop + intdiv($row * $gridHeight, 6);
            $y1 = $gridTop + intdiv(($row + 1) * $gridHeight, 6);
            for ($column = 0; $column < 7; $column++) {
                $x0 = intdiv($column * $mainWidth, 7);
                $x1 = intdiv(($column + 1) * $mainWidth, 7);
                $date = $start->modify('+' . ($row * 7 + $column) . ' days');
                $this->drawDayCell($date, $x0, $y0, $x1, $y1, $column, $row);
            }
        }

        foreach ($this->gridRowSeparators($height) as $y) {
            foreach ($this->gridColumnSeparators($mainWidth) as $x) {
                $this->writeClipped($x, $y, Glyphs::SINGLE_CROSS, 1, $this->theme->grid);
            }
        }
    }

    private function drawDayCell(
        DateTimeImmutable $date,
        int $x0,
        int $y0,
        int $x1,
        int $y1,
        int $column,
        int $row,
    ): void {
        $rightBorder = $column < 6 ? 1 : 0;
        $bottomBorder = $row < 5 ? 1 : 0;
        $contentWidth = max(0, $x1 - $x0 - $rightBorder);
        $contentHeight = max(0, $y1 - $y0 - $bottomBorder);
        $this->fillRect($x0, $y0, $contentWidth, $contentHeight, ' ', $this->theme->canvas);

        if ($rightBorder === 1) {
            for ($y = $y0; $y < $y1; $y++) {
                $this->writeClipped($x1 - 1, $y, Glyphs::SINGLE_VERTICAL, 1, $this->theme->grid);
            }
        }
        if ($bottomBorder === 1) {
            $this->fillRect($x0, $y1 - 1, $x1 - $x0, 1, Glyphs::SINGLE_HORIZONTAL, $this->theme->grid);
        }

        $selected = $date->format('Y-m-d') === $this->selectedDate->format('Y-m-d');
        $hovered = $date->format('Y-m-d') === $this->hoveredDate?->format('Y-m-d');
        $today = $date->format('Y-m-d') === $this->today->format('Y-m-d');
        $inMonth = $date->format('Y-m') === $this->selectedDate->format('Y-m');
        $dateAttr = $selected
            ? $this->theme->selection
            : ($hovered
                ? $this->theme->accent
                : ($today ? $this->theme->accent : ($inMonth ? ($column >= 5 ? $this->theme->weekend : $this->theme->canvas) : $this->theme->muted)));
        $dateLabel = $selected
            ? '[' . $date->format('j') . ']'
            : ($hovered ? '‹' . $date->format('j') . '›' : $date->format('j'));
        $this->writeClipped($x0 + 1, $y0, $dateLabel, max(0, $contentWidth - 1), $dateAttr);

        $events = $this->calendar->eventsOn($date);
        $available = max(0, $contentHeight - 1);
        $shown = count($events) > $available
            ? max(0, $available - 1)
            : min($available, count($events));
        for ($index = 0; $index < $shown; $index++) {
            $event = $events[$index];
            $eventY = $y0 + 1 + $index;
            $prefix = $event->allDay ? '' : $event->start->format('H:i') . ' ';
            $text = $prefix . $event->title;
            $this->writeClipped($x0 + 1, $eventY, '●', 1, $this->calendarColor($event->calendar));
            $this->writeClipped($x0 + 2, $eventY, $text, max(0, $contentWidth - 3), $inMonth ? $this->theme->canvas : $this->theme->muted);
        }
        if (count($events) > $shown && $available > 0) {
            $this->writeClipped(
                $x0 + 2,
                $y0 + $contentHeight - 1,
                '+' . (count($events) - $shown) . ' more',
                max(0, $contentWidth - 3),
                $this->theme->muted,
            );
        }
    }

    private function drawAgenda(int $mainWidth, int $width, int $height): void
    {
        for ($y = 0; $y < $height - 1; $y++) {
            $this->writeClipped($mainWidth, $y, Glyphs::SINGLE_VERTICAL, 1, $this->theme->grid);
        }
        foreach ($this->gridRowSeparators($height) as $y) {
            $this->writeClipped($mainWidth, $y, Glyphs::SINGLE_TEE_LEFT, 1, $this->theme->grid);
        }

        $x = $mainWidth + 2;
        $availableWidth = max(0, $width - $x - 1);
        $dateTitle = $this->selectedDate->format('l, F j');
        $this->writeClipped($x, 1, $dateTitle, $availableWidth, $this->theme->primary);
        $events = $this->eventsForSelectedDate();
        $subtitle = count($events) === 1 ? '1 event' : count($events) . ' events';
        if ($this->focus === CalendarFocus::Agenda) {
            $subtitle .= '  •  focused';
        }
        $this->writeClipped($x, 2, $subtitle, $availableWidth, $this->theme->muted);

        if ($events === []) {
            $this->writeClipped($x, 5, 'No events scheduled', $availableWidth, $this->theme->muted);
            $this->writeClipped($x, 7, 'Press N or double-click a day', $availableWidth, $this->theme->muted);

            return;
        }

        $maxVisible = max(1, min(count($events), intdiv(max(4, $height - 12), 2)));
        for ($index = 0; $index < $maxVisible; $index++) {
            $event = $events[$index];
            $y = 4 + $index * 2;
            $selected = $index === $this->agendaIndex;
            $attr = $selected ? $this->theme->selection : $this->theme->canvas;
            if ($selected) {
                $this->writeClipped($x - 1, $y, '›', 1, $this->theme->selection);
            }
            $this->writeClipped($x, $y, '●', 1, $selected ? $this->theme->selection : $this->calendarColor($event->calendar));
            $this->writeClipped($x + 2, $y, $event->title, max(0, $availableWidth - 2), $attr);
            $meta = $event->timeLabel();
            if ($event->location !== '') {
                $meta .= '  ·  ' . $event->location;
            }
            $this->writeClipped($x + 2, $y + 1, $meta, max(0, $availableWidth - 2), $selected ? $this->theme->selection : $this->theme->muted);
        }

        $selected = $this->selectedEvent();
        if ($selected === null) {
            return;
        }
        $detailY = 5 + $maxVisible * 2;
        if ($detailY >= $height - 2) {
            return;
        }
        $this->fillRect($x, $detailY, $availableWidth, 1, Glyphs::SINGLE_HORIZONTAL, $this->theme->grid);
        $detailY++;
        $this->writeClipped($x, $detailY++, $selected->title, $availableWidth, $this->theme->primary);
        $this->writeClipped($x, $detailY++, $selected->timeLabel(), $availableWidth, $this->theme->muted);
        $this->writeClipped($x, $detailY++, 'Calendar  ' . $selected->calendar, $availableWidth, $this->theme->muted);
        if ($selected->repeat !== RepeatRule::Never && $detailY < $height - 1) {
            $this->writeClipped($x, $detailY++, 'Repeats   ' . $selected->repeat->label(), $availableWidth, $this->theme->muted);
        }
        if ($selected->location !== '' && $detailY < $height - 1) {
            $this->writeClipped($x, $detailY++, 'Location  ' . $selected->location, $availableWidth, $this->theme->muted);
        }
        if ($selected->notes !== '' && $detailY < $height - 1) {
            foreach ($this->wrap($selected->notes, $availableWidth) as $line) {
                if ($detailY >= $height - 1) {
                    break;
                }
                $this->writeClipped($x, $detailY++, $line, $availableWidth, $this->theme->muted);
            }
        }
    }

    private function drawStatus(int $width, int $height): void
    {
        $attr = $this->statusIsError ? $this->theme->error : $this->theme->status;
        $this->fillRect(0, $height - 1, $width, 1, ' ', $attr);
        $this->writeClipped(1, $height - 1, $this->status, max(0, $width - 2), $attr);
    }

    private function drawContextMenu(): void
    {
        $date = $this->contextDate;
        if ($date === null) {
            return;
        }

        [$x, $y, $width, $height] = $this->contextMenuGeometry();
        $this->fillRect($x, $y, $width, $height, ' ', $this->theme->canvas);
        $this->drawBox($x, $y, $width, $height, $this->theme->grid);
        $heading = ' ' . $date->format('D, M j') . ' ';
        $this->writeClipped($x + 2, $y, $heading, max(0, $width - 4), $this->theme->primary);

        foreach ($this->contextActions() as $index => $action) {
            $row = $y + 1 + $index;
            $enabled = $this->contextActionEnabled($action);
            $attr = ! $enabled
                ? $this->theme->muted
                : ($index === $this->contextIndex ? $this->theme->selection : $this->theme->canvas);
            $marker = $enabled && $index === $this->contextIndex ? '›' : ' ';
            $this->writeClipped($x + 1, $row, $marker, 1, $attr);
            $this->writeClipped($x + 3, $row, $action->label(), max(0, $width - 8), $attr);
            $this->writeClipped($x + $width - 3, $row, $action->shortcut(), 1, $attr);
        }
    }

    /** @return array{int, int, int, int} */
    private function contextMenuGeometry(): array
    {
        $width = 28;
        $height = count($this->contextActions()) + 2;
        $origin = $this->contextOrigin ?? new Point(1, 1);
        $x = max(1, min($origin->x + 1, $this->bounds->width() - $width - 1));
        $y = max(1, min($origin->y, $this->bounds->height() - $height - 1));

        return [$x, $y, $width, $height];
    }

    private function drawEditor(): void
    {
        $draft = $this->draft;
        if ($draft === null) {
            return;
        }

        [$x, $y, $boxWidth, $boxHeight] = $this->editorGeometry();
        $this->fillRect($x + 2, $y + 1, $boxWidth, $boxHeight, '░', $this->theme->shadow);
        $this->fillRect($x, $y, $boxWidth, $boxHeight, ' ', $this->theme->canvas);
        $this->drawBox($x, $y, $boxWidth, $boxHeight, $this->theme->grid);

        $heading = $this->editingExisting ? ' Edit Event ' : ' New Event ';
        $this->writeClipped($x + 3, $y, $heading, max(0, $boxWidth - 6), $this->theme->primary);
        $fields = $this->editorFields();
        $labelWidth = 11;
        $valueWidth = max(0, $boxWidth - $labelWidth - 6);
        foreach ($fields as $index => $field) {
            $row = $y + 2 + $index;
            if ($row >= $y + $boxHeight - 3) {
                break;
            }
            $selected = $index === $this->editorField;
            $attr = $selected ? $this->theme->selection : $this->theme->canvas;
            if ($selected) {
                $this->writeClipped($x + 2, $row, '›', 1, $attr);
            }
            $this->writeClipped($x + 3, $row, $field->value, $labelWidth, $attr);
            $rawValue = $draft->value($field);
            $value = match ($field) {
                EventField::AllDay => $draft->allDay ? '[x] Yes' : '[ ] No',
                EventField::Repeat => '‹ ' . $draft->repeat->label() . ' ›',
                default => $rawValue,
            };
            if ($this->isDateField($field)) {
                $value .= $this->datePickerField === $field ? '  ▴' : '  ▾';
            }
            if ($draft->allDay && ($field === EventField::StartTime || $field === EventField::EndTime)) {
                $value = '—';
            }
            $this->writeClipped($x + $labelWidth + 4, $row, $value, $valueWidth, $attr);
            if ($selected && $field->acceptsText()) {
                $cursorX = $x + $labelWidth + 4 + min($this->editorCursor, max(0, $valueWidth - 1));
                $cursorChar = mb_substr($rawValue, $this->editorCursor, 1);
                $this->writeClipped($cursorX, $row, $cursorChar !== '' ? $cursorChar : '▏', 1, $this->theme->selection);
            }
        }

        $buttonY = $y + $boxHeight - 3;
        $this->writeClipped($x + $boxWidth - 24, $buttonY, '[ Cancel ]', 10, $this->theme->canvas);
        $this->writeClipped($x + $boxWidth - 11, $buttonY, '[ Save ]', 8, $this->theme->selection);
        $helpY = $y + $boxHeight - 2;
        $this->writeClipped(
            $x + 3,
            $helpY,
            'Tab next field   Click dates for picker   Ctrl-S/F10 save   Esc cancel',
            max(0, $boxWidth - 6),
            $this->theme->muted,
        );

        $this->drawDatePicker();
    }

    private function drawDatePicker(): void
    {
        $field = $this->datePickerField;
        $month = $this->datePickerMonth;
        $draft = $this->draft;
        if ($field === null || $month === null || $draft === null) {
            return;
        }

        [$x, $y, $width, $height] = $this->datePickerGeometry();
        $this->fillRect($x + 1, $y + 1, $width, $height, '░', $this->theme->shadow);
        $this->fillRect($x, $y, $width, $height, ' ', $this->theme->canvas);
        $this->drawBox($x, $y, $width, $height, $this->theme->grid);

        $heading = $month->format('F Y');
        $headingX = $x + intdiv($width - mb_strlen($heading), 2);
        $this->writeClipped($x + 2, $y + 1, '‹', 1, $this->theme->accent);
        $this->writeClipped($headingX, $y + 1, $heading, max(0, $width - ($headingX - $x) - 2), $this->theme->primary);
        $this->writeClipped($x + $width - 3, $y + 1, '›', 1, $this->theme->accent);
        $this->writeClipped($x + 2, $y + 2, 'Mo Tu We Th Fr Sa Su', 20, $this->theme->muted);

        $selectedDate = $this->parseDate($draft->value($field));
        $gridDate = $this->datePickerGridStart();
        for ($row = 0; $row < 6; $row++) {
            for ($column = 0; $column < 7; $column++) {
                $date = $gridDate->modify('+' . ($row * 7 + $column) . ' days');
                $isSelected = $selectedDate?->format('Y-m-d') === $date->format('Y-m-d');
                $inMonth = $date->format('Y-m') === $month->format('Y-m');
                $attr = $isSelected
                    ? $this->theme->selection
                    : ($inMonth ? $this->theme->canvas : $this->theme->muted);
                if (! $isSelected && $date->format('Y-m-d') === $this->today->format('Y-m-d')) {
                    $attr = $this->theme->accent;
                }
                $label = ($isSelected ? '•' : ' ') . str_pad($date->format('j'), 2, ' ', STR_PAD_LEFT);
                $this->writeClipped($x + 2 + $column * 3, $y + 3 + $row, $label, 3, $attr);
            }
        }
    }

    private function drawDeleteConfirmation(): void
    {
        $event = $this->selectedEvent();
        if ($event === null) {
            return;
        }
        [$x, $y, $boxWidth] = $this->deleteBoxGeometry();
        $this->fillRect($x + 2, $y + 1, $boxWidth, 7, '░', $this->theme->shadow);
        $this->fillRect($x, $y, $boxWidth, 7, ' ', $this->theme->canvas);
        $this->drawBox($x, $y, $boxWidth, 7, $this->theme->error);
        $this->writeClipped($x + 3, $y + 2, 'Delete “' . $event->title . '”?', max(0, $boxWidth - 6), $this->theme->primary);
        $this->writeClipped($x + 3, $y + 4, '[ Y ] Delete     [ N ] Cancel', max(0, $boxWidth - 6), $this->theme->error);
    }

    /** @return array{int, int, int, int} */
    private function editorGeometry(): array
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $boxWidth = min(72, $width - 8);
        $boxHeight = min(16, $height - 4);

        return [
            intdiv($width - $boxWidth, 2),
            intdiv($height - $boxHeight, 2),
            $boxWidth,
            $boxHeight,
        ];
    }

    /** @return array{int, int, int, int} */
    private function datePickerGeometry(): array
    {
        [$editorX, $editorY] = $this->editorGeometry();
        $fieldIndex = array_search($this->datePickerField, $this->editorFields(), true);
        $inputY = $editorY + 2 + (is_int($fieldIndex) ? $fieldIndex : 0);
        $width = 25;
        $height = 10;
        $x = max(1, min($editorX + 15, $this->bounds->width() - $width - 1));
        $y = max(1, min($inputY + 1, $this->bounds->height() - $height - 2));

        return [$x, $y, $width, $height];
    }

    /** @return array{int, int, int} */
    private function deleteBoxGeometry(): array
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $boxWidth = min(58, $width - 8);

        return [intdiv($width - $boxWidth, 2), intdiv($height - 7, 2), $boxWidth];
    }

    private function drawCompactWarning(int $width, int $height): void
    {
        $message = 'Calendar needs at least 72 × 20';
        $x = max(0, intdiv($width - mb_strlen($message), 2));
        $y = max(0, intdiv($height, 2));
        $this->writeClipped($x, $y, $message, max(0, $width - $x), $this->theme->error);
        $this->drawStatus($width, $height);
    }

    private function drawBox(int $x, int $y, int $width, int $height, int $attr): void
    {
        if ($width < 2 || $height < 2) {
            return;
        }
        $this->fillRect($x + 1, $y, $width - 2, 1, Glyphs::SINGLE_HORIZONTAL, $attr);
        $this->fillRect($x + 1, $y + $height - 1, $width - 2, 1, Glyphs::SINGLE_HORIZONTAL, $attr);
        for ($row = $y + 1; $row < $y + $height - 1; $row++) {
            $this->writeClipped($x, $row, Glyphs::SINGLE_VERTICAL, 1, $attr);
            $this->writeClipped($x + $width - 1, $row, Glyphs::SINGLE_VERTICAL, 1, $attr);
        }
        $this->writeClipped($x, $y, Glyphs::SINGLE_TOP_LEFT, 1, $attr);
        $this->writeClipped($x + $width - 1, $y, Glyphs::SINGLE_TOP_RIGHT, 1, $attr);
        $this->writeClipped($x, $y + $height - 1, Glyphs::SINGLE_BOTTOM_LEFT, 1, $attr);
        $this->writeClipped($x + $width - 1, $y + $height - 1, Glyphs::SINGLE_BOTTOM_RIGHT, 1, $attr);
    }

    private function calendarColor(string $calendar): int
    {
        return $this->theme->eventColor($calendar);
    }

    /** @return list<string> */
    private function wrap(string $text, int $width): array
    {
        if ($width <= 0) {
            return [];
        }
        $words = preg_split('/\s+/', trim($text));
        if ($words === false || $words === []) {
            return [];
        }

        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate) <= $width) {
                $line = $candidate;

                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
            $line = mb_substr($word, 0, $width);
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function fillRect(int $x, int $y, int $width, int $height, string $char, int $attr): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, $char, $attr, $width);
        for ($row = 0; $row < $height; $row++) {
            $this->writeLine($x, $y + $row, $width, 1, $buffer);
        }
    }

    private function writeClipped(int $x, int $y, string $text, int $width, int $attr): void
    {
        if ($width <= 0 || $text === '') {
            return;
        }
        $this->writeStr($x, $y, mb_substr($text, 0, $width), $attr);
    }
}
