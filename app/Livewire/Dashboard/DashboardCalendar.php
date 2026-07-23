<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class DashboardCalendar extends Component
{
    public function render()
    {
        return view('livewire.dashboard.placeholder', [
            'heading' => __('dashboard.placeholder.calendar.heading'),
            'body' => __('dashboard.placeholder.calendar.body'),
        ])->title(__('dashboard.placeholder.calendar.heading'));
    }
}
