<?php

namespace App\Enums\Forums;

enum ForumGroupVisibility: string
{
    case Public     = 'public';
    case Private    = 'private';
    case InviteOnly = 'invite-only';
}
