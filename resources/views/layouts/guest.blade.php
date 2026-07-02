<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Platform2027') }}</title>

    {{-- Fonts (consistent with the welcome page) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    {{-- Tailwind 4 + JS via Vite. Livewire 4 auto-injects its own assets,
         so @livewireStyles/@livewireScripts are intentionally omitted. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
    <header class="w-full">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 text-sm">
            <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name', 'Platform2027') }}</a>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="rounded-sm border border-[#19140035] px-4 py-1.5 leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]">
                        {{ __('navigation.dashboard') }}
                    </a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}"
                           class="rounded-sm border border-transparent px-4 py-1.5 leading-normal hover:border-[#19140035] dark:hover:border-[#3E3E3A]">
                            {{ __('navigation.log_in') }}
                        </a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="rounded-sm border border-[#19140035] px-4 py-1.5 leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]">
                            {{ __('navigation.register') }}
                        </a>
                    @endif
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
