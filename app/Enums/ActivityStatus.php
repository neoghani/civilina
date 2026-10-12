<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Planned = 'planned';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
