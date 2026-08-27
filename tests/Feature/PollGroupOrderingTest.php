<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Livewire\Communities\Services\Polls\PollServiceContainer;
use App\Models\Circles\Circle;
use App\Models\Polls\PollGroup;
use App\Models\User;
use App\Services\Circles\VotingService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * Reordering poll groups from the Polls tab. The interesting rule is that a
 * move swaps with the neighbour AS DISPLAYED, not as stored — otherwise a move
 * past a filtered-out row looks like nothing happened.
 */
class PollGroupOrderingTest extends TestCase
{
    private Circle $circle;

    private ?User $creator = null;

    protected function setUp(): void
    {
        parent::setUp();

        TestSchema::make()
            ->permissions()
            ->memberships()
            ->polls();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $circleId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'Ward 7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->circle = Circle::findOrFail($circleId);
    }

    private function admin(): User
    {
        $user = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']);

        $roleId = DB::table('roles')->where('name', 'circle_admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId, 'model_type' => User::class,
            'model_id' => $user->id, 'circle_id' => $this->circle->id,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * Groups are made through the service, not the model: createGroup() derives
     * the slug, and the Polls tab links every card by slug — a slugless group
     * would throw while building the route and take the whole tab with it.
     */
    private function group(string $name, int $position = 0, bool $archived = false): PollGroup
    {
        $group = app(VotingService::class)->createGroup(
            $this->circle,
            $this->creator ??= User::forceCreate([
                'name' => 'Creator', 'email' => 'creator@example.test', 'password' => 'x',
            ]),
            ['name' => $name, 'position' => $position],
        );

        if ($archived) {
            $group->update(['archived_at' => now()]);
        }

        return $group->fresh();
    }

    /** @return list<string> */
    private function orderedNames(): array
    {
        return $this->circle->pollGroups()->orderBy('position')->orderBy('name')->pluck('name')->all();
    }

    public function test_a_group_can_be_moved_down_and_the_order_persists(): void
    {
        $this->group('Alpha');
        $beta = $this->group('Beta');
        $this->group('Gamma');
        $this->actingAs($this->admin());

        // All start at position 0, so the display order falls through to name.
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->orderedNames());

        Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->call('moveDown', $beta->id);

        $this->assertSame(['Alpha', 'Gamma', 'Beta'], $this->orderedNames());
    }

    public function test_moving_up_past_the_first_position_does_nothing(): void
    {
        $alpha = $this->group('Alpha');
        $this->group('Beta');
        $this->actingAs($this->admin());

        Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->call('moveUp', $alpha->id);

        $this->assertSame(['Alpha', 'Beta'], $this->orderedNames());
    }

    public function test_moving_down_past_the_last_position_does_nothing(): void
    {
        $this->group('Alpha');
        $beta = $this->group('Beta');
        $this->actingAs($this->admin());

        Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->call('moveDown', $beta->id);

        $this->assertSame(['Alpha', 'Beta'], $this->orderedNames());
    }

    public function test_a_move_swaps_the_displayed_neighbour_skipping_filtered_out_groups(): void
    {
        // Stored order: Alpha, Archived, Beta. With the default active-only
        // filter the admin sees Alpha then Beta, so moving Beta up must put it
        // above ALPHA — not merely above the hidden archived row, which would
        // look like the click did nothing.
        $this->group('Alpha', 0);
        $this->group('Archived', 1, archived: true);
        $beta = $this->group('Beta', 2);
        $this->actingAs($this->admin());

        Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->assertSet('statusFilter', 'active')
            ->call('moveUp', $beta->id);

        $active = $this->circle->pollGroups()->whereNull('archived_at')
            ->orderBy('position')->pluck('name')->all();

        $this->assertSame(['Beta', 'Alpha'], $active);
    }

    public function test_a_non_manager_cannot_reorder(): void
    {
        $this->group('Alpha');
        $beta = $this->group('Beta');

        $member = User::forceCreate(['name' => 'Member', 'email' => 'member@example.test', 'password' => 'x']);
        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id, 'user_id' => $member->id,
            'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($member);

        Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->call('moveUp', $beta->id);

        $this->assertSame(['Alpha', 'Beta'], $this->orderedNames());
    }

    public function test_a_group_from_another_circle_cannot_be_moved_into_this_one(): void
    {
        $this->group('Alpha');
        $otherCircleId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'Ward 8', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreign = PollGroup::create([
            'circle_id' => $otherCircleId, 'name' => 'Elsewhere', 'slug' => 'elsewhere', 'position' => 5,
        ]);
        $this->actingAs($this->admin());

        Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->call('moveDown', $foreign->id);

        $this->assertSame(5, $foreign->fresh()->position);
        $this->assertSame(['Alpha'], $this->orderedNames());
    }
}
