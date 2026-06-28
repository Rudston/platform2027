<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CommunityTypeFilter extends Component
{
    /** CommunityType value (FQCN) or null for "All". Reactive so the active pill tracks the parent. */
    #[Reactive]
    public ?string $selectedType = null;

    /**
     * @return array<int, array{value: ?string, label: string, icon: string}>
     */
    public function pills(): array
    {
        return [
            ['value' => null,                                       'label' => 'All',           'icon' => '🌍'],
            ['value' => CommunityType::LocationCommunity->value,    'label' => 'Locations',     'icon' => '📍'],
            ['value' => CommunityType::ThemeCommunity->value,       'label' => 'Theme Communities',        'icon' => '💡'],
            ['value' => CommunityType::Organisation->value,         'label' => 'Organisations', 'icon' => '🏛'],
            ['value' => CommunityType::Campaign->value,             'label' => 'Campaigns',     'icon' => '📢'],
            ['value' => CommunityType::Course->value,               'label' => 'Courses',       'icon' => '🎓'],
            ['value' => CommunityType::Event->value,                'label' => 'Events',        'icon' => '📅'],
        ];
    }

    public function render()
    {
        return view('livewire.explore.community-type-filter', [
            'pills' => $this->pills(),
        ]);
    }
}
