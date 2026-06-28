<x-layouts::authenticated>
    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4">
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome, {{ auth()->user()->name }}
            </h1>

            <p class="mt-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                You are logged in as {{ auth()->user()->email }}
            </p>

            <p class="mt-1 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Your role: {{ auth()->user()->getRoleNames()->join(', ') ?: '—' }}
            </p>

            <a
                href="{{ route('explore') }}"
                class="mt-6 inline-flex items-center gap-1.5 rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white transition hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
            >
                Explore Communities →
            </a>
        </div>
    </div>
</x-layouts::authenticated>
