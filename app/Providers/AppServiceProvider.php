<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow <x-layouts.*> to resolve to resources/views/layouts/*.blade.php
        // (same files Livewire components reference via #[Layout('layouts.*')]).
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');
    }
}
