@php
    /** @var \App\Livewire\Auth\Login $this */
@endphp
<div>
    <h1 class="mb-1 text-lg font-medium">{{ __('auth.login.heading') }}</h1>
    <p class="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">{{ __('auth.login.subtitle') }}</p>

    <form wire:submit="login" class="space-y-4">
        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('auth.shared.email') }}</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autocomplete="email"
                class="mt-1 block w-full rounded-md border border-[#19140035] bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a]"
            />
            @error('email') <p class="mt-1 text-sm text-red-600 dark:text-[#FF4433]">{{ $message }}</p> @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('auth.shared.password') }}</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="mt-1 block w-full rounded-md border border-[#19140035] bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a]"
            />
            @error('password') <p class="mt-1 text-sm text-red-600 dark:text-[#FF4433]">{{ $message }}</p> @enderror
        </div>

        {{-- Remember me + forgot password --}}
        <div class="flex items-center justify-between">
            <label for="remember" class="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    id="remember"
                    wire:model="remember"
                    class="rounded border-[#19140035] text-indigo-600 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a]"
                />
                <span>{{ __('auth.login.remember') }}</span>
            </label>

            <a href="{{ route('password.request') }}" class="text-sm text-[#706f6c] underline dark:text-[#A1A09A]">
                {{ __('auth.login.forgot') }}
            </a>
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white transition hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
        >
            {{ __('auth.login.submit') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
        {{ __('auth.login.no_account') }}
        <a href="{{ route('register') }}" class="font-medium text-[#1b1b18] underline dark:text-[#EDEDEC]">{{ __('auth.login.register') }}</a>
    </p>
</div>
