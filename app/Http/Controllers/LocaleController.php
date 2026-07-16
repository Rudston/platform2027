<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the session locale, then return to the previous page. The new
     * locale is applied on the next request by SetLocaleFromBrowser (which
     * reads session('locale')). Unsupported values are ignored.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, config('app.supported_locales', []), true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
