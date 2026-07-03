<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
      x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))"
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Platform2027') }}</title>

    {{-- Fonts (consistent with the rest of the app) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    {{-- Tailwind 4 + JS via Vite. Livewire 4 auto-injects its own assets. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-main antialiased transition-colors duration-200">
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

            {{-- Right: theme toggle + auth actions --}}
            <div class="flex items-center gap-3">
                <button type="button" @click="darkMode = !darkMode"
                        class="rounded-lg border border-border-muted px-2 py-1.5 text-xs font-semibold transition hover:opacity-80"
                        aria-label="Toggle dark mode">
                    <span x-show="!darkMode">🌙</span>
                    <span x-show="darkMode">☀️</span>
                </button>

                @auth
                    <span class="text-muted">{{ auth()->user()?->name }}</span>
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

    {{-- Full-width; page content supplies its own inner container/width. --}}
    <main>
        {{ $slot }}
    </main>
</body>
</html>
