<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Livewire\Communities\Services\Polls\PollGroupPage;
use App\Models\Circles\Circle;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\User;
use App\Services\Circles\VotingService;
use App\Support\Circles\CircleViewer;
use App\Support\Polls\Mark;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * US42: a visitor must not be able to read a Poll while it is running, so a
 * Circle's internal deliberation stays internal. Q11 settled the shape —
 * member-only while running, and only a CLOSED Poll's Result may be published
 * outside the Circle, and then only if the Organiser said so.
 *
 * The gate is on the POLL, never the Group: a Poll Group is organisational only
 * and never gates what is inside it (docs/adr/0003), so the group page filters
 * what it LISTS while each Poll answers for itself.
 */
class PollVisibilityTest extends TestCase
{
    /**
     * Who may read a Poll in each state: [visitor, member, manager]. The one
     * list, driving both the predicate matrix and the HTTP sweep below.
     */
    private const MATRIX = [
        'draft' => [false, false, true],
        'scheduled' => [false, true, true],
        'open' => [false, true, true],
        'closedPublished' => [true, true, true],
        'closedByClockPublished' => [true, true, true],
        'closedUnpublished' => [false, true, true],
        'cancelled' => [false, true, true],
    ];

    private Circle $circle;

    private PollGroup $group;

    private User $organiser;

    private VotingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        TestSchema::make()
            ->permissions()
            ->memberships()
            ->tagging()
            ->polls();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->service = app(VotingService::class);

        $circleId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'Ward 7', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->circle = Circle::findOrFail($circleId);

