<?php

namespace App\Enums\Forums;

enum ForumGroupStatus: string
{
    case Active      = 'active';
    case Deactivated = 'deactivated';
    case Archived    = 'archived';
}
