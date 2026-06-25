@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
<div class="p-6">
    <div class="flex items-start justify-between gap-4">
        <h2 class="text-xl font-bold text-gray-800">{{ $circle->name }}</h2>
        <button type="button" wire:click="closeModal" class="text-gray-400 transition hover:text-gray-600" aria-label="Close">
            ✕
        </button>
    </div>

    @if ($circle->description)
        <p class="mt-2 text-sm text-gray-600">{{ $circle->description }}</p>
    @endif

    {{-- Geographic location --}}
    <div class="mt-4 flex items-center gap-1.5 text-sm text-gray-500">
        <span aria-hidden="true">📍</span>
        <span>{{ $circle->locatable?->name ?? '—' }}</span>
    </div>

    {{-- Active services --}}
    @php($services = $circle->services->where('pivot.is_active', true))
    @if ($services->isNotEmpty())
        <div class="mt-5">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Services</h3>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($services as $service)
                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                        ⚙️ {{ $service->name }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-5 text-sm text-gray-500">0 members</div>

    <div class="mt-6 flex gap-3">
        <button type="button" wire:click="joinCommunity" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
            Join Community
        </button>
        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            Close
        </button>
    </div>
</div>
