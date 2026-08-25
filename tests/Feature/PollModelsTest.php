<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\PollStatus;
use App\Enums\Polls\TallyMethod;
use App\Models\Circles\Circle;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollOption;
use App\Models\Polls\PollQuestion;
use App\Models\Polls\PollResponse;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The model-predicate seam: derived lifecycle, entitlement, the roster gate and
 * the conclude/cancel authority rule. Writes here go through the models
 * directly — the service is exercised separately in tests/Services.
 */
class PollModelsTest extends TestCase
{
    private Circle $circle;

    private PollGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        // The full migration set cannot run on sqlite (a demography backfill
        // references a `countries` table no migration creates), so build only
        // the tables these tests need.
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

        foreach (glob(database_path('migrations/2026_08_25_*.php')) as $migration) {
            (include $migration)->up();
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        // Insert the circle via the query builder: Circle::booted() attaches
        // default services and auto-tags, which would demand tables these
        // tests do not build (the same reason ForumGroupsTest does this).
        $circleId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'Ward 7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->circle = Circle::findOrFail($circleId);
        $this->group = PollGroup::create([
            'circle_id' => $this->circle->id,
            'name' => '2027 Budget',
            'position' => 0,
        ]);
    }

    private function user(string $name): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'password' => 'secret',
        ]);
    }

    private function joinCircle(User $user): void
    {
        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id,
            'user_id' => $user->id,
            'joined_at' => now()->subYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function leaveCircle(User $user): void
    {
        DB::table('circle_memberships')
            ->where('circle_id', $this->circle->id)
            ->where('user_id', $user->id)
            ->update(['left_at' => now()]);
    }

    private function grantCircleAdmin(User $user): void
    {
        $roleId = DB::table('roles')->where('name', 'circle_admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->id,
            'circle_id' => $this->circle->id,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function makePoll(array $attributes = [], ?User $organiser = null): Poll
    {
        $poll = Poll::create(array_merge([
            'circle_id' => $this->circle->id,
            'poll_group_id' => $this->group->id,
            'created_by' => $organiser?->id,
            'title' => 'Choose a steward',
            'eligibility' => 'private',
            'status' => PollStatus::Draft->value,
        ], $attributes));

        $question = PollQuestion::create([
            'poll_id' => $poll->id,
            'position' => 0,
            'text' => 'Select ONE from:',
            'type' => PollResponseShape::SingleChoice->value,
            'tally_method' => TallyMethod::Plurality->value,
        ]);

        PollOption::create(['poll_question_id' => $question->id, 'label' => 'Ada', 'position' => 0]);
        PollOption::create(['poll_question_id' => $question->id, 'label' => 'Grace', 'position' => 1]);

        return $poll->fresh();
    }

    private function respond(Poll $poll, User $user): void
    {
        PollResponse::create([
            'poll_question_id' => $poll->question->id,
            'user_id' => $user->id,
            'submitted_at' => now(),
        ]);
    }

    // -------------------------------------------------- derived lifecycle

    public function test_a_draft_is_neither_open_nor_closed(): void
    {
        $poll = $this->makePoll();

        $this->assertTrue($poll->isDraft());
        $this->assertFalse($poll->isOpen());
        $this->assertFalse($poll->isClosed());
        $this->assertFalse($poll->isScheduled());
        $this->assertSame('draft', $poll->stateKey());
    }

    public function test_a_published_poll_with_a_future_opening_is_scheduled(): void
    {
        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'opens_at' => now()->addDay()]);

        $this->assertTrue($poll->isScheduled());
        $this->assertFalse($poll->isOpen());
        $this->assertSame('scheduled', $poll->stateKey());
    }

    public function test_a_poll_past_its_closing_time_reads_closed_while_its_status_stays_published(): void
    {
        // The ADR-0001 rule: the clock owns WHETHER a poll accepts responses,
        // the status owns WHY it stopped early. A poll that simply ran out of
        // time stopped for no exceptional reason, so nothing is recorded — and
        // no scheduled job is needed to flip anything.
        $poll = $this->makePoll([
            'status' => PollStatus::Published->value,
            'opens_at' => now()->subDays(3),
            'closes_at' => now()->subDay(),
        ]);

        $this->assertTrue($poll->isClosed());
        $this->assertFalse($poll->isOpen());
        $this->assertSame(PollStatus::Published, $poll->status);
        $this->assertSame('closed', $poll->stateKey());
    }

    public function test_concluded_and_cancelled_polls_are_closed(): void
    {
        $concluded = $this->makePoll(['status' => PollStatus::Concluded->value, 'closes_at' => now()]);
        $cancelled = $this->makePoll(['status' => PollStatus::Cancelled->value, 'closes_at' => now()]);

        $this->assertTrue($concluded->isClosed());
        $this->assertSame('concluded', $concluded->stateKey());

        $this->assertTrue($cancelled->isClosed());
        $this->assertTrue($cancelled->isCancelled());
        $this->assertSame('cancelled', $cancelled->stateKey());
    }

    // ------------------------------------------------------- entitlement

    public function test_entitlement_needs_both_the_electorate_and_current_membership(): void
    {
        $member = $this->user('Member');
        $stranger = $this->user('Stranger');
        $this->joinCircle($member);
        $this->joinCircle($stranger);

        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'closes_at' => now()->addDay()]);
        $poll->electorate()->attach($member->id);

        $this->assertTrue($poll->isEntitled($member));

        // A member of the circle who was not in the snapshot is not entitled.
        $this->assertFalse($poll->isEntitled($stranger));
        $this->assertFalse($poll->isEntitled(null));
    }

    public function test_leaving_the_circle_keeps_a_response_but_ends_entitlement_and_does_not_move_the_denominator(): void
    {
        $leaver = $this->user('Leaver');
        $this->joinCircle($leaver);

        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'closes_at' => now()->addDay()]);
        $poll->electorate()->attach($leaver->id);
        $this->respond($poll, $leaver);

        $this->leaveCircle($leaver);
        $poll = $poll->fresh();

        $this->assertTrue($poll->hasResponded($leaver), 'a response already given must stand');
        $this->assertFalse($poll->isEntitled($leaver), 'an ex-member may not cast a new response');
        $this->assertFalse($poll->canRespond($leaver));
        $this->assertSame(1, $poll->electorateCount(), 'the turnout denominator must not move');
    }

    public function test_responding_twice_is_refused_unless_the_poll_allows_revision(): void
    {
        $member = $this->user('Member');
        $this->joinCircle($member);

        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'closes_at' => now()->addDay()]);
        $poll->electorate()->attach($member->id);

        $this->assertTrue($poll->canRespond($member));

        $this->respond($poll, $member);
        $poll = $poll->fresh();
        $this->assertFalse($poll->canRespond($member));

        $poll->update(['allow_response_update' => true]);
        $this->assertTrue($poll->fresh()->canRespond($member));
    }

    public function test_a_closed_poll_accepts_nothing_even_from_an_entitled_member(): void
    {
        $member = $this->user('Member');
        $this->joinCircle($member);

        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'closes_at' => now()->subMinute()]);
        $poll->electorate()->attach($member->id);

        $this->assertTrue($poll->isEntitled($member));
        $this->assertFalse($poll->canRespond($member));
    }

    // ------------------------------------------------------------ roster

    public function test_the_roster_publishes_a_live_count_but_withholds_names_until_the_poll_closes(): void
    {
        $ann = $this->user('Ann');
        $bob = $this->user('Bob');
        $this->joinCircle($ann);
        $this->joinCircle($bob);

        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'closes_at' => now()->addDay()]);
        $poll->electorate()->attach([$ann->id, $bob->id]);
        $this->respond($poll, $ann);
        $this->respond($poll, $bob);
        $poll = $poll->fresh();

        // While open: the number, never the names.
        $this->assertSame(2, $poll->respondentCount());
        $this->assertFalse($poll->rosterIsVisible());

        $poll->update(['status' => PollStatus::Concluded->value, 'closes_at' => now()]);
        $poll = $poll->fresh();

        $this->assertTrue($poll->rosterIsVisible());
        $this->assertSame(['Ann', 'Bob'], $poll->roster()->pluck('name')->all());
    }

    public function test_the_roster_throws_rather_than_returning_an_empty_collection_when_hidden(): void
    {
        // An empty roster is indistinguishable from "nobody responded", so a
        // caller who forgot to check rosterIsVisible() would render a
        // plausible falsehood. Refuse loudly instead.
        $poll = $this->makePoll(['status' => PollStatus::Published->value, 'closes_at' => now()->addDay()]);

        $this->expectException(LogicException::class);
        $poll->roster();
    }

    public function test_a_cancelled_poll_never_rosters(): void
    {
        $poll = $this->makePoll(['status' => PollStatus::Cancelled->value, 'closes_at' => now()]);

        $this->assertFalse($poll->rosterIsVisible());
        $this->expectException(LogicException::class);
        $poll->roster();
    }

    public function test_a_closed_poll_with_no_question_does_not_roster(): void
    {
        // A half-built draft that was closed: roster() must throw cleanly
        // rather than fataling on a null relation.
        $poll = Poll::create([
            'circle_id' => $this->circle->id,
            'poll_group_id' => $this->group->id,
            'title' => 'No question yet',
            'eligibility' => 'private',
            'status' => PollStatus::Concluded->value,
            'closes_at' => now(),
        ]);

        $this->assertFalse($poll->rosterIsVisible());
        $this->expectException(LogicException::class);
        $poll->roster();
    }

    // ------------------------------------------------------- authorization

    public function test_the_organiser_may_end_their_poll_only_while_they_remain_a_member(): void
    {
        $organiser = $this->user('Organiser');
        $this->joinCircle($organiser);

        $poll = $this->makePoll(['status' => PollStatus::Published->value], $organiser);

        $this->assertTrue($poll->canBeEndedBy($organiser));

        // Leaving the circle ends the authority without unmaking them the
        // Organiser — a departed member must not be able to void a live poll.
        $this->leaveCircle($organiser);
        $this->assertFalse($poll->fresh()->canBeEndedBy($organiser));
    }

    public function test_a_circle_admin_may_end_any_poll_and_an_ordinary_member_may_end_none(): void
    {
        $organiser = $this->user('Organiser');
        $admin = $this->user('Admin');
        $member = $this->user('Member');
        $this->joinCircle($organiser);
        $this->joinCircle($admin);
        $this->joinCircle($member);
        $this->grantCircleAdmin($admin);

        $poll = $this->makePoll(['status' => PollStatus::Published->value], $organiser);

        $this->assertTrue($poll->canBeEndedBy($admin));
        $this->assertFalse($poll->canBeEndedBy($member));
        $this->assertFalse($poll->canBeEndedBy(null));

        // Still true once the organiser has gone.
        $this->leaveCircle($organiser);
        $this->assertTrue($poll->fresh()->canBeEndedBy($admin));
    }

    public function test_group_management_follows_the_circle_gate(): void
    {
        $admin = $this->user('Admin');
        $member = $this->user('Member');
        $this->joinCircle($admin);
        $this->joinCircle($member);
        $this->grantCircleAdmin($admin);

        $this->assertTrue($this->group->isManageableBy($admin));
        $this->assertFalse($this->group->isManageableBy($member));
        $this->assertFalse($this->group->isManageableBy(null));
    }

    public function test_a_group_is_archived_by_timestamp_and_keeps_its_polls(): void
    {
        $this->makePoll();
        $this->group->update(['archived_at' => now()]);

        $this->assertTrue($this->group->fresh()->isArchived());
        $this->assertCount(1, $this->group->fresh()->polls, 'archiving a shelf must not hide what is on it');
    }

    // ------------------------------------------------------------- result

    public function test_a_result_is_only_public_once_frozen_and_never_for_a_cancelled_poll(): void
    {
        $poll = $this->makePoll([
            'status' => PollStatus::Concluded->value,
            'closes_at' => now(),
            'publish_results' => true,
        ]);

        $this->assertFalse($poll->hasResult());
        $this->assertFalse($poll->resultIsPublic());

        $poll->update(['result' => ['winner_option_id' => 1], 'result_frozen_at' => now()]);
        $this->assertTrue($poll->fresh()->resultIsPublic());

        $cancelled = $this->makePoll([
            'status' => PollStatus::Cancelled->value,
            'publish_results' => true,
            'result' => ['winner_option_id' => 1],
        ]);
        $this->assertFalse($cancelled->resultIsPublic());
    }

    public function test_the_response_shape_decides_which_tally_methods_are_legal(): void
    {
        $this->assertTrue(PollResponseShape::SingleChoice->allows(TallyMethod::Plurality));
        $this->assertFalse(PollResponseShape::SingleChoice->allows(TallyMethod::AverageScore));
        $this->assertTrue(PollResponseShape::Rating->allows(TallyMethod::AverageScore));

        $poll = $this->makePoll();
        $this->assertTrue($poll->question->hasLegalTallyMethod());
    }
}
