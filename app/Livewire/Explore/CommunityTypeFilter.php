<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CommunityTypeFilter extends Component
{
    /**
     * Which subset of pills to render:
     *   'location'  → [All, Locations]            (top section)
     *   'community' → [Theme Communities, Organisations, Campaigns, Courses, Events]
     */
    public string $group = 'location';

    /** Currently active value for THIS bar (FQCN or null). Reactive so the active pill tracks the parent. */
    #[Reactive]
    public ?string $active = null;

    /**
     * @return array<int, array{value: ?string, label: string, icon: string}>
     */
    public function pills(): array
    {
        return match ($this->group) {
            'community' => [
                ['value' => CommunityType::ThemeCommunity->value, 'label' => 'Theme Communities', 'icon' => '💡'],
                ['value' => CommunityType::Organisation->value,   'label' => 'Organisations',     'icon' => '🏛'],
                ['value' => CommunityType::Campaign->value,       'label' => 'Campaigns',         'icon' => '📢'],
                ['value' => CommunityType::Course->value,         'label' => 'Courses',           'icon' => '🎓'],
                ['value' => CommunityType::Event->value,          'label' => 'Events',            'icon' => '📅'],
            ],
            default => [
                ['value' => null,                                    'label' => 'All',       'icon' => '🌍'],
                ['value' => CommunityType::LocationCommunity->value, 'label' => 'Locations', 'icon' => '📍'],
            ],
        };
    }

    /** Parent action invoked when a pill is clicked (differs per section). */
    public function action(): string
    {
        return $this->group === 'community' ? 'selectCommunityType' : 'selectType';
    }

    public function render()
    {
        return view('livewire.explore.community-type-filter', [
            'pills'  => $this->pills(),
            'action' => $this->action(),
        ]);
    }
}