        $this->organiser = $this->member('Organiser');
        $this->group = $this->service->createGroup($this->circle, $this->organiser, ['name' => '2027 Budget']);
    }

    // ------------------------------------------------------------------ people

    private function user(string $name): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'password' => 'secret',
        ]);
    }

    private function member(string $name): User
    {
        $user = $this->user($name);

        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id,
            'user_id' => $user->id,
            'joined_at' => now()->subYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** A circle_admin who is deliberately NOT a member — the manager bypass. */
    private function manager(string $name): User
    {
        $user = $this->user($name);

        $roleId = DB::table('roles')->where('name', 'circle_admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId, 'model_type' => User::class,
            'model_id' => $user->id, 'circle_id' => $this->circle->id,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    // ------------------------------------------------------------------- polls

    private function draft(array $extra = []): Poll
    {
        return $this->service->createPoll($this->group, $this->organiser, array_merge([
            'title' => 'Choose a steward',
            'prompt' => 'Select ONE from:',
            'shape' => PollResponseShape::SingleChoice,
            'tally_method' => TallyMethod::Plurality,
            'options' => ['Ada', 'Grace'],
        ], $extra));
    }

    private function open(array $extra = []): Poll
    {
        return $this->service->publish($this->draft($extra));
    }

    private function scheduled(): Poll
    {
        return $this->service->publish($this->draft(['opens_at' => now()->addWeek()]));
    }

    /** Closed by the Organiser, with the Result published outside the Circle. */
    private function closedPublished(): Poll
    {
        return $this->service->conclude($this->open(['publish_results' => true]));
    }

    private function closedUnpublished(): Poll
    {
        return $this->service->conclude($this->open(['publish_results' => false]));
    }

    /**
     * Closed by the CLOCK, with the Result published but not yet frozen — the
     * ordinary way a poll ends (ADR-0001: status stays `published`, and
     * freezing happens on first read or hourly). The gate must not wait for
     * the freeze, or a published Result 404s until someone with access looks.
     */
    private function closedByClockPublished(): Poll
    {
        $poll = $this->open(['publish_results' => true]);
        $poll->update(['closes_at' => now()->subMinute()]);

        return $poll->fresh();
    }

    /** Cancelled, and asking to publish — a Cancelled Poll has no Result. */
    private function cancelled(): Poll
    {
        return $this->service->cancel($this->open(['publish_results' => true]));
    }

    private function pollUrl(Poll $poll): string
    {
        return route('communities.polls.poll', [
            'circle' => $this->circle,
            'pollGroup' => $this->group->slug,
            'poll' => $poll->getKey(),
        ]);
    }

    // ------------------------------------------------- the predicate, in full

    /**
     * The whole matrix in one place, asserted through the model so a failure
     * names the state and the viewer rather than a status code.
     */
    public function test_only_a_closed_poll_with_a_published_result_is_readable_from_outside(): void
    {
        $member = $this->member('Mo Member');
        $manager = $this->manager('Mia Manager');

        foreach (self::MATRIX as $state => [$visitor, $asMember, $asManager]) {
            $poll = $this->{$state}();

            $this->assertSame($visitor, $this->readableBy($poll, null), "visitor vs $state");
            $this->assertSame($asMember, $this->readableBy($poll, $member), "member vs $state");
            $this->assertSame($asManager, $this->readableBy($poll, $manager), "manager vs $state");
        }
    }

    private function readableBy(Poll $poll, ?User $user): bool
    {
        return $poll->isReadableBy(CircleViewer::for($this->circle, $user));
    }

    /**
     * isReadableBy must not touch the database: the group listing calls it once
     * per row, so a lookup inside it would be an N+1. This is why the viewer's
     * standing is passed IN rather than resolved there.
     *
     * (rosterIsVisibleTo is deliberately NOT held to this — it reads the
     * question relation, and it is only ever called for the one poll on a
     * detail page.)
     */
    public function test_the_read_gate_costs_no_query_so_a_listing_can_filter_in_memory(): void
    {
        $member = $this->member('Mo Member');
        $poll = $this->closedUnpublished();
        $viewer = CircleViewer::for($this->circle, $member);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($poll->isReadableBy($viewer));
        $this->assertFalse($poll->isReadableBy(CircleViewer::visitor()));

        $this->assertSame([], DB::getQueryLog(), 'the read gate reads columns already in memory');
    }

    // ------------------------------------------------------- the page gate (404)

    public function test_a_visitor_opening_a_running_poll_gets_a_404_as_a_draft_already_does(): void
    {
        $this->get($this->pollUrl($this->open()))->assertNotFound();
        $this->get($this->pollUrl($this->scheduled()))->assertNotFound();
        $this->get($this->pollUrl($this->draft()))->assertNotFound();
    }

    public function test_a_logged_in_non_member_is_a_visitor_too(): void
    {
        $this->actingAs($this->user('Outsider'))
            ->get($this->pollUrl($this->open()))
            ->assertNotFound();
    }

    public function test_a_closed_poll_whose_result_is_published_stays_reachable_to_a_non_member(): void
    {
        $this->get($this->pollUrl($this->closedPublished()))
            ->assertOk()
            ->assertSee('Choose a steward');
    }

    public function test_a_closed_poll_whose_result_is_not_published_is_member_only(): void
    {
        $poll = $this->closedUnpublished();

        $this->get($this->pollUrl($poll))->assertNotFound();

        $this->actingAs($this->member('Mo Member'))->get($this->pollUrl($poll))->assertOk();
    }

    /**
     * The same matrix through the real routes, so the page gate and the
     * predicate cannot disagree.
     */
    public function test_the_page_gate_matches_the_matrix_for_every_viewer(): void
    {
        $member = $this->member('Mo Member');
        $manager = $this->manager('Mia Manager');

        foreach (self::MATRIX as $state => [$visitor, $asMember, $asManager]) {
            $url = $this->pollUrl($this->{$state}());

            $this->assertPageStatus($url, null, $visitor, $state);
            $this->assertPageStatus($url, $member, $asMember, $state);
            $this->assertPageStatus($url, $manager, $asManager, $state);
        }
    }

    private function assertPageStatus(string $url, ?User $user, bool $readable, string $state): void
    {
        // A real logout, not just flushSession(): actingAs() in an earlier
        // iteration otherwise leaves the "visitor" case authenticated, and the
        // sweep would quietly assert the wrong viewer.
        $user ? $this->actingAs($user) : Auth::logout();

        $this->assertSame(
            $readable ? 200 : 404,
            $this->get($url)->getStatusCode(),
            ($user?->name ?? 'visitor')." vs $state",
        );
    }

    /**
     * The defect this pins: gating on resultIsPublic() (which needs a FROZEN
     * Result) would 404 a poll that just ran out its clock — for up to an hour,
     * until polls:freeze-results ran, and no visitor could heal it because the
     * page freezes AFTER the gate.
     */
    public function test_a_result_published_on_a_clock_closed_poll_is_readable_before_it_is_frozen(): void
    {
        $poll = $this->closedByClockPublished();

        $this->assertFalse($poll->hasResult(), 'nothing has frozen it yet');
        $this->assertTrue($poll->resultIsReleased());
        $this->assertFalse($poll->resultIsPublic(), 'released, but not yet frozen');

        $this->get($this->pollUrl($poll))->assertOk();

        $this->assertTrue($poll->fresh()->hasResult(), 'the visitor\'s read froze it');
    }

    // ------------------------------------------------------- the group listing

    public function test_a_group_page_does_not_list_polls_the_viewer_may_not_open(): void
    {
        $this->open(['title' => 'Running poll']);
        $this->closedUnpublished();
        $this->closedPublished();

        // The group page itself has no visibility — it lists what it may.
        Livewire::test(PollGroupPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group])
            ->assertOk()
            ->assertSee('Choose a steward')      // the closed, published one
            ->assertDontSee('Running poll');

        $this->actingAs($this->member('Mo Member'));

        Livewire::test(PollGroupPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group])
            ->assertSee('Running poll');
    }

    /**
     * The Roster is what lets a MEMBER trust a result; it is not part of the
     * Result, and only the Result may be published outside the Circle. So a
     * visitor reading a published Result never learns who responded.
     */
    public function test_a_visitor_reading_a_published_result_does_not_see_the_roster(): void
    {
        $respondent = $this->member('Rosa Respondent');
        $poll = $this->open(['publish_results' => true]);
        $option = $poll->question->options()->firstOrFail();
        $this->service->respond($poll, $respondent, [new Mark($option->getKey())]);
        $poll = $this->service->conclude($poll->fresh());

        $this->get($this->pollUrl($poll))->assertOk()->assertDontSee('Rosa Respondent');

        $this->actingAs($respondent)->get($this->pollUrl($poll))->assertSee('Rosa Respondent');
    }
}
