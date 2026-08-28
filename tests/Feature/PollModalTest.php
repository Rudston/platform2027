<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\RatingScalePresentation;
use App\Enums\Polls\TallyMethod;
use App\Livewire\Communities\Services\Polls\PollGroupModal;
use App\Livewire\Communities\Services\Polls\PollModal;
use App\Livewire\Communities\Services\Polls\PollPage;
use App\Services\Circles\VotingService;
use App\Models\Circles\Circle;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollRatingScale;
use App\Models\Polls\PollRatingScalePoint;
use App\Models\User;
use App\Models\Polls\Poll;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestSchema;
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

        TestSchema::make()
            ->permissions()
            ->memberships()
            ->tagging()
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

    /**
     * slugFor may legitimately return NOTHING — a name in a non-Latin script,
     * or one made only of punctuation — and the group route binds by slug, so
     * storing an empty one would make the group unreachable and throw while
     * rendering the whole Polls tab. The modal refuses it and says so, which is
     * where the person can actually fix it (.scratch/polls/issues/13).
     */
    public function test_a_group_name_that_yields_no_slug_is_refused_with_a_message(): void
    {
        $this->actingAs($this->admin());

        foreach (['中文名字', '???'] as $name) {
            Livewire::test(PollGroupModal::class, ['circleId' => $this->circle->id])
                ->set('name', $name)
                ->call('save')
                ->assertHasErrors('slug')
                ->assertNotDispatched('poll-groups-changed');
        }

        $this->assertSame(0, $this->circle->pollGroups()->count());

        // The escape hatch: supply the URL slug yourself and the name is free.
        Livewire::test(PollGroupModal::class, ['circleId' => $this->circle->id])
            ->set('name', '中文名字')
            ->set('slug', 'budget-talk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('poll_groups', [
            'circle_id' => $this->circle->id,
            'slug' => 'budget-talk',
        ]);
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

    public function test_a_rating_poll_renders_its_scale_and_records_a_response(): void
    {
        $scale = PollRatingScale::create(['name' => '5-point agreement']);
        $points = [];

        foreach ([['Strongly disagree', 1], ['Neutral', 3], ['Strongly agree', 5]] as $position => [$label, $value]) {
            $points[$value] = PollRatingScalePoint::create([
                'poll_rating_scale_id' => $scale->id,
                'label' => $label, 'value' => $value, 'position' => $position,
            ])->id;
        }

        $group = $this->group('Alpha');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('shape', PollResponseShape::Rating->value)
            ->set('ratingScaleId', $scale->id)
            ->set('title', 'Rate the proposals')
            ->set('prompt', 'Score each one:')
            ->set('options', ['Road repairs', 'Water pressure'])
            ->call('save')
            ->assertHasNoErrors();

        $poll = Poll::query()->latest('id')->firstOrFail();
        app(VotingService::class)->publish($poll);

        // The admin must be in the electorate to answer their own poll.
        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id, 'user_id' => $admin->id,
            'joined_at' => now()->subYear(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $poll->fresh()->electorate()->syncWithoutDetaching([$admin->id]);

        $options = $poll->question->options()->pluck('id', 'label')->all();

        $page = Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $poll->fresh(),
        ]);

        // Every scale point must be offered, or the ballot cannot be answered.
        $page->assertSee('Strongly disagree')->assertSee('Neutral')->assertSee('Strongly agree')
            ->assertSee('Road repairs')->assertSee('Water pressure');

        // Scoring only one option is refused — a rating response must score all.
        $page->set('scores', [$options['Road repairs'] => $points[5]])
            ->call('submit')
            ->assertHasErrors('response');

        $page->set('scores', [
            $options['Road repairs'] => $points[5],
            $options['Water pressure'] => $points[1],
        ])->call('submit')->assertHasNoErrors('response');

        // The POINT is stored; its VALUE is what a tally averages.
        $item = $poll->fresh()->question->responses()->firstOrFail()
            ->items()->where('poll_option_id', $options['Road repairs'])->firstOrFail();

        $this->assertSame($points[5], (int) $item->rating_scale_point_id);
        $this->assertNull($item->rank);
        $this->assertSame(5, $item->ratingScalePoint->value);

        // Concluded: the result panel shows MEANS, and says so — points that do
        // not add up to the turnout look like an error without the note.
        app(VotingService::class)->conclude($poll->fresh());

        Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $poll->fresh(),
        ])
            ->assertSee(__('polls.result.average_note'))
            ->assertSee('Road repairs')
            ->assertSee(__('polls.result.winner'));

        // Read through frozenResult(), which is what the page uses: the RAW
        // column holds what json_encode produced, and 5.0 encodes as `5`.
        // PollResult::fromArray casts by method, restoring the float.
        $frozen = app(VotingService::class)->frozenResult($poll->fresh());

        $this->assertSame(5.0, $frozen->totals[$options['Road repairs']]);
        $this->assertSame(1.0, $frozen->totals[$options['Water pressure']]);
        $this->assertSame($options['Road repairs'], $frozen->winnerOptionId);
        $this->assertSame(TallyMethod::AverageScore, $frozen->method);
    }

    public function test_a_star_scale_renders_stars_while_other_scales_keep_the_dropdown(): void
    {
        // Which widget is used comes from the scale's own declaration, never
        // from its name (admin-curated) or its shape — the agreement scale here
        // is also five points, so shape cannot tell them apart.
        $stars = PollRatingScale::create(['name' => '1–5 stars', 'presentation' => RatingScalePresentation::Stars]);
        $words = PollRatingScale::create(['name' => '5-point agreement', 'presentation' => RatingScalePresentation::Select]);

        foreach ([$stars, $words] as $scale) {
            foreach ([1, 2, 3, 4, 5] as $position => $value) {
                PollRatingScalePoint::create([
                    'poll_rating_scale_id' => $scale->id,
                    'label' => $scale->is($stars) ? "{$value} star" : "Level {$value}",
                    'value' => $value, 'position' => $position,
                ]);
            }
        }

        $group = $this->group('Alpha');
        $admin = $this->admin();
        $this->actingAs($admin);
        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id, 'user_id' => $admin->id,
            'joined_at' => now()->subYear(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $make = function (PollRatingScale $scale) use ($group, $admin) {
            Livewire::test(PollModal::class, ['groupId' => $group->id])
                ->set('shape', PollResponseShape::Rating->value)
                ->set('ratingScaleId', $scale->id)
                ->set('title', 'Rate '.$scale->name)
                ->set('prompt', 'Score each one:')
                ->set('options', ['Road repairs', 'Water pressure'])
                ->call('save');

            $poll = Poll::query()->latest('id')->firstOrFail();
            app(VotingService::class)->publish($poll);
            $poll->fresh()->electorate()->syncWithoutDetaching([$admin->id]);

            return $poll->fresh();
        };

        // Stars: a radiogroup of buttons writing straight to the Livewire
        // property, no <select> in sight.
        Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $make($stars),
        ])
            ->assertSeeHtml('role="radiogroup"')
            ->assertSeeHtml('$wire.set(')
            ->assertDontSeeHtml('<select wire:model="scores.');

        // Words: unchanged, because a five-level agreement scale is unreadable
        // as five identical stars.
        Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $make($words),
        ])
            ->assertSeeHtml('<select wire:model="scores.')
            ->assertDontSeeHtml('role="radiogroup"');
    }

    public function test_a_scale_defaults_to_the_dropdown(): void
    {
        // The column defaults to 'select', so every scale that predates this
        // feature keeps the widget it had.
        $scale = PollRatingScale::create(['name' => 'Unspecified']);

        $this->assertSame(RatingScalePresentation::Select, $scale->fresh()->presentation);
        $this->assertFalse($scale->fresh()->rendersAsStars());
    }

    public function test_an_open_poll_shows_the_running_count_never_a_stale_frozen_result(): void
    {
        // Belt and braces for the reported bug: even if a stale Result somehow
        // survives on an OPEN poll, the page must not present it as the
        // outcome of a poll people are still voting in.
        $group = $this->group('Alpha');
        $admin = $this->admin();
        $this->actingAs($admin);
        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id, 'user_id' => $admin->id,
            'joined_at' => now()->subYear(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->call('save');

        $poll = Poll::query()->latest('id')->firstOrFail();
        $service = app(VotingService::class);
        $service->publish($poll);
        $poll = $poll->fresh();
        $poll->electorate()->syncWithoutDetaching([$admin->id]);

        $options = $poll->question->options()->pluck('id', 'label')->all();
        $service->respond($poll->fresh(), $admin, [new \App\Support\Polls\Mark($options['Ada'])]);

        // Forcibly plant the stale figure the bug produced.
        $poll->fresh()->update([
            'result' => ['method' => 'plurality', 'totals' => [], 'turnout' => 0,
                'winner_option_id' => null, 'tied_option_ids' => [], 'rounds' => null],
            'result_frozen_at' => now(),
        ]);

        $page = Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $group, 'poll' => $poll->fresh(),
        ]);

        $this->assertSame(1, $page->instance()->result()->turnout, 'the live count, not the frozen zero');
        $page->assertDontSee(__('polls.result.no_responses'));
    }

    public function test_the_form_rejects_a_closing_time_before_the_opening(): void
    {
        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->set('opensAt', '2026-09-01T10:00')
            ->set('closesAt', '2026-08-31T10:00')
            ->call('save')
            // On the offending FIELD, not as a general form error on the title.
            ->assertHasErrors('closesAt');

        $this->assertSame(0, Poll::query()->count(), 'nothing should have been created');
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

    /**
     * A datetime-local field cannot express seconds, so the form receives the
     * stored Qualifying Date truncated to the minute and sends that copy back
     * on every save. Taken literally, saving an unrelated edit would move the
     * stated date up to 59 seconds earlier and re-resolve the Electorate for no
     * reason — the very disagreement .scratch/polls/issues/11 is about.
     */
    public function test_an_unrelated_edit_moves_neither_the_qualifying_date_nor_the_electorate(): void
    {
        // A stored date with SECONDS on it, which the form cannot round-trip.
        Carbon::setTestNow(Carbon::parse('2026-08-26 10:32:47', 'UTC'));

        $group = $this->group('Alpha');
        $this->actingAs($this->admin());

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->call('save');

        $poll = app(VotingService::class)->publish(Poll::query()->latest('id')->firstOrFail());

        $this->assertSame('2026-08-26 10:32:47', $poll->qualifying_date->toDateTimeString());
        $electorate = $poll->electorate()->pluck('users.id')->sort()->values()->all();

        // Someone joins after publication: they must NOT appear.
        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id,
            'user_id' => User::forceCreate([
                'name' => 'Late', 'email' => 'late@example.test', 'password' => 'x',
            ])->id,
            'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PollModal::class, ['groupId' => $group->id, 'pollId' => $poll->id])
            ->set('title', 'Choose a steward, renamed')
            ->call('save')
            ->assertHasNoErrors();

        $after = $poll->fresh();

        $this->assertSame('Choose a steward, renamed', $after->title);
        $this->assertSame(
            '2026-08-26 10:32:47',
            $after->qualifying_date->toDateTimeString(),
            'the seconds the form could not carry are not an edit',
        );
        $this->assertSame(
            $electorate,
            $after->electorate()->pluck('users.id')->sort()->values()->all(),
            'and the Electorate was not re-resolved',
        );
    }

    /**
     * Emptying the cut-off on a PUBLISHED poll is refused, because its stored
     * Electorate was resolved from that date (.scratch/polls/issues/11). The
     * refusal has to reach the organiser as a form error rather than a 500 —
     * the modal catches the service's exceptions and surfaces the message.
     *
     * It lands on `title`, which is where every service refusal lands today;
     * routing each one to its own field is issue 16's business.
     */
    public function test_emptying_the_cut_off_on_a_published_poll_is_refused_on_the_form(): void
    {
        $group = $this->group('Alpha');
        $this->actingAs($this->admin());
        $service = app(VotingService::class);

        Livewire::test(PollModal::class, ['groupId' => $group->id])
            ->set('title', 'Choose a steward')
            ->set('prompt', 'Select ONE from:')
            ->set('options', ['Ada', 'Grace'])
            ->set('qualifyingDate', '2026-08-19T09:00')
            ->call('save');

        $poll = $service->publish(Poll::query()->latest('id')->firstOrFail());
        $electorate = $poll->electorate()->pluck('users.id')->all();

        Livewire::test(PollModal::class, ['groupId' => $group->id, 'pollId' => $poll->id])
            ->set('qualifyingDate', '')
            ->call('save')
            ->assertHasErrors('title');

        $this->assertSame(
            $electorate,
            $poll->fresh()->electorate()->pluck('users.id')->all(),
            'a refused amendment leaves the Electorate alone',
        );
    }
}
