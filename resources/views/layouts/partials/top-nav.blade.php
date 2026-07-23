{{-- The app-wide top bar (brand + primary links on the left; language, theme,
     profile, admin and auth actions on the right). Shared by layouts.main and
     layouts.dashboard so profile / admin / language stay identical and in ONE
     place across both. --}}
<nav class="border-b border-border-muted bg-surface">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 text-sm">
        {{-- Left: brand + primary navigation --}}
        <div class="flex items-center gap-6">
            <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name', 'Platform2027') }}</a>
            <a href="{{ route('explore') }}" class="hover:underline">{{ __('navigation.explore_communities') }}</a>
            @auth
                <a href="{{ route('dashboard') }}" class="hover:underline">{{ __('navigation.dashboard') }}</a>
            @endauth
        </div>

        {{-- Right: language switcher + theme toggle + auth actions --}}
        <div class="flex items-center gap-3">
            {{-- Language switcher (available to guests too) --}}
            @php($localeLabels = ['en' => 'EN', 'pt_BR' => 'PT'])
            <div class="flex items-center gap-1 text-xs">
                @foreach (config('app.supported_locales', []) as $loc)
                    <a href="{{ route('locale.update', $loc) }}"
                       @class([
                           'rounded px-1.5 py-1 transition',
                           'font-semibold text-main' => app()->getLocale() === $loc,
                           'text-muted hover:text-main' => app()->getLocale() !== $loc,
                       ])>{{ $localeLabels[$loc] ?? strtoupper($loc) }}</a>
                @endforeach
            </div>

            <button type="button" x-on:click="darkMode = !darkMode"
                    class="rounded-lg border border-border-muted px-2 py-1.5 text-xs font-semibold transition hover:opacity-80"
                    aria-label="Toggle dark mode">
                <span x-show="!darkMode">🌙</span>
                <span x-show="darkMode">☀️</span>
            </button>

            @auth
                @php($navUser = auth()->user())
                @if ($navUser?->hasAnyRole(['admin', 'superadmin']))
                    <a href="{{ url('/admin') }}" class="font-medium hover:underline">{{ __('navigation.admin') }}</a>
                @endif
                <span class="text-muted">{{ $navUser?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-sm border border-border-muted px-4 py-1.5 leading-normal transition hover:opacity-80">
                        {{ __('navigation.log_out') }}
                    </button>
                </form>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}"
                       class="rounded-sm border border-transparent px-4 py-1.5 leading-normal transition hover:border-border-muted">
                        {{ __('navigation.log_in') }}
                    </a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}"
                       class="rounded-sm border border-border-muted px-4 py-1.5 leading-normal transition hover:opacity-80">
                        {{ __('navigation.register') }}
                    </a>
                @endif
            @endauth
        </div>
    </div>
</nav>
