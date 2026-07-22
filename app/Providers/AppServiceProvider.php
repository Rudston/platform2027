<?php

namespace App\Providers;

use App\Contracts\Moderation\CommentModerationCheckerContract;
use App\Services\Moderation\StubModerationChecker;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The ONLY place to change when real AI (OpenAI / a local LLM) replaces
        // the deterministic stub — all callers resolve the contract, never the
        // concrete class.
        $this->app->bind(CommentModerationCheckerContract::class, StubModerationChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow <x-layouts.*> to resolve to resources/views/layouts/*.blade.php
        // (same files Livewire components reference via #[Layout('layouts.*')]).
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Establish a region → base-language → fallback translation chain, e.g.
        // pt_BR → pt → en. Stock Laravel only resolves [locale, fallback_locale]
        // (i.e. pt_BR → en), so this inserts each region locale's base language
        // (the part before "_") before the fallback.
        Lang::determineLocalesUsing(function (array $locales) {
            $expanded = [];

            foreach ($locales as $locale) {
                $expanded[] = $locale;

                if (str_contains((string) $locale, '_')) {
                    $expanded[] = strtok((string) $locale, '_');
                }
            }

            return array_values(array_unique($expanded));
        });
    }
}
