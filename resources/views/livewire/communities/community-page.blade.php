@php
    /** @var \App\Models\Circles\Circle $circle */
    /** @var string $backUrl */
@endphp
{{-- Full height, 80% width, centred. Rendered in the neutral layouts.app
     shell (public — no auth chrome). --}}
<div class="mx-auto min-h-screen w-4/5 py-10">
    <a href="{{ $backUrl }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
        ← Explore Communities
    </a>

    <div class="mt-4 rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        {{-- Header: type icon + name --}}
        <div class="flex items-start gap-3">
            <span class="text-3xl" aria-hidden="true">{{ $this->icon() }}</span>
            <h1 class="text-2xl font-bold text-gray-800">{{ $circle->name }}</h1>
        </div>

        {{-- Geographic breadcrumb (temporary: single location line, as in the modal) --}}
        <div class="mt-3 flex items-center gap-1.5 text-sm text-gray-500">
            <span aria-hidden="true">📍</span>
            <span>{{ $circle->locatable?->name ?? '—' }}</span>
        </div>

        {{-- Description --}}
        @if ($circle->description)
            <p class="mt-4 text-gray-600">{{ $circle->description }}</p>
        @endif

        {{-- Active services --}}
        @php($services = $circle->services->where('pivot.is_active', true))
        @if ($services->isNotEmpty())
            <div class="mt-6">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Services</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($services as $service)
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                            ⚙️ {{ $service->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Member count placeholder --}}
        <div class="mt-6 text-sm text-gray-500">0 members</div>

        {{-- Join (placeholder) --}}
        <div class="mt-6">
            <button
                type="button"
                wire:click="joinCommunity"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                Join Community
            </button>
        </div>

        {{-- Future: type-specific panels (Organisation / Campaign / Course /
             ThemeCommunity / Event / LocationCommunity) slot in below here. --}}
    </div>
</div>
