@php
    /** @var \App\Livewire\Auth\ForgotPassword $this */
@endphp
<div>
    <h1 class="mb-1 text-lg font-medium">Forgot your password?</h1>

    @if ($sent)
        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-[#1D2B1D] dark:text-green-300">
            If an account exists for that email, a password reset link has been sent.
            Please check your inbox.
        </div>

        <p class="mt-6 text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
            <a href="{{ route('login') }}" class="font-medium text-[#1b1b18] underline dark:text-[#EDEDEC]">Back to log in</a>
        </p>
    @else
        <p class="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
            Enter your email and we'll send you a link to reset your password.
        </p>

        <form wire:submit="sendLink" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border border-[#19140035] bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a]"
                />
                @error('email') <p class="mt-1 text-sm text-red-600 dark:text-[#FF4433]">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                class="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white transition hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
            >
                Email password reset link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
            <a href="{{ route('login') }}" class="font-medium text-[#1b1b18] underline dark:text-[#EDEDEC]">Back to log in</a>
        </p>
    @endif
</div>
