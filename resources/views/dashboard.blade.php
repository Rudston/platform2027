<x-layouts::authenticated>
    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ __('pages.dashboard.greeting', ['name' => auth()->user()->name]) }}
            </h1>

            <p class="mt-2 text-sm text-muted">
                {{ __('pages.dashboard.logged_in', ['email' => auth()->user()->email]) }}
            </p>

            <p class="mt-1 text-sm text-muted">
                {{ __('pages.dashboard.role', ['roles' => auth()->user()->getRoleNames()->join(', ') ?: '—']) }}
            </p>

            <a
                href="{{ route('explore') }}"
                class="mt-6 inline-flex items-center gap-1.5 rounded-md bg-main px-4 py-2 text-sm font-medium text-surface transition hover:opacity-90"
            >
                {{ __('pages.dashboard.explore') }}
            </a>
        </div>
    </div>
</x-layouts::authenticated>
