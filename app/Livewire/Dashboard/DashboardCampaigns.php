<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class DashboardCampaigns extends Component
{
    public function render()
    {
        return view('livewire.dashboard.placeholder', [
            'heading' => __('dashboard.placeholder.campaigns.heading'),
            'body' => __('dashboard.placeholder.campaigns.body'),
        ])->title(__('dashboard.placeholder.campaigns.heading'));
    }
}
