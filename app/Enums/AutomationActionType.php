<?php

namespace App\Enums;

enum AutomationActionType: string
{
    case Notify = 'notify';
    case Log = 'log';
    case Score = 'score';
    case Tag = 'tag';
    case QueueJob = 'queue_job';
}
