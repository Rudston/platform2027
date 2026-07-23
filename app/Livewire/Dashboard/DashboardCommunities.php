<?php

namespace App\Livewire\Dashboard;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class DashboardCommunities extends Component
{
    /**
     * The viewer's active memberships, split into "admin" vs "member" and each
     * sorted by geographic path so same-region circles cluster. Bounded by how
     * many circles the user belongs to (never paginated).
     *
     * @return array{admin: Collection<int, array>, member: Collection<int, array>}
     */
    #[Computed]
    public function groups(): array
    {
        /** @var User $user */
        $user = auth()->user();

        // Circles the user is a circle_admin of — ONE query, drives the split.
        $adminCircleIds = Circle::administeredBy($user)->pluck('id')->all();

        $rows = CircleMembership::query()
            ->where('user_id', $user->id)
            ->active()
            ->with('circle')
            ->get()
            ->filter(fn (CircleMembership $m): bool => $m->circle !== null)
            ->map(fn (CircleMembership $m): array => [
                'circle' => $m->circle,
                // Existing materialised-path ancestor method (not a new recursion).
                'ancestors' => $m->circle->ancestors(),
                'isAdmin' => in_array($m->circle->id, $adminCircleIds, true),
            ])
            ->sortBy(fn (array $r): string => (string) $r['circle']->path)
            ->values();

        return [
            'admin' => $rows->where('isAdmin', true)->values(),
            'member' => $rows->where('isAdmin', false)->values(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.communities')->title(__('dashboard.communities.heading'));
    }
}
