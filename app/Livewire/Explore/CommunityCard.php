<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use Livewire\Component;

class CommunityCard extends Component
{
    public Circle $circle;

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
            'Country'              => 'Country',
            'Province'            => 'Province',
            'DistrictMunicipality' => 'DM',
            'LocalMunicipality'   => 'Local Muni',
            'City'                => ($this->circle->locatable?->metropolis ? 'Metro' : 'City'),
            default               => class_basename((string) $this->circle->locatable_type),
        };
    }

    public function render()
    {
        return view('livewire.explore.community-card');
    }
}
