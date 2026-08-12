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

    /**
     * Plural, human-readable type label — the single source shared by the
     * explorer's filters/breadcrumb and the dashboard's community trails.
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::LocationCommunity => __('communities.plural.locations'),
            self::Organisation      => __('communities.plural.organisations'),
            self::Campaign          => __('communities.plural.campaigns'),
            self::Course            => __('communities.plural.courses'),
            self::Event             => __('communities.plural.events'),
            self::ThemeCommunity    => __('communities.plural.theme_communities'),
        };
    }
}
