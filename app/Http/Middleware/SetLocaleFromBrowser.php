<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromBrowser
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', [config('app.locale')]);

        // Priority chain (highest → lowest):
        //   1. Authenticated user's saved locale preference.
        //      TODO: the users table has no locale/language column yet — wire
        //            this tier in once it exists, e.g. $request->user()?->locale
        //   2. Session-stored locale (key 'locale'; set by a language switcher, if any).
        //   3. Browser Accept-Language, negotiated against the supported locales.
        //   4. App default locale.
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;

        $locale = $sessionLocale
            ?? $request->getPreferredLanguage($supported)
            ?? config('app.locale');

        // Never apply an unsupported locale (e.g. a stale session value).
        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        // Livewire XHR requests include Accept-Language automatically — no special handling needed
        // (Livewire's /livewire/update route uses the 'web' group, so this middleware runs for them).

        return $next($request);
    }
}
