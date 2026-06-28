<x-layouts::guest>
    <h1 class="mb-1 text-lg font-medium">Welcome to {{ config('app.name', 'Platform2027') }}</h1>
    <p class="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
        Discover and connect with communities across South Africa.
    </p>

    <a
        href="{{ route('explore') }}"
        class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white transition hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
    >
        Explore Communities →
    </a>
</x-layouts::guest>
