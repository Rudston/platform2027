<?php

namespace App\Livewire\Communities;

use App\Contracts\Stewardship\CircleStewardshipQueue;
use App\Models\Circles\Circle;
use App\Models\ContentBlock;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Per-circle stewardship oversight — the layer ABOVE circle_admins. Shows one
 * health row per registered CircleStewardshipQueue (config/stewardship.php):
 * pending count, oldest-pending age, and a link into the Filament queue; rows
 * past the neglect threshold are highlighted so a dropped-ball circle_admin is
 * visible. Platform admins/superadmins ONLY — circle_admins get a 403 (this
 * page is about watching them, not for them).
 */
#[Layout('layouts.main')]
class CircleOversightPage extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        abort_unless((bool) auth()->user()?->hasAnyRole(['admin', 'superadmin']), 403);

        $this->circle = $circle;
    }

    /** Neglect threshold in days (admin-editable ContentBlock; default 7). */
    public function neglectDays(): int
    {
        return max(1, (int) ContentBlock::get('stewardship.neglect_days', '7'));
    }

    /**
     * One row per registered stewardship queue, in config order.
     *
     * @return array<int, array{label: string, count: int, oldest: ?Carbon, url: string, neglected: bool}>
     */
    #[Computed]
    public function queues(): array
    {
        $threshold = now()->subDays($this->neglectDays());

        $rows = [];

        foreach ((array) config('stewardship', []) as $class) {
            // Ignore anything in the registry that isn't actually a queue.
            if (! is_string($class) || ! is_subclass_of($class, CircleStewardshipQueue::class)) {
                continue;
            }

            $oldest = $class::oldestPendingAgeForCircle($this->circle);

            $rows[] = [
                'label' => $class::queueLabel(),
                'count' => $class::pendingCountForCircle($this->circle),
                'oldest' => $oldest,
                'url' => $class::filamentUrlForCircle($this->circle),
                'neglected' => $oldest !== null && $oldest->lessThan($threshold),
            ];
        }

        return $rows;
    }

    public function render()
    {
        return view('livewire.communities.circle-oversight-page')
            ->title(__('stewardship.oversight_title', ['circle' => $this->circle->name]));
    }
}
