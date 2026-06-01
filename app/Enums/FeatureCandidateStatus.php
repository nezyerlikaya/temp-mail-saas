<?php

namespace App\Enums;

enum FeatureCandidateStatus: string
{
    case Proposed = 'proposed';
    case Reviewed = 'reviewed';
    case Accepted = 'accepted';
    case Deferred = 'deferred';
    case Rejected = 'rejected';
}
