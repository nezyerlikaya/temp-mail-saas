<?php

namespace App\Enums;

enum ContentType: string
{
    case Page = 'page';
    case Post = 'post';
    case Announcement = 'announcement';
    case Help = 'help';
}
