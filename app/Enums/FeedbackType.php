<?php

namespace App\Enums;

enum FeedbackType: string
{
    case Issue = 'issue';
    case FeatureRequest = 'feature_request';
    case Suggestion = 'suggestion';
    case Question = 'question';
    case Praise = 'praise';
}
