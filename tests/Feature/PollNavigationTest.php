<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Livewire\Communities\Services\Polls\PollGroupPage;
use App\Livewire\Communities\Services\Polls\PollPage;
use App\Livewire\Communities\Services\Polls\PollServiceContainer;
use App\Models\Circles\Circle;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\User;
use App\Services\Circles\VotingService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * The back-link trail: Polls tab -> group -> poll and back again.
 *
 * Each hop carries ?from= for the one before it, so the tab survives the whole
 * way. The bug this pins: the poll link discarded the group page's own
 * back-link, so returning to the group left it with nothing to go back TO, and
 * the next "back" fell through to the community page's default tab.
 */
class PollNavigationTest extends TestCase
{
    private Circle $circle;

    private PollGroup $group;

    private Poll $poll;

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
            'name' => 'Ward 7', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->circle = Circle::findOrFail($circleId);

        $organiser = User::forceCreate(['name' => 'Org', 'email' => 'org@example.test', 'password' => 'x']);
        $service = app(VotingService::class);
        $this->group = $service->createGroup($this->circle, $organiser, ['name' => '2027 Budget']);
        $this->poll = $service->createPoll($this->group, $organiser, [
            'title' => 'Choose a steward',
            'prompt' => 'Select ONE from:',
            'shape' => \App\Enums\Polls\PollResponseShape::SingleChoice,
            'tally_method' => \App\Enums\Polls\TallyMethod::Plurality,
            'options' => ['Ada', 'Grace'],
        ]);

        $roleId = DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId, 'model_type' => User::class,
            'model_id' => $organiser->id, 'circle_id' => $this->circle->id,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($organiser->fresh());
    }

    public function test_the_tab_survives_the_whole_trail_out_and_back(): void
    {
        // 1. The Polls tab links to the group, remembering the tab.
        $groupUrl = Livewire::test(PollServiceContainer::class, ['circle' => $this->circle])
            ->instance()->groupUrl($this->group);

        $this->assertStringContainsString('service=voting', urldecode($groupUrl));

        // 2. The group page links to the poll, carrying its OWN back-link
        //    forward — this is what was missing.
        $groupFrom = $this->fromParam($groupUrl);
        $pollUrl = Livewire::withQueryParams(['from' => $groupFrom])
            ->test(PollGroupPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group])
            ->instance()->pollUrl($this->poll);

        $pollFrom = $this->fromParam($pollUrl);
        $this->assertStringContainsString('service=voting', urldecode($pollFrom),
            'the poll page must be able to send you back to a group that still knows the tab');

        // 3. Back from the poll reaches the group page...
        $backToGroup = Livewire::withQueryParams(['from' => $pollFrom])
            ->test(PollPage::class, [
                'circle' => $this->circle, 'pollGroup' => $this->group, 'poll' => $this->poll,
            ])->get('backUrl');

        $this->assertStringContainsString('/polls/'.$this->group->slug, $backToGroup);

        // 4. ...and back from THERE reaches the community page with the Polls
        //    tab selected, which is the hop that used to lose it.
        $backToTab = Livewire::withQueryParams(['from' => $this->fromParam($backToGroup)])
            ->test(PollGroupPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group])
            ->get('backUrl');

        $this->assertStringContainsString('service=voting', urldecode($backToTab));
    }

    public function test_a_group_reached_without_a_trail_still_goes_back_to_the_polls_tab(): void
    {
        // A shared link or a bookmark has no ?from= at all.
        $backUrl = Livewire::test(PollGroupPage::class, [
            'circle' => $this->circle, 'pollGroup' => $this->group,
        ])->get('backUrl');

        $this->assertStringContainsString('service=voting', urldecode($backUrl));
    }

    public function test_a_poll_reached_without_a_trail_falls_back_through_its_group(): void
    {
        $backUrl = Livewire::test(PollPage::class, [
            'circle' => $this->circle, 'pollGroup' => $this->group, 'poll' => $this->poll,
        ])->get('backUrl');

        $this->assertStringContainsString('/polls/'.$this->group->slug, $backUrl);
        $this->assertStringContainsString('service=voting', urldecode($backUrl),
            'and that group page must itself know the tab');
    }

    public function test_an_external_from_is_refused(): void
    {
        // ?from= is attacker-controllable; only internal /communities paths win.
        $backUrl = Livewire::withQueryParams(['from' => 'https://example.com/phish'])
            ->test(PollGroupPage::class, ['circle' => $this->circle, 'pollGroup' => $this->group])
            ->get('backUrl');

        $this->assertStringNotContainsString('example.com', $backUrl);
        $this->assertStringContainsString('service=voting', urldecode($backUrl));
    }

    private function fromParam(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['from'] ?? '';
    }
}
