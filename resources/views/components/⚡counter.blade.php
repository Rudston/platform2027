<?php

use Livewire\Component;

new class extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }
};
?>

<div class="flex flex-col items-center gap-6 py-16">
    <h1 class="text-2xl font-semibold text-main">Livewire Counter</h1>

    <div class="text-6xl font-bold tabular-nums text-indigo-600">{{ $count }}</div>

    <div class="flex gap-3">
        <button
            wire:click="decrement"
            class="rounded-lg bg-border-muted px-5 py-2 text-lg font-medium text-main transition hover:opacity-90"
        >
            &minus;
        </button>
        <button
            wire:click="increment"
            class="rounded-lg bg-indigo-600 px-5 py-2 text-lg font-medium text-white transition hover:bg-indigo-700"
        >
            +
        </button>
    </div>
</div>
