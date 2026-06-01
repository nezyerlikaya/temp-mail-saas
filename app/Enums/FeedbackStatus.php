<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Planned = 'planned';
    case Closed = 'closed';
}
