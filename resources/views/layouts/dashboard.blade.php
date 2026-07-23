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

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-main antialiased transition-colors duration-200">
    {{-- Profile / admin / language live in the shared top bar, NOT the vertical nav. --}}
    @include('layouts.partials.top-nav')

    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:flex-row">
        {{-- Persistent left vertical nav (one entry per bookmarkable section) --}}
        @php
            $sections = [
                ['route' => 'dashboard.news',        'icon' => '📰', 'label' => __('dashboard.nav.news')],
                ['route' => 'dashboard.calendar',    'icon' => '📅', 'label' => __('dashboard.nav.calendar')],
                ['route' => 'dashboard.communities', 'icon' => '👥', 'label' => __('dashboard.nav.communities')],
                ['route' => 'dashboard.campaigns',   'icon' => '📣', 'label' => __('dashboard.nav.campaigns')],
                ['route' => 'dashboard.voting',      'icon' => '🗳️', 'label' => __('dashboard.nav.voting')],
            ];
        @endphp
        <aside class="shrink-0 sm:w-56">
            <nav class="flex gap-1 overflow-x-auto sm:flex-col" aria-label="{{ __('dashboard.nav.aria') }}">
                @foreach ($sections as $section)
                    @php($active = request()->routeIs($section['route']))
                    <a href="{{ route($section['route']) }}" wire:navigate
                       @class([
                           'flex items-center gap-3 whitespace-nowrap rounded-lg border-l-2 px-3 py-2 text-sm transition',
                           'border-indigo-600 bg-border-muted/40 font-semibold text-main' => $active,
                           'border-transparent text-muted hover:bg-border-muted/20 hover:text-main' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        <span aria-hidden="true">{{ $section['icon'] }}</span>
                        <span>{{ $section['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- Active section --}}
        <main class="min-w-0 flex-1">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
