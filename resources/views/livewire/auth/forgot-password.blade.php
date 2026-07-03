@php
    /** @var \App\Livewire\Auth\ForgotPassword $this */
@endphp
<div>
    <h1 class="mb-1 text-lg font-medium">{{ __('auth.forgot.heading') }}</h1>

    @if ($sent)
        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
            {{ __('auth.forgot.sent') }}
        </div>

        <p class="mt-6 text-center text-sm text-muted">
            <a href="{{ route('login') }}" class="font-medium text-main underline">{{ __('auth.shared.back_to_login') }}</a>
        </p>
    @else
        <p class="mb-6 text-sm text-muted">
            {{ __('auth.forgot.intro') }}
        </p>

        <form wire:submit="sendLink" class="space-y-4">
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

            <button
                type="submit"
                class="w-full rounded-md bg-main px-4 py-2 text-sm font-medium text-surface transition hover:opacity-90"
            >
                {{ __('auth.forgot.submit') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            <a href="{{ route('login') }}" class="font-medium text-main underline">{{ __('auth.shared.back_to_login') }}</a>
        </p>
    @endif
</div>
