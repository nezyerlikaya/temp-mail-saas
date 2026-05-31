<?php

namespace App\Support\Helpers;

final class ApplicationHelper
{
    public static function displayName(): string
    {
        return (string) config('tempmail.name', config('app.name', 'Temp Mail SaaS'));
    }
}
