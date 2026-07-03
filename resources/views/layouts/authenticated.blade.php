<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply the .dark class before paint so semantic tokens flip with the
         stored/system theme. --}}
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
    <nav class="border-b border-border-muted bg-surface">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 text-sm">
            {{-- Left: app name --}}
            <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name', 'Platform2027') }}</a>

            {{-- Centre: navigation --}}
            <div class="flex items-center gap-6">
                <a href="{{ url('/') }}" class="hover:underline">{{ __('navigation.home') }}</a>
                <a href="{{ route('explore') }}" class="hover:underline">{{ __('navigation.explore_communities') }}</a>
            </div>

            {{-- Right: user + logout --}}
            <div class="flex items-center gap-4">
                <span class="text-muted">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-sm border border-border-muted px-4 py-1.5 leading-normal transition hover:opacity-80">
                        {{ __('navigation.log_out') }}
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4">
        {{ $slot }}
    </main>
</body>
</html>
