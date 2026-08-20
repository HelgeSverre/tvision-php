<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

enum CalendarContextAction
{
    case NewEvent;
    case EditFirstEvent;
    case ShowAgenda;
    case GoToToday;

    public function label(): string
    {
        return match ($this) {
            self::NewEvent => 'New Event',
            self::EditFirstEvent => 'Edit First Event',
            self::ShowAgenda => 'Show Day in Agenda',
            self::GoToToday => 'Jump to Today',
        };
    }

    public function shortcut(): string
    {
        return match ($this) {
            self::NewEvent => 'N',
            self::EditFirstEvent => 'E',
            self::ShowAgenda => 'A',
            self::GoToToday => 'T',
        };
    }
}
