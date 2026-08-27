<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Livewire\Communities\Services\Polls\PollModal;
use App\Livewire\Communities\Services\Polls\PollPage;
use App\Models\Circles\Circle;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollResponse;
use App\Models\User;
use App\Services\Circles\VotingService;
use App\Support\Polls\Mark;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * US35 asks for "a real guarantee and not a courtesy": no one — Organiser,
 * platform admin, superadmin — sees which option another person chose, and no
 * setting anywhere changes that. CONTEXT.md states it without qualification,
 * so there is nothing to configure and nothing to flip in the database.
 *
 * What this does NOT conceal is THAT someone responded: the Roster is
 * unaffected, and these tests pin that too, because the guarantee is often
 * mistaken for a secret ballot. It is not one — identity is always stored.
 */
class PollAttributionTest extends TestCase
{
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

    private function member(string $name): User
    {
        $user = User::forceCreate([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'password' => 'secret',
        ]);

        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id,
            'user_id' => $user->id,
            'joined_at' => now()->subYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** A GLOBAL role (circle_id null) — admin, superadmin, or circle_admin. */
    private function withRole(User $user, string $role, ?int $circleId = null): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId, 'model_type' => User::class,
            'model_id' => $user->id, 'circle_id' => $circleId,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function openPoll(): Poll
    {
        return $this->service->publish($this->service->createPoll($this->group, $this->organiser, [
            'title' => 'Choose a steward',
            'prompt' => 'Select ONE from:',
            'shape' => PollResponseShape::SingleChoice,
            'tally_method' => TallyMethod::Plurality,
            'options' => ['Ada', 'Grace'],
        ]));
    }

    private function respond(Poll $poll, User $user, int $optionIndex = 0): PollResponse
    {
        $option = $poll->question->options()->orderBy('position')->get()[$optionIndex];

        return $this->service->respond($poll, $user, [new Mark($option->getKey())]);
    }

    /**
     * An Open poll with exactly one response, and the pieces a test needs to
     * talk about it. The Respondent joins BEFORE publication because the
     * Electorate is snapshotted at publish (docs/adr/0002), so joining later
     * confers no vote.
     *
     * @return array{poll: Poll, respondent: User, response: PollResponse, chosen: int}
     */
    private function pollWithOneResponse(): array
    {
        $respondent = $this->member('Rosa Respondent');
        $poll = $this->openPoll();
        $response = $this->respond($poll, $respondent);

        return [
            'poll' => $poll,
            'respondent' => $respondent,
            'response' => $response,
            'chosen' => (int) $poll->question->options()->orderBy('position')->first()->getKey(),
        ];
    }

    // -------------------------------------------------- nothing to configure

    public function test_the_compose_form_offers_no_control_over_attribution(): void
    {
        $this->assertFalse(
            property_exists(PollModal::class, 'hideVoterIdentities'),
            'the compose form must not carry an attribution switch',
        );

        $manager = $this->withRole($this->member('Mia Manager'), 'circle_admin', $this->circle->id);

        Livewire::actingAs($manager)
            ->test(PollModal::class, ['groupId' => $this->group->getKey()])
            ->assertDontSee('hideVoterIdentities')
            ->assertDontSee('hide_voter_identities');
    }

    public function test_the_label_for_the_removed_control_is_gone_from_every_locale(): void
    {
        // pt_BR has no polls.php yet (it holds Brazilian overrides only), so
        // that pass is a forward guard: a future one must not reintroduce the
        // key while translating from the pt base.
        foreach (['en', 'pt', 'pt_BR'] as $locale) {
            $this->assertFalse(
                Lang::hasForLocale('polls.poll.hide_voter_identities', $locale),
                "polls.poll.hide_voter_identities still exists in $locale",
            );
            $this->assertFalse(Lang::hasForLocale('polls.poll.hide_help', $locale));
        }
    }

    /**
     * The column is dropped, not merely ignored: one that may only ever hold a
     * single value misrepresents what is configurable, and leaves the rule
     * flippable straight in the database.
     */
    public function test_the_flag_is_gone_from_the_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('polls', 'hide_voter_identities'));
    }

    /**
     * The service must not forward a stray attribution key to the insert. The
     * proof is that creation SUCCEEDS: the column is gone, so a payload still
     * carrying it would fail with "no such column".
     */
    public function test_the_service_ignores_a_stray_attribution_key(): void
    {
        $poll = $this->service->createPoll($this->group, $this->organiser, [
            'title' => 'Choose a steward',
            'prompt' => 'Select ONE from:',
            'shape' => PollResponseShape::SingleChoice,
            'tally_method' => TallyMethod::Plurality,
            'options' => ['Ada', 'Grace'],
            'hide_voter_identities' => false,
        ]);

        $this->assertTrue($poll->exists, 'the stray key was dropped, not written');
        $this->assertArrayNotHasKey('hide_voter_identities', $poll->fresh()->getAttributes());
    }

    // ------------------------------------------------------- the guarantee

    public function test_no_role_sees_another_respondents_choice(): void
    {
        ['respondent' => $respondent, 'response' => $response] = $this->pollWithOneResponse();

        $this->assertTrue($response->isChoiceVisibleTo($respondent), 'a Respondent sees their own choice');

        $others = [
            'the Organiser' => $this->organiser,
            'a fellow member' => $this->member('Mo Member'),
            'a circle admin' => $this->withRole($this->member('Cai Admin'), 'circle_admin', $this->circle->id),
            'a platform admin' => $this->withRole($this->member('Pat Admin'), 'admin'),
            'a superadmin' => $this->withRole($this->member('Sue Super'), 'superadmin'),
        ];

        foreach ($others as $who => $user) {
            $this->assertFalse(
                $response->isChoiceVisibleTo($user),
                "$who must not see another Respondent's choice",
            );
        }

        $this->assertFalse($response->isChoiceVisibleTo(null), 'nor a logged-out visitor');
    }

    public function test_a_respondent_still_sees_their_own_response_on_the_page(): void
    {
        ['poll' => $poll, 'respondent' => $respondent, 'chosen' => $chosen] = $this->pollWithOneResponse();

        Livewire::actingAs($respondent)
            ->test(PollPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group, 'poll' => $poll])
            ->assertSet('choice', $chosen);

        // The Organiser's own form is empty — they see no one else's ballot.
        Livewire::actingAs($this->organiser)
            ->test(PollPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group, 'poll' => $poll])
            ->assertSet('choice', null);
    }

    // ------------------------------------------------------------- the Roster

    public function test_the_roster_still_names_who_responded_once_the_poll_closes(): void
    {
        ['poll' => $poll] = $this->pollWithOneResponse();

        $this->assertFalse($poll->rosterIsVisible(), 'names are withheld while the poll runs');
        $this->assertSame(1, $poll->respondentCount(), 'but the live count is published');

        $closed = $this->service->conclude($poll->fresh());

        $this->assertTrue($closed->rosterIsVisible());
        $this->assertSame(['Rosa Respondent'], $closed->roster()->pluck('name')->all());
    }

    /**
     * The guarantee is not a secret ballot and must never be described as one:
     * user_id is always written, which is exactly why the withholding is a
     * display rule rather than a storage one.
     */
    public function test_identity_is_still_stored_for_every_response(): void
    {
        ['respondent' => $respondent] = $this->pollWithOneResponse();

        $this->assertSame(
            $respondent->getKey(),
            (int) DB::table('poll_responses')->value('user_id'),
        );
    }
}
