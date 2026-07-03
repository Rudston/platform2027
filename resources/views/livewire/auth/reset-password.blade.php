@php
    /** @var \App\Livewire\Auth\ResetPassword $this */
@endphp
<div>
    <h1 class="mb-1 text-lg font-medium">{{ __('auth.reset.heading') }}</h1>
    <p class="mb-6 text-sm text-muted">{{ __('auth.reset.subtitle') }}</p>

    <form wire:submit="resetPassword" class="space-y-4">
        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('auth.shared.email') }}</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autocomplete="email"
                class="mt-1 block w-full rounded-md border border-border-muted bg-surface px-3 py-2 text-sm text-main shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- New password --}}
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('auth.reset.new_password') }}</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="new-password"
                class="mt-1 block w-full rounded-md border border-border-muted bg-surface px-3 py-2 text-sm text-main shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Confirm new password --}}
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">{{ __('auth.reset.confirm_password') }}</label>
            <input
                type="password"
                id="password_confirmation"
                wire:model="password_confirmation"
                autocomplete="new-password"
                class="mt-1 block w-full rounded-md border border-border-muted bg-surface px-3 py-2 text-sm text-main shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            @error('password_confirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-main px-4 py-2 text-sm font-medium text-surface transition hover:opacity-90"
        >
            {{ __('auth.reset.submit') }}
        </button>
    </form>
</div>
