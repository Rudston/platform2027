<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

class CounterTest extends TestCase
{
    public function test_counter_renders(): void
    {
        Livewire::test('counter')
            ->assertSee('Livewire Counter')
            ->assertSet('count', 0);
    }

    public function test_counter_increments(): void
    {
        Livewire::test('counter')
            ->call('increment')
            ->assertSet('count', 1)
            ->call('increment')
            ->assertSet('count', 2);
    }

    public function test_counter_decrements(): void
    {
        Livewire::test('counter')
            ->call('decrement')
            ->assertSet('count', -1);
    }

    public function test_counter_route_responds(): void
    {
        $this->get('/counter')
            ->assertOk()
            ->assertSee('Livewire Counter');
    }
}
