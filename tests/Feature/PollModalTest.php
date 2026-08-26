<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Livewire\Communities\Services\Polls\PollGroupModal;
use App\Livewire\Communities\Services\Polls\PollModal;
use App\Livewire\Communities\Services\Polls\PollPage;
use App\Services\Circles\VotingService;
use App\Models\Circles\Circle;
use App\Models\Polls\PollGroup;
use App\Models\User;
use App\Models\Polls\Poll;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The poll modals: closing them, and the timezone boundary on their
 * datetime-local fields — the only such inputs in the application.
 */
class PollModalTest extends TestCase
{
    private Circle $circle;

    private ?User $creator = null;

    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        Schema::create('circles', function (Blueprint $table): void {
            $table->id();
            $table->string('circleable_type')->nullable();
            $table->unsignedBigInteger('circleable_id')->nullable();
            $table->string('locatable_type')->nullable();
            $table->unsignedBigInteger('locatable_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('path')->nullable();
            $table->integer('depth')->default(0);
            $table->string('name')->nullable();
            $table->json('description')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        (include database_path('migrations/2026_07_16_000001_create_circle_memberships_table.php'))->up();

        // The poll page renders its tag row, so the theme vocabulary and its
        // pivot must exist even though these tests never tag anything.
        Schema::create('themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });
        (include database_path('migrations/2026_07_17_000001_create_taggables_table.php'))->up();

        foreach (glob(database_path('migrations/2026_08_25_*.php')) as $migration) {
            (include $migration)->up();
        }

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

    public function test_the_cancel_button_actually_closes_the_modal(): void
    {
        // Regression: these used Alpine's $dispatch('closeModal'), which fires a
        // DOM event wire-elements never receives — Cancel did nothing at all on
        // both poll modals. The close must go through the component method.
        $this->actingAs($this->admin());

        Livewire::test(PollGroupModal::class, ['circleId' => $this->circle->id])
            ->assertSeeHtml('wire:click="closeModal"')
            ->call('closeModal')
            ->assertDispatched('closeModal');

        $group = $this->group('Alpha');

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->assertSeeHtml('wire:click="closeModal"')
            ->call('closeModal')
            ->assertDispatched('closeModal');
    }

    public function test_a_typed_opening_time_is_read_as_local_wall_clock_not_utc(): void
    {
        // The reported bug: an organiser in SAST set the poll to open at 12:21,
        // it was stored as 12:21 UTC, and at 12:31 local the poll still said
        // "not accepting responses" because 12:21 UTC was two hours away.
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Johannesburg']);
        Carbon::setTestNow(Carbon::parse('2026-08-26 10:31:00', 'UTC')); // 12:31 SAST

        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->set('opensAt', '2026-08-26T12:21')
            ->call('save')
            ->assertHasNoErrors();

        $poll = Poll::query()->latest('id')->firstOrFail();

        $this->assertSame('2026-08-26 10:21:00', $poll->opens_at->utc()->toDateTimeString());
        $this->assertTrue($poll->opens_at->isPast(), 'an opening time ten minutes ago must be in the past');

        Carbon::setTestNow();
    }

    public function test_the_poll_page_shows_its_timing_in_the_display_wall_clock(): void
    {
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Johannesburg']);

        $group = $this->group('Alpha');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->set('opensAt', '2026-08-26T12:21')
            ->set('closesAt', '2026-08-27T17:00')
            ->call('save');

        $poll = Poll::query()->latest('id')->firstOrFail();
        app(VotingService::class)->publish($poll);

        Livewire::test(PollPage::class, [
            'circle' => $this->circle,
            'pollGroup' => $group,
            'poll' => $poll->fresh(),
        ])
            // The wall clock that was typed, not its UTC equivalent (10:21).
            ->assertSee('26 Aug 2026, 12:21')
            ->assertSee('27 Aug 2026, 17:00')
            ->assertDontSee('10:21')
            ->assertSee('SAST');
    }

    public function test_choosing_a_ranked_ballot_offers_both_counting_methods(): void
    {
        // A new TallyMethod has to reach the compose form, not just the enum:
        // the select is driven by PollResponseShape::allowedTallyMethods().
        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        $modal = Livewire::test(PollModal::class, ['groupId' => $group->id]);

        // Single choice offers only plurality — Borda must not appear.
        $modal->assertSet('shape', PollResponseShape::SingleChoice->value)
            ->assertSee(__('polls.method.plurality'))
            ->assertDontSee(__('polls.method.borda_count'));

        $modal->set('shape', PollResponseShape::RankedChoice->value)
            ->assertSee(__('polls.method.instant_runoff'))
            ->assertSee(__('polls.method.borda_count'))
            // Switching shape must leave a legal method selected, not the
            // plurality carried over from before.
            ->assertSet('tallyMethod', TallyMethod::InstantRunoff->value);
    }

    public function test_a_borda_poll_can_be_created_and_keeps_its_method(): void
    {
        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('shape', PollResponseShape::RankedChoice->value)
            ->set('tallyMethod', TallyMethod::Borda->value)
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Rank them in order:')
            ->set('options', ['Ada', 'Grace', 'Bo'])
            ->call('save')
            ->assertHasNoErrors();

        $poll = Poll::query()->latest('id')->firstOrFail();

        $this->assertSame(TallyMethod::Borda, $poll->question->tally_method);
        $this->assertTrue($poll->question->hasLegalTallyMethod());
    }

    public function test_a_qualifying_date_is_shown_only_when_it_differs_from_the_opening(): void
    {
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Johannesburg']);
        Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'UTC'));

        $group = $this->group('Alpha');
        $this->actingAs($this->admin());
        $service = app(VotingService::class);

        // Cut-off set a week before the poll opens: it decides WHO may respond,
        // so it earns its own line.
        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->set('qualifyingDate', '2026-08-19T09:00')
            ->call('save');

        $withCutoff = Poll::query()->latest('id')->firstOrFail();
        $service->publish($withCutoff);

        Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $withCutoff->fresh(),
        ])
            ->assertSee(__('polls.timing.qualifying'))
            ->assertSee('19 Aug 2026, 09:00');

        // Left blank, publish() defaults it to the opening moment — repeating
        // the line above would be noise, so it is not shown.
        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Another poll')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->call('save');

        $noCutoff = Poll::query()->latest('id')->firstOrFail();
        $service->publish($noCutoff);

        Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $noCutoff->fresh(),
        ])->assertDontSee(__('polls.timing.qualifying'));

        Carbon::setTestNow();
    }

    public function test_a_poll_with_no_closing_time_says_so_rather_than_showing_nothing(): void
    {
        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Open-ended')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->call('save');

        $poll = Poll::query()->latest('id')->firstOrFail();
        app(VotingService::class)->publish($poll);

        Livewire::test(PollPage::class, [
            'circle' => $this->circle,
            'pollGroup' => $group,
            'poll' => $poll->fresh(),
        ])->assertSee(__('polls.timing.no_close'));
    }

    public function test_reopening_the_edit_form_shows_the_wall_clock_that_was_typed(): void
    {
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Johannesburg']);

        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->set('opensAt', '2026-08-26T12:21')
            ->set('closesAt', '2026-08-27T17:00')
            ->call('save');

        $poll = Poll::query()->latest('id')->firstOrFail();

        // Not 10:21 — the organiser must see what they entered.
        Livewire::test(PollModal::class, ['groupId' => $group->id, 'pollId' => $poll->id])
            ->assertSet('opensAt', '2026-08-26T12:21')
            ->assertSet('closesAt', '2026-08-27T17:00');
    }
}
