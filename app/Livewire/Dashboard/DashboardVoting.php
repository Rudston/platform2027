<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class DashboardVoting extends Component
{
    public function render()
    {
        return view('livewire.dashboard.placeholder', [
            'heading' => __('dashboard.placeholder.voting.heading'),
            'body' => __('dashboard.placeholder.voting.body'),
        ])->title(__('dashboard.placeholder.voting.heading'));
    }
}
