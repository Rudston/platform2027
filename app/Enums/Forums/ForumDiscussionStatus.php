<?php

namespace App\Enums\Forums;

enum ForumDiscussionStatus: string
{
    case Active      = 'active';
    case Deactivated = 'deactivated';
}
