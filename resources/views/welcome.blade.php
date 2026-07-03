<x-layouts::guest>
    <h1 class="mb-1 text-lg font-medium">{{ __('pages.welcome.heading', ['app' => config('app.name', 'Platform2027')]) }}</h1>
    <p class="mb-6 text-sm text-muted">
        {{ __('pages.welcome.tagline') }}
    </p>

    <a
        href="{{ route('explore') }}"
        class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-main px-4 py-2 text-sm font-medium text-surface transition hover:opacity-90"
    >
        {{ __('pages.welcome.explore') }}
    </a>
</x-layouts::guest>
