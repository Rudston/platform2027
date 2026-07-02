<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use Livewire\Component;

class CommunityCard extends Component
{
    public Circle $circle;

    /** Relative Explore URL to return to, passed through to the community page as ?from=. */
    public ?string $from = null;

    public function icon(): string
    {
        return match ($this->circle->circleable_type) {
            CommunityType::LocationCommunity->value => '📍',
            CommunityType::Organisation->value      => '🏛',
            CommunityType::Campaign->value          => '📢',
            CommunityType::Course->value            => '🎓',
            CommunityType::Event->value             => '📅',
            CommunityType::ThemeCommunity->value    => '💡',
            default                                 => '🌍',
        };
    }

    public function levelBadge(): string
    {
        return match (class_basename((string) $this->circle->locatable_type)) {
            'Country'              => __('geographic.badge_card.national'),
            'Province'            => __('geographic.badge_card.provincial'),
            'DistrictMunicipality' => __('geographic.badge_card.dm'),
            'LocalMunicipality'   => __('geographic.badge_card.lm'),
            'MainPlace'           => __('geographic.badge_card.main_place'),
            'City'                => ($this->circle->locatable?->metropolis ? __('geographic.badge_card.metro') : __('geographic.badge_card.city')),
             default               => class_basename((string) $this->circle->locatable_type),
        };
    }

    public function render()
    {
        return view('livewire.explore.community-card');
    }
}
