<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Calendar;

enum EventField: string
{
    case Title = 'Title';
    case StartDate = 'Starts on';
    case StartTime = 'Starts at';
    case EndDate = 'Ends on';
    case EndTime = 'Ends at';
    case AllDay = 'All day';
    case Location = 'Location';
    case Calendar = 'Calendar';
    case Repeat = 'Repeat';
    case Notes = 'Notes';

    public function acceptsText(): bool
    {
        return $this !== self::AllDay && $this !== self::Repeat;
    }
}
