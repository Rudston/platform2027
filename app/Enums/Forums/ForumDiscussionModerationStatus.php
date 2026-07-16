<?php

namespace App\Enums\Forums;

enum ForumDiscussionModerationStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
