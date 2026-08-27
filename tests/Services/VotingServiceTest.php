<?php

namespace Tests\Services;

use App\Enums\CommunityType;
use App\Enums\Polls\PollEligibility;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\PollStatus;
use App\Enums\Polls\TallyMethod;
use App\Models\Circles\Circle;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollQuestion;
use App\Models\Polls\PollRatingScale;
use App\Models\Polls\PollRatingScalePoint;
use App\Models\User;
use App\Services\Circles\VotingService;
use App\Support\Polls\Mark;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CountsQueries;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * The service seam: every state change a Poll can undergo, driven through
 * VotingService and asserted through public predicates.
 */
class VotingServiceTest extends TestCase
{
    use CountsQueries;

    private VotingService $service;

    private Circle $circle;

    private PollGroup $group;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();

        TestSchema::make()
            ->permissions()
            ->memberships()
            ->polls();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->service = app(VotingService::class);

        // Query-builder insert so Circle::booted() does not fire.
        $circleId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'Ward 7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->circle = Circle::findOrFail($circleId);
        $this->organiser = $this->member('Organiser');
        $this->group = $this->service->createGroup($this->circle, $this->organiser, ['name' => '2027 Budget']);
    }

    private function member(string $name, ?string $role = null, ?string $approved = null, ?string $joinedAt = null): User
    {
        $user = User::forceCreate([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'password' => 'secret',
        ]);

        DB::table('circle_memberships')->insert([
            'circle_id' => $this->circle->id,
            'user_id' => $user->id,
            'internal_role' => $role,
            'metadata' => $approved ? json_encode(['internal_role_approved' => $approved]) : null,
            'joined_at' => $joinedAt ?? now()->subYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function election(array $extra = []): Poll
    {
        return $this->service->createPoll($this->group, $this->organiser, array_merge([
            'title' => 'Choose a steward',
            'prompt' => 'Select ONE from:',
            'shape' => PollResponseShape::SingleChoice,
            'tally_method' => TallyMethod::Plurality,
            'options' => ['Ada', 'Grace', 'Bo'],
        ], $extra));
    }

    /**
     * A Rating Scale and its points, keyed by the VALUE a Tally averages —
     * which is not the point id a response stores (they coincide only by luck).
     *
     * @param  list<array{0: string, 1: int}>  $points  label and value, in order
     * @return array{0: PollRatingScale, 1: array<int, int>}
     */
    private function ratingScaleWithPoints(string $name, array $points = [['Low', 1], ['Mid', 3], ['High', 5]]): array
    {
        $scale = PollRatingScale::create(['name' => $name]);
        $ids = [];

        foreach ($points as $position => [$label, $value]) {
            $ids[$value] = PollRatingScalePoint::create([
                'poll_rating_scale_id' => $scale->id,
                'label' => $label,
                'value' => $value,
                'position' => $position,
            ])->id;
        }

        return [$scale, $ids];
    }

    /**
     * A DRAFT rating poll and the scale it carries — for tests about the
     * question's shape and its scale reference rather than tallying.
     *
     * @return array{0: Poll, 1: PollRatingScale}
     */
    private function ratingPoll(): array
    {
        [$scale] = $this->ratingScaleWithPoints('5-point agreement');

        $poll = $this->election([
            'shape' => PollResponseShape::Rating,
            'tally_method' => TallyMethod::AverageScore,
            'rating_scale_id' => $scale->id,
        ]);

        return [$poll, $scale];
    }

    /**
     * A published rating poll over Road and Water, answered by $respondents
     * people. Scores ALTERNATE between 5 and 3 on Road so the average is real
     * arithmetic rather than a repeated constant; Water is scored 1 by all.
     *
     * @return array{0: Poll, 1: array<string, int>} the poll and label => option id
     */
    private function publishedRatingPollAnsweredBy(int $respondents): array
    {
        [$scale, $points] = $this->ratingScaleWithPoints('Scale for '.$respondents);

        $people = [];

        for ($i = 0; $i < $respondents; $i++) {
            $people[] = $this->member("Respondent {$respondents}.{$i}");
        }

        $poll = $this->service->publish($this->service->createPoll($this->group, $this->organiser, [
            'title' => 'Rate the proposals',
            'prompt' => 'Score each',
            'shape' => PollResponseShape::Rating,
            'tally_method' => TallyMethod::AverageScore,
            'options' => ['Road', 'Water'],
            'rating_scale_id' => $scale->id,
        ]));

        $options = $this->optionIds($poll);

        foreach ($people as $i => $person) {
            $this->service->respond($poll->fresh(), $person, [
                Mark::scoredWithPoint($options['Road'], $points[$i % 2 === 0 ? 5 : 3]),
                Mark::scoredWithPoint($options['Water'], $points[1]),
            ]);
        }

        return [$poll->fresh(), $options];
    }

    private function optionIds(Poll $poll): array
    {
        return $poll->question->options()->pluck('id', 'label')->all();
    }

    // ------------------------------------------------------------- groups

    public function test_it_creates_a_group_with_a_derived_slug_and_archives_without_deleting(): void
    {
        $this->assertSame('2027-budget', $this->group->slug);
        $this->assertTrue($this->service->groupSlugTaken($this->circle, '2027 Budget'));
        $this->assertFalse($this->group->isArchived());

        $this->service->archiveGroup($this->group);
        $this->assertTrue($this->group->fresh()->isArchived());

        $this->service->restoreGroup($this->group);
        $this->assertFalse($this->group->fresh()->isArchived());
    }

    public function test_reordering_rewrites_positions_as_a_clean_sequence(): void
    {
        // Every group starts at position 0, so a scheme that only swapped
        // stored values would do nothing on the first move.
        $second = $this->service->createGroup($this->circle, $this->organiser, ['name' => 'Roads']);
        $third = $this->service->createGroup($this->circle, $this->organiser, ['name' => 'Water']);

        $this->assertSame([0, 0, 0], [$this->group->position, $second->position, $third->position]);

        $this->service->reorderGroups($this->circle, [$third->id, $this->group->id, $second->id]);

        $this->assertSame([0, 1, 2], [
            $third->fresh()->position,
            $this->group->fresh()->position,
            $second->fresh()->position,
        ]);
    }

    public function test_reordering_ignores_foreign_ids_and_keeps_omitted_groups_at_the_end(): void
    {
        $second = $this->service->createGroup($this->circle, $this->organiser, ['name' => 'Roads']);
        $third = $this->service->createGroup($this->circle, $this->organiser, ['name' => 'Water']);

        // A group from another circle must not be reordered into this one, and
        // omitting a group must not lose it.
        $otherCircleId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'Ward 8', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreign = PollGroup::create(['circle_id' => $otherCircleId, 'name' => 'Elsewhere', 'position' => 7]);

        $this->service->reorderGroups($this->circle, [$third->id, $foreign->id]);

        $this->assertSame(0, $third->fresh()->position, 'the named group leads');
        $this->assertSame(7, $foreign->fresh()->position, 'a foreign group is untouched');

        // The two omitted groups keep their relative order after it.
        $this->assertSame(
            [$third->id, $this->group->id, $second->id],
            $this->circle->pollGroups()->orderBy('position')->pluck('id')->all(),
        );
    }

    // -------------------------------------------------------- composition

    public function test_it_refuses_an_illegal_shape_and_tally_pairing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not legal/');

        $this->election(['tally_method' => TallyMethod::AverageScore]);
    }

    public function test_it_refuses_fewer_than_two_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->election(['options' => ['Ada']]);
    }

    public function test_a_rating_poll_needs_a_scale_and_only_a_rating_poll_may_have_one(): void
    {
        try {
            $this->election([
                'shape' => PollResponseShape::Rating,
                'tally_method' => TallyMethod::AverageScore,
            ]);
            $this->fail('a rating poll without a scale should be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('needs a rating scale', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->election(['rating_scale_id' => 1]);
    }

    public function test_a_new_poll_is_a_draft_with_one_question_and_ordered_options(): void
    {
        $poll = $this->election();

        $this->assertTrue($poll->isDraft());
        $this->assertSame(1, $poll->questions()->count());
        $this->assertSame(0, $poll->question->position);
        $this->assertSame(['Ada', 'Grace', 'Bo'], $poll->question->options()->pluck('label')->all());
        $this->assertSame(0, $poll->electorateCount(), 'a draft has no electorate yet');
    }

    public function test_a_poll_cannot_close_before_it_opens(): void
    {
        // The real cause of the stale-result report: a mistyped pair makes a
        // poll Closed the moment it is published, which then freezes an empty
        // Result. Refuse the pair rather than clean up after it.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot close before it opens/');

        $this->election([
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addHour(),
        ]);
    }

    public function test_an_amendment_is_checked_against_the_effective_window(): void
    {
        // Moving ONE end must be validated against the other as it already
        // stands, not only against a pair supplied together.
        $poll = $this->election([
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(2),
        ]);

        try {
            $this->service->updatePoll($poll, ['closes_at' => now()->addHours(2)]);
            $this->fail('pulling the close before the existing open should be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot close before it opens', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->updatePoll($poll->fresh(), ['opens_at' => now()->addDays(3)]);
    }

    public function test_an_identical_opening_and_closing_time_is_refused(): void
    {
        // A zero-length window is never what anyone meant.
        $at = now()->addDay();

        $this->expectException(InvalidArgumentException::class);
        $this->election(['opens_at' => $at, 'closes_at' => $at]);
    }

    // --------------------------------------------------------- publishing

    public function test_publishing_snapshots_the_electorate_and_opens_the_poll(): void
    {
        $this->member('Ann');
        $this->member('Bob');

        $poll = $this->service->publish($this->election());

        $this->assertSame(PollStatus::Published, $poll->status);
        $this->assertNotNull($poll->opens_at);
        $this->assertTrue($poll->isOpen());
        $this->assertSame(3, $poll->electorateCount(), 'organiser + two members');
    }

    public function test_a_qualifying_date_may_not_be_in_the_future(): void
    {
        // The electorate is snapshotted AT publish, so a future cut-off could
        // never be resolved without a scheduled job.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/may not be in the future/');

        $this->service->publish($this->election(), now()->addDay());
    }

    public function test_someone_who_joins_after_publication_is_not_enfranchised(): void
    {
        $poll = $this->service->publish($this->election());

        $latecomer = $this->member('Latecomer', joinedAt: now()->toDateTimeString());

        $this->assertFalse($poll->fresh()->isInElectorate($latecomer));
        $this->assertFalse($poll->fresh()->canRespond($latecomer));
    }

    public function test_internal_eligibility_admits_only_approved_internal_roles(): void
    {
        // This is the case that fails if the electorate is ever DERIVED instead
        // of snapshotted: internal_role_approved is mutated in place and keeps
        // no history (docs/adr/0002).
        $approved = $this->member('Approved', 'organisation_member', 'approved');
        $pending = $this->member('Pending', 'organisation_member', 'pending');
        $plain = $this->member('Plain');

        $poll = $this->service->publish($this->election(['eligibility' => PollEligibility::Internal]));

        $this->assertTrue($poll->isInElectorate($approved));
        $this->assertFalse($poll->isInElectorate($pending), 'a claimed but unconfirmed role is not trusted');
        $this->assertFalse($poll->isInElectorate($plain));
        $this->assertSame(1, $poll->electorateCount());
    }

    public function test_a_poll_cannot_be_published_twice(): void
    {
        $poll = $this->service->publish($this->election());

        $this->expectException(RuntimeException::class);
        $this->service->publish($poll);
    }

    // -------------------------------------------------------- responding

    public function test_it_records_a_response_and_refuses_a_second_one(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $options = $this->optionIds($poll);

        $this->service->respond($poll, $ann, [new Mark($options['Ada'])]);
        $this->assertSame(1, $poll->fresh()->respondentCount());

        $this->expectException(RuntimeException::class);
        $this->service->respond($poll->fresh(), $ann, [new Mark($options['Bo'])]);
    }

    public function test_a_revision_replaces_the_previous_answer_rather_than_adding_to_it(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election(['allow_response_update' => true]));
        $options = $this->optionIds($poll);

        $this->service->respond($poll, $ann, [new Mark($options['Ada'])]);
        $this->service->respond($poll->fresh(), $ann, [new Mark($options['Grace'])]);

        $poll = $poll->fresh();
        $this->assertSame(1, $poll->respondentCount());

        $response = $poll->question->responses()->with('items')->first();
        $this->assertCount(1, $response->items);
        $this->assertSame($options['Grace'], (int) $response->items->first()->poll_option_id);
    }

    public function test_it_refuses_a_non_member_and_an_option_not_on_the_ballot(): void
    {
        $poll = $this->service->publish($this->election());
        $outsider = User::forceCreate(['name' => 'Outsider', 'email' => 'out@example.test', 'password' => 'x']);

        try {
            $this->service->respond($poll, $outsider, [new Mark($this->optionIds($poll)['Ada'])]);
            $this->fail('a non-member should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('may not respond', $e->getMessage());
        }

        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());

        $this->expectException(InvalidArgumentException::class);
        $this->service->respond($poll, $ann, [new Mark(999999)]);
    }

    public function test_a_single_choice_response_must_mark_exactly_one_option(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $options = $this->optionIds($poll);

        $this->expectException(InvalidArgumentException::class);
        $this->service->respond($poll, $ann, [new Mark($options['Ada']), new Mark($options['Bo'])]);
    }

    public function test_ranked_responses_must_use_distinct_gapless_ranks(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election([
            'shape' => PollResponseShape::RankedChoice,
            'tally_method' => TallyMethod::InstantRunoff,
        ]));
        $options = $this->optionIds($poll);

        try {
            $this->service->respond($poll, $ann, [
                new Mark($options['Ada'], rank: 1),
                new Mark($options['Grace'], rank: 1),
            ]);
            $this->fail('duplicate ranks should be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('distinct rank', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/1\.\.N/');
        $this->service->respond($poll, $ann, [
            new Mark($options['Ada'], rank: 1),
            new Mark($options['Grace'], rank: 5),
        ]);
    }

    public function test_a_poll_requiring_a_full_ranking_refuses_a_partial_one(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election([
            'shape' => PollResponseShape::RankedChoice,
            'tally_method' => TallyMethod::InstantRunoff,
            'require_full_ranking' => true,
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/every option to be ranked/');
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'], rank: 1)]);
    }

    public function test_a_rating_response_stores_the_scale_point_and_tallies_its_value(): void
    {
        [$scale, $points] = $this->ratingScaleWithPoints('5-point agreement', [
            ['Strongly Disagree', 1], ['Neutral', 3], ['Strongly Agree', 5],
        ]);

        $ann = $this->member('Ann');
        $bob = $this->member('Bob');

        $poll = $this->service->publish($this->service->createPoll($this->group, $this->organiser, [
            'title' => 'Rate the proposals',
            'prompt' => 'Score each',
            'shape' => PollResponseShape::Rating,
            'tally_method' => TallyMethod::AverageScore,
            'options' => ['Road', 'Water'],
            'rating_scale_id' => $scale->id,
        ]));

        $options = $this->optionIds($poll);

        $this->service->respond($poll, $ann, [
            Mark::scoredWithPoint($options['Road'], $points[5]),
            Mark::scoredWithPoint($options['Water'], $points[1]),
        ]);
        $this->service->respond($poll->fresh(), $bob, [
            Mark::scoredWithPoint($options['Road'], $points[3]),
            Mark::scoredWithPoint($options['Water'], $points[1]),
        ]);

        // The POINT is stored; its VALUE is what gets averaged.
        // Road (5+3)/2 = 4 · Water (1+1)/2 = 1
        $result = $this->service->tally($poll->fresh());
        $this->assertSame(4.0, $result->totals[$options['Road']]);
        $this->assertSame(1.0, $result->totals[$options['Water']]);
        $this->assertSame($options['Road'], $result->winnerOptionId);
    }

    /**
     * The tally runs on every view of an open poll, and again when the Result
     * freezes. Reading the scale point off each response ITEM made that one
     * query per item — 200 respondents scoring 5 options is a thousand extra
     * round trips (.scratch/polls/issues/12).
     */
    public function test_tallying_a_rating_poll_costs_the_same_queries_whatever_the_turnout(): void
    {
        [$few, $fewOptions] = $this->publishedRatingPollAnsweredBy(2);
        [$many, $manyOptions] = $this->publishedRatingPollAnsweredBy(8);

        $fewQueries = $this->queriesDuring(fn () => $this->service->tally($few));
        $manyQueries = $this->queriesDuring(fn () => $this->service->tally($many));

        $this->assertSame(
            count($fewQueries),
            count($manyQueries),
            'tallying 8 respondents took '.count($manyQueries).' queries where 2 took '
            .count($fewQueries).': the scale point must be loaded WITH the items, not per item',
        );

        // WHY it is flat: the points are fetched once, not once per item.
        $this->assertSame(1, $this->queriesTouching($manyQueries, 'poll_rating_scale_points'));

        // A query fix, not an arithmetic one. Road alternates 5 and 3, so both
        // averages are 4.0 while Water stays 1.0 — the pure tally itself is
        // pinned exhaustively in tests/Unit/PollTallyTest.php.
        $fewResult = $this->service->tally($few);
        $manyResult = $this->service->tally($many);

        $this->assertSame(4.0, $fewResult->totals[$fewOptions['Road']]);
        $this->assertSame(1.0, $fewResult->totals[$fewOptions['Water']]);
        $this->assertSame(4.0, $manyResult->totals[$manyOptions['Road']]);
        $this->assertSame(1.0, $manyResult->totals[$manyOptions['Water']]);
        $this->assertSame(2, $fewResult->turnout);
        $this->assertSame(8, $manyResult->turnout);
    }

    public function test_a_rating_response_must_score_every_option_with_a_point_from_its_own_scale(): void
    {
        [$scale, $points] = $this->ratingScaleWithPoints('Two point', [['Yes', 1]]);

        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->service->createPoll($this->group, $this->organiser, [
            'title' => 'Rate', 'prompt' => 'Score each',
            'shape' => PollResponseShape::Rating, 'tally_method' => TallyMethod::AverageScore,
            'options' => ['Road', 'Water'], 'rating_scale_id' => $scale->id,
        ]));
        $options = $this->optionIds($poll);

        try {
            $this->service->respond($poll, $ann, [Mark::scoredWithPoint($options['Road'], $points[1])]);
            $this->fail('a partial scoring should be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('score every option', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->respond($poll, $ann, [
            Mark::scoredWithPoint($options['Road'], 999999),
            Mark::scoredWithPoint($options['Water'], 999998),
        ]);
    }

    // ---------------------------------------------------------- amendment

    public function test_a_poll_with_responses_can_no_longer_be_amended_or_unpublished(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);

        try {
            $this->service->updatePoll($poll->fresh(), ['title' => 'Renamed']);
            $this->fail('amending a poll with responses should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('can no longer be amended', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->service->unpublish($poll->fresh());
    }

    public function test_a_poll_stays_amendable_until_its_first_response_published_or_not(): void
    {
        // Publishing is NOT the point of no return — the first response is.
        $poll = $this->election();
        $this->assertTrue($poll->isAmendable(), 'a draft is amendable');

        $ann = $this->member('Ann');
        $poll = $this->service->publish($poll);
        $this->assertTrue($poll->isAmendable(), 'a published poll nobody has answered is still amendable');

        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);
        $this->assertFalse($poll->fresh()->isAmendable());
    }

    public function test_amending_rewrites_the_prompt_options_and_settings(): void
    {
        $poll = $this->service->publish($this->election());

        $poll = $this->service->updatePoll($poll, [
            'title' => 'Choose two stewards',
            'prompt' => 'Rank them:',
            'shape' => PollResponseShape::RankedChoice,
            'tally_method' => TallyMethod::InstantRunoff,
            'options' => ['Ada', 'Bo'],
            'allow_response_update' => true,
        ]);

        $this->assertSame('Choose two stewards', $poll->title);
        $this->assertTrue($poll->allow_response_update);
        $this->assertSame('Rank them:', $poll->question->text);
        $this->assertSame(PollResponseShape::RankedChoice, $poll->question->type);
        $this->assertSame(['Ada', 'Bo'], $poll->question->options()->pluck('label')->all());
    }

    public function test_amending_cannot_leave_an_illegal_shape_tally_or_scale_combination(): void
    {
        $poll = $this->service->publish($this->election());

        try {
            $this->service->updatePoll($poll, ['tally_method' => TallyMethod::AverageScore]);
            $this->fail('an illegal pairing should be refused on amendment too');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not legal', $e->getMessage());
        }

        // createPoll has always guarded this; updatePoll must too, or an
        // amendment can strand a rating poll with no scale.
        $before = PollQuestion::findOrFail($poll->question->getKey())->toArray();

        try {
            $this->service->updatePoll($poll->fresh(), [
                'shape' => PollResponseShape::Rating,
                'tally_method' => TallyMethod::AverageScore,
                'title' => 'Renamed',
            ]);
            $this->fail('a rating poll with no scale should be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('needs a rating scale', $e->getMessage());
        }

        // A refusal is never a partial write: every guard runs before the
        // transaction opens, so neither the question nor the title moved.
        $this->assertSame($before, PollQuestion::findOrFail($poll->question->getKey())->toArray());
        $this->assertSame('Choose a steward', $poll->fresh()->title);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Only a rating poll/');
        $this->service->updatePoll($poll->fresh(), ['rating_scale_id' => 1]);
    }

    /**
     * The same `??` mistake, one method away: PollGroupModal sends an explicit
     * null to clear a description (it stores '' as null), and updateGroup
     * discarded it, so a description could be typed but never removed. Found by
     * the audit .scratch/polls/issues/10 asked for, not the reported symptom.
     */
    public function test_clearing_a_group_description_is_not_silently_ignored(): void
    {
        $group = $this->service->createGroup($this->circle, $this->organiser, [
            'name' => 'Budget',
            'description' => 'Everything about the 2027 budget',
        ]);

        $this->service->updateGroup($group, ['description' => null]);

        $this->assertNull(
            PollGroup::findOrFail($group->getKey())->description,
            'an explicit null clears the description',
        );

        // An omitted key still means "leave it alone".
        $this->service->updateGroup($group->fresh(), ['description' => 'Restored']);
        $this->service->updateGroup($group->fresh(), ['name' => 'Budget 2027']);

        $restored = PollGroup::findOrFail($group->getKey());
        $this->assertSame('Budget 2027', $restored->name);
        $this->assertSame('Restored', $restored->description);
    }

    /**
     * Amending OFF rating must clear the scale. The compose form sends an
     * explicit null when the shape changes (PollModal::updatedShape), and the
     * write path must respect it: keeping the old value stores a single-choice
     * question carrying a rating scale — precisely the combination
     * guardRatingScale refuses to CREATE.
     */
    public function test_switching_a_poll_off_rating_clears_its_scale(): void
    {
        [$poll, $scale] = $this->ratingPoll();
        $this->assertSame($scale->id, $poll->question->rating_scale_id);

        $amended = $this->service->updatePoll($poll, [
            'shape' => PollResponseShape::SingleChoice,
            'tally_method' => TallyMethod::Plurality,
            'rating_scale_id' => null,
        ]);

        // The STORED question, read back fresh — not merely the absence of an
        // exception, which is what let this through in the first place.
        $stored = PollQuestion::findOrFail($amended->question->getKey());

        $this->assertSame(PollResponseShape::SingleChoice, $stored->type);
        $this->assertSame(TallyMethod::Plurality, $stored->tally_method);
        $this->assertNull($stored->rating_scale_id, 'the scale must not be silently restored');
    }

    public function test_a_rating_poll_that_keeps_its_shape_keeps_its_scale(): void
    {
        [$poll, $scale] = $this->ratingPoll();

        $amended = $this->service->updatePoll($poll, ['title' => 'Renamed, same ballot']);
        $stored = PollQuestion::findOrFail($amended->question->getKey());

        $this->assertSame('Renamed, same ballot', $amended->title);
        $this->assertSame(PollResponseShape::Rating, $stored->type);
        $this->assertSame($scale->id, $stored->rating_scale_id, 'an unrelated edit must not drop the scale');
    }

    public function test_switching_a_poll_to_rating_stores_the_chosen_scale(): void
    {
        $poll = $this->election();
        $scale = PollRatingScale::create(['name' => '1-10 priority']);

        $amended = $this->service->updatePoll($poll, [
            'shape' => PollResponseShape::Rating,
            'tally_method' => TallyMethod::AverageScore,
            'rating_scale_id' => $scale->id,
        ]);
        $stored = PollQuestion::findOrFail($amended->question->getKey());

        $this->assertSame(PollResponseShape::Rating, $stored->type);
        $this->assertSame($scale->id, $stored->rating_scale_id);
    }

    /*
    |--------------------------------------------------------------------------
    | The Electorate under amendment (.scratch/polls/issues/11)
    |--------------------------------------------------------------------------
    |
    | The Electorate is snapshotted BECAUSE it cannot be derived afterwards
    | (docs/adr/0002), so an amendment that moves the Qualifying Date or the
    | eligibility rule without re-snapshotting leaves a denominator nothing can
    | reconstruct: the Poll's stated date and its real Electorate disagree.
    |
    | Amendment requires zero responses, so re-snapshotting disenfranchises
    | nobody who has already acted.
    */

    public function test_moving_the_qualifying_date_earlier_drops_a_member_who_joined_after_it(): void
    {
        $recent = $this->member('Recent', joinedAt: now()->subMonths(6)->toDateTimeString());
        $poll = $this->service->publish($this->election());

        $this->assertTrue($poll->isInElectorate($recent), 'the Qualifying Date defaulted to publication');

        $amended = $this->service->updatePoll($poll, [
            'qualifying_date' => now()->subYear()->addDay(),
        ]);

        $this->assertFalse(
            $amended->isInElectorate($recent),
            'a member who joined after the new Qualifying Date is no longer entitled',
        );
    }

    public function test_widening_the_qualifying_date_admits_a_member_who_joined_after_the_old_one(): void
    {
        $recent = $this->member('Recent', joinedAt: now()->subMonths(6)->toDateTimeString());
        $poll = $this->service->publish(
            $this->election(['qualifying_date' => now()->subYear()->addDay()]),
        );

        $this->assertFalse($poll->isInElectorate($recent));

        $amended = $this->service->updatePoll($poll, ['qualifying_date' => now()]);

        $this->assertTrue($amended->isInElectorate($recent), 'the wider Qualifying Date enfranchises them');
        $this->assertTrue($amended->isInElectorate($this->organiser));
    }

    /**
     * The re-snapshot obeys the ORIGINAL rules, approved internal roles
     * included — a claimed but unconfirmed role is never trusted.
     */
    public function test_amending_the_eligibility_rule_re_snapshots_the_electorate(): void
    {
        $approved = $this->member('Approved', 'organisation_member', 'approved');
        $pending = $this->member('Pending', 'organisation_member', 'pending');
        $plain = $this->member('Plain');

        $poll = $this->service->publish($this->election());
        $this->assertSame(4, $poll->electorateCount(), 'Private admits every member');

        $internal = $this->service->updatePoll($poll, ['eligibility' => PollEligibility::Internal]);

        $this->assertTrue($internal->isInElectorate($approved));
        $this->assertFalse($internal->isInElectorate($pending), 'a claimed role is not trusted on a re-snapshot either');
        $this->assertFalse($internal->isInElectorate($plain));
        $this->assertSame(1, $internal->electorateCount());

        // ...and back again: widening restores the members it had removed.
        $private = $this->service->updatePoll($internal->fresh(), ['eligibility' => PollEligibility::Private]);

        $this->assertSame(4, $private->electorateCount());
        $this->assertTrue($private->isInElectorate($plain));
    }

    public function test_amending_anything_else_leaves_the_electorate_untouched(): void
    {
        $poll = $this->service->publish($this->election());
        $before = $poll->electorate()->pluck('users.id')->sort()->values()->all();

        // Someone joins AFTER publication: if an unrelated edit re-snapshotted,
        // they would silently appear in the Electorate.
        $late = $this->member('Late', joinedAt: now()->toDateTimeString());

        $amended = $this->service->updatePoll($poll, [
            'title' => 'Renamed',
            'prompt' => 'Pick one:',
            'options' => ['Ada', 'Grace'],
        ]);

        $this->assertSame($before, $amended->electorate()->pluck('users.id')->sort()->values()->all());
        $this->assertFalse($amended->isInElectorate($late));
    }

    public function test_amending_a_draft_snapshots_nothing_because_publishing_does(): void
    {
        $poll = $this->election();

        $amended = $this->service->updatePoll($poll, ['qualifying_date' => now()->subMonth()]);

        $this->assertSame(0, $amended->electorateCount(), 'a Draft has no Electorate to keep honest');

        $published = $this->service->publish($amended);
        $this->assertTrue($published->isInElectorate($this->organiser));
    }

    /**
     * guardAmendable admits a Concluded or Cancelled poll that nobody answered,
     * and such a poll still carries an Electorate — so the re-snapshot is keyed
     * on "has been published", not on status being Published.
     */
    public function test_a_concluded_poll_with_no_responses_still_re_snapshots(): void
    {
        $recent = $this->member('Recent', joinedAt: now()->subMonths(6)->toDateTimeString());

        // Opened yesterday, so concluding now leaves a window guardWindow
        // accepts. (A poll published and concluded in the same second has
        // opens_at == closes_at and cannot be amended at all — unrelated to
        // the Electorate, and left alone here.)
        $poll = $this->service->conclude(
            $this->service->publish($this->election(['opens_at' => now()->subDay()])),
        );

        $this->assertTrue($poll->isInElectorate($recent));

        $amended = $this->service->updatePoll($poll, ['qualifying_date' => now()->subYear()->addDay()]);

        $this->assertFalse($amended->isInElectorate($recent));
    }

    public function test_an_amendment_may_not_move_the_qualifying_date_into_the_future(): void
    {
        $poll = $this->service->publish($this->election());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/may not be in the future/');
        $this->service->updatePoll($poll, ['qualifying_date' => now()->addWeek()]);
    }

    public function test_a_published_poll_may_not_have_its_qualifying_date_removed(): void
    {
        $poll = $this->service->publish($this->election());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must keep a Qualifying Date/');
        $this->service->updatePoll($poll, ['qualifying_date' => null]);
    }

    public function test_an_untouched_published_poll_may_return_to_draft_and_loses_its_electorate(): void
    {
        $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->assertSame(2, $poll->electorateCount());

        $poll = $this->service->unpublish($poll);

        $this->assertTrue($poll->isDraft());
        $this->assertSame(0, $poll->electorateCount(), 'a fresh snapshot is taken on the next publish');
    }

    // ------------------------------------------------------ ending & result

    public function test_concluding_stamps_the_closing_time_and_freezes_the_result(): void
    {
        $ann = $this->member('Ann');
        $bob = $this->member('Bob');
        $poll = $this->service->publish($this->election());
        $options = $this->optionIds($poll);

        $this->service->respond($poll, $ann, [new Mark($options['Grace'])]);
        $this->service->respond($poll->fresh(), $bob, [new Mark($options['Grace'])]);

        $this->assertFalse($poll->fresh()->hasResult(), 'nothing is frozen while the poll is open');

        $poll = $this->service->conclude($poll->fresh());

        $this->assertSame(PollStatus::Concluded, $poll->status);
        $this->assertNotNull($poll->closes_at, 'ending must stamp the clock so the two never disagree');
        $this->assertTrue($poll->hasResult());
        $this->assertSame($options['Grace'], $poll->result['winner_option_id']);
        $this->assertSame($poll->result['turnout'], array_sum($poll->result['totals']));
    }

    public function test_freezing_a_result_is_idempotent_and_never_rewrites_the_decision(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);

        $poll = $this->service->conclude($poll->fresh());
        $frozenAt = (string) $poll->result_frozen_at;

        $this->service->freezeResult($poll->fresh());

        $this->assertSame($frozenAt, (string) $poll->fresh()->result_frozen_at);
    }

    public function test_a_poll_that_runs_out_of_time_can_be_frozen_on_read_without_a_scheduled_job(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);

        $poll->update(['closes_at' => now()->subMinute()]);
        $poll = $poll->fresh();

        $this->assertTrue($poll->isClosed());
        $this->assertSame(PollStatus::Published, $poll->status);

        $poll = $this->service->freezeResult($poll);
        $this->assertTrue($poll->hasResult());
    }

    public function test_cancelling_voids_the_poll_and_it_never_yields_a_result(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);

        $poll = $this->service->cancel($poll->fresh());

        $this->assertTrue($poll->isCancelled());
        $this->assertNotNull($poll->closes_at);
        $this->assertFalse($poll->hasResult());

        // Even asked directly, a cancelled poll is never tallied into a Result.
        $this->assertFalse($this->service->freezeResult($poll->fresh())->hasResult());

        $this->expectException(RuntimeException::class);
        $this->service->cancel($poll->fresh());
    }

    // ------------------------------------------------- polls:freeze-results

    public function test_the_freeze_command_catches_polls_nobody_visited(): void
    {
        $ann = $this->member('Ann');

        // Ran out its clock, never opened by anyone.
        $expired = $this->service->publish($this->election());
        $this->service->respond($expired, $ann, [new Mark($this->optionIds($expired)['Ada'])]);
        $expired->update(['closes_at' => now()->subDay()]);

        // Still open — must be left alone.
        $open = $this->service->publish($this->election(['closes_at' => now()->addDay()]));

        // Cancelled — its responses must never be tallied.
        $cancelled = $this->service->publish($this->election());
        $this->service->respond($cancelled, $ann, [new Mark($this->optionIds($cancelled)['Ada'])]);
        $cancelled = $this->service->cancel($cancelled->fresh());

        // Draft — never ran.
        $draft = $this->election();

        $this->artisan('polls:freeze-results')->assertSuccessful();

        $this->assertTrue($expired->fresh()->hasResult(), 'a closed poll nobody visited must be frozen');
        $this->assertFalse($open->fresh()->hasResult(), 'an open poll must not be');
        $this->assertFalse($cancelled->fresh()->hasResult(), 'a cancelled poll never gets a Result');
        $this->assertFalse($draft->fresh()->hasResult(), 'a draft never gets one either');
    }

    public function test_the_freeze_command_clears_a_stale_result_from_an_open_poll(): void
    {
        // Self-healing for rows written before updatePoll/unpublish learned to
        // discard a stale Result. Left in place it would outlive the poll: the
        // page shows the stale count while votes are cast, and it is still
        // there when the poll closes, becoming the record of a vote it never saw.
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election(['closes_at' => now()->addDay()]));
        $poll->electorate()->syncWithoutDetaching([$ann->id]);
        $this->service->respond($poll->fresh(), $ann, [new Mark($this->optionIds($poll)['Ada'])]);

        // Plant the figure the bug produced.
        $poll->fresh()->update([
            'result' => ['method' => 'plurality', 'totals' => [], 'turnout' => 0,
                'winner_option_id' => null, 'tied_option_ids' => [], 'rounds' => null],
            'result_frozen_at' => now(),
        ]);
        $this->assertTrue($poll->fresh()->hasResult());

        $this->artisan('polls:freeze-results')->assertSuccessful();

        $this->assertFalse($poll->fresh()->hasResult(), 'an open poll must not hold a frozen Result');
        $this->assertNull($poll->fresh()->result_frozen_at);

        // And once it genuinely closes, it freezes the REAL count.
        $poll->fresh()->update(['closes_at' => now()->subMinute()]);
        $this->artisan('polls:freeze-results')->assertSuccessful();

        $this->assertSame(1, $poll->fresh()->result['turnout']);
    }

    public function test_the_freeze_command_never_rewrites_an_existing_result(): void
    {
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);
        $poll = $this->service->conclude($poll->fresh());

        $frozenAt = (string) $poll->result_frozen_at;
        $result = $poll->result;

        $this->artisan('polls:freeze-results')->assertSuccessful();

        $this->assertSame($frozenAt, (string) $poll->fresh()->result_frozen_at);
        $this->assertSame($result, $poll->fresh()->result);
    }

    public function test_the_freeze_command_writes_no_poll_state(): void
    {
        // ADR-0001: closing is derived from the clock. A scheduled job may
        // record a Result but must never touch status, closes_at or
        // archived_at, or the clock stops being the authority.
        $ann = $this->member('Ann');
        $poll = $this->service->publish($this->election());
        $this->service->respond($poll, $ann, [new Mark($this->optionIds($poll)['Ada'])]);
        $poll->update(['closes_at' => now()->subDay()]);

        // Compare stringified values: only() hands back Carbon instances, which
        // compare by object identity rather than by the time they represent.
        $snapshot = fn (Poll $p): array => [
            'status' => $p->status->value,
            'opens_at' => $p->opens_at?->toDateTimeString(),
            'closes_at' => $p->closes_at?->toDateTimeString(),
            'archived_at' => $p->archived_at?->toDateTimeString(),
        ];

        $before = $snapshot($poll->fresh());

        $this->artisan('polls:freeze-results')->assertSuccessful();

        $after = $poll->fresh();
        $this->assertSame($before, $snapshot($after));
        $this->assertSame(PollStatus::Published, $after->status, 'still published — it merely ran out of time');
        $this->assertTrue($after->hasResult());
    }

    public function test_amending_a_poll_discards_a_result_frozen_while_it_was_closed(): void
    {
        // The reported bug. A poll whose closing time had already passed got an
        // EMPTY Result frozen, was then edited back into life (allowed — no
        // responses yet), and the stale zero outlived it: freezeResult never
        // overwrites, so every later view showed "Nobody responded" while
        // votes were being cast.
        $poll = $this->service->publish($this->election(['closes_at' => now()->subMinute()]));

        $this->assertTrue($poll->isClosed());
        $poll = $this->service->freezeResult($poll);
        $this->assertTrue($poll->hasResult());
        $this->assertSame(0, $poll->result['turnout']);

        $poll = $this->service->updatePoll($poll->fresh(), ['closes_at' => now()->addDay()]);

        $this->assertTrue($poll->isOpen());
        $this->assertFalse($poll->hasResult(), 'the stale Result must not survive the amendment');
        $this->assertNull($poll->result_frozen_at);

        // And it freezes correctly once it really does close.
        $ann = $this->member('Ann');
        $poll->electorate()->syncWithoutDetaching([$ann->id]);
        $this->service->respond($poll->fresh(), $ann, [new Mark($this->optionIds($poll)['Ada'])]);

        $poll = $this->service->conclude($poll->fresh());
        $this->assertSame(1, $poll->result['turnout']);
    }

    public function test_returning_a_poll_to_draft_discards_a_frozen_result(): void
    {
        $poll = $this->service->publish($this->election(['closes_at' => now()->subMinute()]));
        $this->service->freezeResult($poll);

        $poll = $this->service->unpublish($poll->fresh());

        $this->assertTrue($poll->isDraft());
        $this->assertFalse($poll->hasResult());
        $this->assertNull($poll->result_frozen_at);
    }

    public function test_instant_runoff_through_the_service_eliminates_and_redistributes(): void
    {
        // Five electors: first preferences Ada 2, Grace 2, Bo 1. Nobody has a
        // majority; Bo goes out alone and his ballot's next preference is
        // Grace, giving Grace three of five in round two.
        $ann = $this->member('Ann');
        $bob = $this->member('Bob');
        $cat = $this->member('Cat');
        $dan = $this->member('Dan');

        $poll = $this->service->publish($this->election([
            'shape' => PollResponseShape::RankedChoice,
            'tally_method' => TallyMethod::InstantRunoff,
        ]));
        $o = $this->optionIds($poll);

        $rank = fn (array $order): array => array_map(
            fn (int $optionId, int $index): Mark => new Mark($optionId, rank: $index + 1),
            $order,
            array_keys($order),
        );

        $this->service->respond($poll, $this->organiser, $rank([$o['Ada'], $o['Grace'], $o['Bo']]));
        $this->service->respond($poll->fresh(), $ann, $rank([$o['Ada'], $o['Grace'], $o['Bo']]));
        $this->service->respond($poll->fresh(), $bob, $rank([$o['Grace'], $o['Bo'], $o['Ada']]));
        $this->service->respond($poll->fresh(), $cat, $rank([$o['Grace'], $o['Bo'], $o['Ada']]));
        $this->service->respond($poll->fresh(), $dan, $rank([$o['Bo'], $o['Grace'], $o['Ada']]));

        $result = $this->service->tally($poll->fresh());

        $this->assertSame($o['Grace'], $result->winnerOptionId);
        $this->assertSame(2, $result->rounds);
        $this->assertSame([$o['Ada'] => 2, $o['Grace'] => 2, $o['Bo'] => 1], $result->totals);
    }
}
