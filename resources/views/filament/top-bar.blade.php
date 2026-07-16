{{-- Site top bar rendered inside the Filament admin panel (BODY_START render
     hook). Styling is self-contained (scoped CSS, not the app's Tailwind build,
     which the panel doesn't load); dark mode follows Filament's `dark` class on
     <html>. No theme toggle here — Filament provides its own. --}}
@php($navUser = auth()->user())
<style>
    .site-top-bar{background:#fff;color:#111827;border-bottom:1px solid #e5e7eb;font-size:.875rem}
    :is(html.dark) .site-top-bar{background:#0f172a;color:#f9fafb;border-bottom-color:#334155}
    .site-top-bar__inner{max-width:80rem;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem}
    .site-top-bar__group{display:flex;align-items:center;gap:1.5rem}
    .site-top-bar__right{display:flex;align-items:center;gap:.75rem}
    .site-top-bar a{color:inherit;text-decoration:none}
    .site-top-bar a:hover{text-decoration:underline}
    .site-top-bar__brand{font-weight:600}
    .site-top-bar__admin{font-weight:500}
    .site-top-bar__muted{color:#6b7280}
    :is(html.dark) .site-top-bar__muted{color:#9ca3af}
    .site-top-bar__btn{border:1px solid #e5e7eb;border-radius:.375rem;padding:.375rem 1rem;background:transparent;color:inherit;cursor:pointer;font:inherit;line-height:1.5}
    :is(html.dark) .site-top-bar__btn{border-color:#334155}
    .site-top-bar__btn:hover{opacity:.8}
    .site-top-bar form{margin:0}
</style>
<nav class="site-top-bar">
    <div class="site-top-bar__inner">
        <div class="site-top-bar__group">
            <a href="{{ url('/') }}" class="site-top-bar__brand">{{ config('app.name', 'Platform2027') }}</a>
            <a href="{{ route('explore') }}">{{ __('navigation.explore_communities') }}</a>
            <a href="{{ route('dashboard') }}">{{ __('navigation.dashboard') }}</a>
        </div>
        <div class="site-top-bar__right">
            @if ($navUser?->hasAnyRole(['admin', 'superadmin']))
                <a href="{{ url('/admin') }}" class="site-top-bar__admin">{{ __('navigation.admin') }}</a>
            @endif
            <span class="site-top-bar__muted">{{ $navUser?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="site-top-bar__btn">{{ __('navigation.log_out') }}</button>
            </form>
        </div>
    </div>
</nav>
