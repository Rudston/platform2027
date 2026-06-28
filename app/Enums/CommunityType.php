<?php

namespace App\Enums;

enum CommunityType: string
{
    case Organisation        = 'App\Models\Communities\OrganisationCommunity';
    case Campaign            = 'App\Models\Communities\Campaign';
    case Course              = 'App\Models\Communities\CourseCommunity';
    case Event               = 'App\Models\Communities\Event';
    case LocationCommunity   = 'App\Models\Communities\LocationCommunity';
    case ThemeCommunity      = 'App\Models\Communities\ThemeCommunity';

    public function modelClass(): string
    {
        return $this->value;
    }
}
