<?php

namespace App\Enums;

enum ThemeSuggestionStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
