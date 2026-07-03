<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply the .dark class before paint so semantic tokens flip with the
         stored/system theme (no toggle button on this shell — it follows
         the preference set elsewhere). --}}
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

    <title>{{ $title ?? config('app.name', 'Platform2027') }}</title>

    {{-- Fonts (consistent with the welcome page) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    {{-- Tailwind 4 + JS via Vite. Livewire 4 auto-injects its own assets,
         so @livewireStyles/@livewireScripts are intentionally omitted. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-main antialiased transition-colors duration-200">
    <header class="w-full">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 text-sm">
            <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name', 'Platform2027') }}</a>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="rounded-sm border border-border-muted px-4 py-1.5 leading-normal transition hover:opacity-80">
                        {{ __('navigation.dashboard') }}
                    </a>
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
        </nav>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-lg border border-border-muted bg-surface p-6 shadow-sm">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
