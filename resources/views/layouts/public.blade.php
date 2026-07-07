<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Platform2027'))</title>

    {{-- Apply saved/system theme before paint. These public pages don't load
         Livewire/Alpine, so we set the .dark class directly (no FOUC). --}}
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const dark = stored === 'dark'
                || (! stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (dark) document.documentElement.classList.add('dark');
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-main antialiased transition-colors duration-200">
    {{-- No platform navigation: these pages are for external recipients. --}}
    <main class="mx-auto flex min-h-screen max-w-xl items-center justify-center px-4 py-10">
        <div class="w-full">
            @yield('content')
        </div>
    </main>
</body>
</html>
