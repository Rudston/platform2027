<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class DashboardNews extends Component
{
    public function render()
    {
        return view('livewire.dashboard.placeholder', [
            'heading' => __('dashboard.placeholder.news.heading'),
            'body' => __('dashboard.placeholder.news.body'),
        ])->title(__('dashboard.placeholder.news.heading'));
    }
}
