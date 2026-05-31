<?php

namespace App\Enums;

enum OrganizationMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';
}
