<!DOCTYPE html>
<!-- 1. Add Alpine logic to the html tag to watch for preferences and set the .dark class -->
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
      x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))"
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Platform2027' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<!-- 2. Replaced 'bg-gray-50' with 'bg-surface text-main' to use your Tailwind v4 variables -->
<body class="min-h-screen bg-surface text-main antialiased transition-colors duration-200">

<!-- 3. The Toggle Container Box (Placed at the top corner of the screen) -->
<div class="fixed top-4 right-4 z-50">
    <button @click="darkMode = !darkMode"
            class="px-3 py-1.5 border border-border-muted rounded-lg bg-surface text-main text-xs font-semibold cursor-pointer shadow-xs hover:opacity-85 transition-opacity">
        <span x-show="!darkMode">🌙 Dark</span>
        <span x-show="darkMode">☀️ Light</span>
    </button>
</div>

<!-- Your existing page injection point remains unchanged -->
{{ $slot }}

</body>
</html>
