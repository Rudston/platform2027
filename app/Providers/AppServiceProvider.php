<?php

namespace App\Providers;

use App\Contracts\Moderation\CommentModerationCheckerContract;
use App\Services\Moderation\StubModerationChecker;
use App\Support\DisplayTime;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
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

        // Render an absolute date/time in the wall clock the interface speaks
        // (config('app.display_timezone')), leaving the stored instant in UTC.
        // Storage stays UTC everywhere; only what a human READS is converted.
        // Relative output (diffForHumans) needs no conversion — a difference is
        // the same in any zone — which is why this was not noticed until Polls
        // became the first feature to show absolute times.
        Carbon::macro('inDisplayZone', function (): Carbon {
            /** @var Carbon $this */
            return DisplayTime::forDisplay($this);
        });

        // Filament renders its own date columns and pickers; point it at the
        // same wall clock so the admin panel and the front end never disagree.
        FilamentTimezone::set(DisplayTime::timezone());

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
