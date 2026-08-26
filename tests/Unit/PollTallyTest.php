<?php

namespace Tests\Unit;

use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Support\Polls\Ballot;
use App\Support\Polls\PollResult;
use App\Support\Polls\Tally;
use PHPUnit\Framework\TestCase;

/**
 * The pure tally seam: ballots in, a Result out, with no database, circle or
 * membership involved. Every expected winner and total below is worked out by
 * hand in the comment above it — a test that recomputed instant-runoff to
 * check instant-runoff would prove nothing.
 */
class PollTallyTest extends TestCase
{
    /** @param  list<int>  $optionIdsInOrder */
    private function ranked(int $times, array $optionIdsInOrder): array
    {
        return array_fill(0, $times, Ballot::ranking($optionIdsInOrder));
    }

    private function chosen(int $times, int $optionId): array
    {
        return array_fill(0, $times, Ballot::choosing($optionId));
    }

    // ------------------------------------------------------------- plurality

    public function test_plurality_awards_the_most_votes_and_totals_sum_to_turnout(): void
    {
        $result = Tally::run(TallyMethod::Plurality, [1, 2, 3], array_merge(
            $this->chosen(4, 1), $this->chosen(2, 2), $this->chosen(1, 3),
        ));

        $this->assertSame(1, $result->winnerOptionId);
        $this->assertSame([1 => 4, 2 => 2, 3 => 1], $result->totals);

        // The check a member performs by hand against the Roster's length.
        $this->assertSame($result->turnout, array_sum($result->totals));
        $this->assertSame(7, $result->turnout);
    }

    public function test_plurality_needs_no_majority(): void
    {
        // 3 of 8 is a plurality but nowhere near half.
        $result = Tally::run(TallyMethod::Plurality, [1, 2, 3], array_merge(
            $this->chosen(3, 1), $this->chosen(2, 2), $this->chosen(3, 3),
        ));

        // 1 and 3 are level on 3 — a tie, not a winner picked arbitrarily.
        $this->assertTrue($result->isTie());
        $this->assertSame([1, 3], $result->tiedOptionIds);
        $this->assertNull($result->winnerOptionId);
    }

    public function test_plurality_ignores_blank_ballots_and_options_not_on_the_paper(): void
    {
        $result = Tally::run(TallyMethod::Plurality, [1, 2], [
            Ballot::choosing(99),
            new Ballot([]),
        ]);

        $this->assertSame(0, $result->turnout);
        $this->assertSame([1 => 0, 2 => 0], $result->totals);
        $this->assertTrue($result->isEmpty());
        $this->assertNull($result->winnerOptionId);
    }

    public function test_an_option_nobody_picked_still_appears_at_zero(): void
    {
        $result = Tally::run(TallyMethod::Plurality, [1, 2, 3], $this->chosen(2, 1));

        $this->assertSame([1 => 2, 2 => 0, 3 => 0], $result->totals);
    }

    // ---------------------------------------------------------- average score

    public function test_average_score_means_each_option_and_does_not_sum_to_turnout(): void
    {
        // Option 1: (5+4)/2 = 4.5 · Option 2: (3+3)/2 = 3 · Option 3: (1+2)/2 = 1.5
        $result = Tally::run(TallyMethod::AverageScore, [1, 2, 3], [
            Ballot::scoring([1 => 5, 2 => 3, 3 => 1]),
            Ballot::scoring([1 => 4, 2 => 3, 3 => 2]),
        ]);

        $this->assertSame([1 => 4.5, 2 => 3.0, 3 => 1.5], $result->totals);
        $this->assertSame(1, $result->winnerOptionId);
        $this->assertSame(2, $result->turnout);
        $this->assertNotSame((float) $result->turnout, array_sum($result->totals));
    }

    public function test_average_score_treats_an_unscored_option_as_zero_not_a_division_by_zero(): void
    {
        $result = Tally::run(TallyMethod::AverageScore, [1, 2], [Ballot::scoring([1 => 3])]);

        $this->assertSame([1 => 3.0, 2 => 0.0], $result->totals);
    }

    public function test_average_score_rounds_recurring_decimals_stably(): void
    {
        // (1+1+2)/3 = 1.333… — rounded so a frozen Result serialises identically
        // every time it is recomputed.
        $result = Tally::run(TallyMethod::AverageScore, [1], [
            Ballot::scoring([1 => 1]), Ballot::scoring([1 => 1]), Ballot::scoring([1 => 2]),
        ]);

        $this->assertSame(1.3333, $result->totals[1]);
    }

    // ---------------------------------------------------------- instant runoff

    public function test_instant_runoff_ends_in_one_round_on_a_first_count_majority(): void
    {
        // 3 of 4 is more than half: no elimination needed.
        $result = Tally::run(TallyMethod::InstantRunoff, [1, 2], array_merge(
            $this->ranked(3, [1]), $this->ranked(1, [2]),
        ));

        $this->assertSame(1, $result->winnerOptionId);
        $this->assertSame(1, $result->rounds);
    }

    public function test_instant_runoff_can_defeat_the_first_preference_leader(): void
    {
        // 4x(1>3), 3x(2>3), 2x(3>2). First preferences 4-3-2, nobody past 4.5.
        // Option 3 is eliminated and its 2 ballots pass to option 2, giving
        // 2 five of nine. The leader on first preferences LOSES — the whole
        // point of instant runoff, and the case most likely to be broken by a
        // "simplification" later.
        $result = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], array_merge(
            $this->ranked(4, [1, 3]), $this->ranked(3, [2, 3]), $this->ranked(2, [3, 2]),
        ));

        $this->assertSame(2, $result->winnerOptionId);
        $this->assertSame(2, $result->rounds);

        // Stored totals are FIRST PREFERENCES, so they sum to turnout and the
        // winner is not the largest number.
        $this->assertSame([1 => 4, 2 => 3, 3 => 2], $result->totals);
        $this->assertSame(9, $result->turnout);
        $this->assertSame($result->turnout, array_sum($result->totals));
        $this->assertLessThan(max($result->totals), $result->totals[$result->winnerOptionId]);
    }

    public function test_a_ballot_redistributes_past_a_second_eliminated_candidate(): void
    {
        // 3x(3>2>1), 4x(1), 4x(4), 1x(2).
        // R1: 1=4, 2=1, 3=3, 4=4 of 12 — option 2 out (lowest), its ballot exhausts.
        // R2: 1=4, 3=3, 4=4 of 11 — option 3 out; its 3 ballots skip the already
        //     eliminated 2 and land on 1.
        // R3: 1=7 of 11 — a majority. Option 1 wins in three rounds.
        $result = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3, 4], array_merge(
            $this->ranked(3, [3, 2, 1]), $this->ranked(4, [1]), $this->ranked(4, [4]), $this->ranked(1, [2]),
        ));

        $this->assertSame(1, $result->winnerOptionId);
        $this->assertSame(3, $result->rounds);
    }

    public function test_exhausted_ballots_leave_the_denominator(): void
    {
        // 5x(1), 4x(2), 2x(3 with no second preference).
        // Option 3 is eliminated and its 2 ballots are exhausted, so option 1
        // needs more than half of the NINE continuing ballots, not of eleven.
        // Under the other reading (5 of 11) nobody would ever win.
        $result = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], array_merge(
            $this->ranked(5, [1]), $this->ranked(4, [2]), $this->ranked(2, [3]),
        ));

        $this->assertSame(1, $result->winnerOptionId);
        $this->assertSame(2, $result->rounds);

        // Exhausted ballots still count as turnout — they were cast.
        $this->assertSame(11, $result->turnout);
    }

    public function test_candidates_tied_for_last_are_eliminated_together(): void
    {
        // Options 3 and 4 are both on 1. Batch elimination resolves it in one
        // round rather than needing an arbitrary tiebreak to pick which goes first.
        $result = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3, 4], array_merge(
            $this->ranked(5, [1]), $this->ranked(4, [2]), $this->ranked(1, [3, 1]), $this->ranked(1, [4, 1]),
        ));

        $this->assertSame(1, $result->winnerOptionId);
        $this->assertSame(2, $result->rounds);
    }

    public function test_a_dead_level_contest_is_a_tie_rather_than_an_invented_winner(): void
    {
        $twoWay = Tally::run(TallyMethod::InstantRunoff, [1, 2], array_merge(
            $this->ranked(2, [1]), $this->ranked(2, [2]),
        ));

        $this->assertTrue($twoWay->isTie());
        $this->assertNull($twoWay->winnerOptionId);
        $this->assertSame([1, 2], $twoWay->tiedOptionIds);

        // Three electors each top-ranking a different candidate is 1-1-1:
        // nobody can be eliminated without eliminating everyone.
        $threeWay = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], array_merge(
            $this->ranked(1, [1]), $this->ranked(1, [2]), $this->ranked(1, [3]),
        ));

        $this->assertTrue($threeWay->isTie());
        $this->assertCount(3, $threeWay->tiedOptionIds);
    }

    public function test_instant_runoff_handles_no_ballots_a_single_candidate_and_partial_rankings(): void
    {
        $empty = Tally::run(TallyMethod::InstantRunoff, [1, 2], []);
        $this->assertTrue($empty->isEmpty());
        $this->assertSame(0, $empty->rounds);
        $this->assertNull($empty->winnerOptionId);

        $sole = Tally::run(TallyMethod::InstantRunoff, [1], $this->ranked(3, [1]));
        $this->assertSame(1, $sole->winnerOptionId);
        $this->assertSame(1, $sole->rounds);

        // Rankings of differing lengths, plus a preference for an option that
        // is not on this ballot paper.
        $mixed = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], array_merge(
            $this->ranked(3, [3, 1, 2]), $this->ranked(3, [1]), $this->ranked(2, [2, 1]), $this->ranked(2, [99]),
        ));
        $this->assertSame(8, $mixed->turnout);
    }

    // --------------------------------------------------------------- borda

    public function test_borda_elects_the_compromise_candidate_instant_runoff_discards(): void
    {
        // From real use: four candidates, three voters, each with a different
        // first choice — and all three ranking option 10 SECOND.
        $ballots = [
            Ballot::ranking([9, 10, 11, 12]),
            Ballot::ranking([11, 10, 12, 9]),
            Ballot::ranking([12, 10, 9, 11]),
        ];

        // Instant runoff eliminates 10 FIRST, on zero first preferences,
        // before its second-place support can ever be counted — then the
        // remaining three are level and nobody wins.
        $irv = Tally::run(TallyMethod::InstantRunoff, [9, 10, 11, 12], $ballots);
        $this->assertNull($irv->winnerOptionId);
        $this->assertSame([9, 11, 12], $irv->tiedOptionIds);
        $this->assertSame(0, $irv->totals[10]);

        // Borda counts every place, so the universally-second candidate wins:
        // 2 points on each of three ballots against 3+1+0 for each rival.
        $borda = Tally::run(TallyMethod::Borda, [9, 10, 11, 12], $ballots);
        $this->assertSame(10, $borda->winnerOptionId);
        $this->assertSame([9 => 4, 10 => 6, 11 => 4, 12 => 4], $borda->totals);
    }

    public function test_borda_scores_by_places_ranked_on_that_ballot(): void
    {
        // A voter ranking only two of four options must not inflate their
        // favourite relative to a voter who ranked all four. Here the partial
        // ballot's top choice earns 1 (of 2 places), not 3 (of 4).
        $result = Tally::run(TallyMethod::Borda, [1, 2, 3, 4], [Ballot::ranking([1, 2])]);

        $this->assertSame([1 => 1, 2 => 0, 3 => 0, 4 => 0], $result->totals);
        $this->assertSame(1, $result->turnout);
    }

    public function test_borda_totals_are_points_and_do_not_sum_to_turnout(): void
    {
        $result = Tally::run(TallyMethod::Borda, [1, 2, 3], [
            Ballot::ranking([1, 2, 3]),
            Ballot::ranking([1, 2, 3]),
        ]);

        // 1st=2pts, 2nd=1, 3rd=0, twice over.
        $this->assertSame([1 => 4, 2 => 2, 3 => 0], $result->totals);
        $this->assertSame(2, $result->turnout);
        $this->assertNotSame($result->turnout, array_sum($result->totals));
        $this->assertSame(1, $result->winnerOptionId);
    }

    public function test_borda_ties_when_points_are_level_and_handles_no_ballots(): void
    {
        $tied = Tally::run(TallyMethod::Borda, [1, 2], [
            Ballot::ranking([1, 2]),
            Ballot::ranking([2, 1]),
        ]);
        $this->assertTrue($tied->isTie());
        $this->assertSame([1, 2], $tied->tiedOptionIds);

        $empty = Tally::run(TallyMethod::Borda, [1, 2], []);
        $this->assertTrue($empty->isEmpty());
        $this->assertNull($empty->winnerOptionId);
    }

    public function test_only_ranked_ballots_may_be_tallied_by_borda(): void
    {
        $this->assertTrue(PollResponseShape::RankedChoice->allows(TallyMethod::Borda));
        $this->assertFalse(PollResponseShape::SingleChoice->allows(TallyMethod::Borda));
        $this->assertFalse(PollResponseShape::Rating->allows(TallyMethod::Borda));
    }

    // ------------------------------------------------------ purity & storage

    public function test_the_same_ballots_always_produce_an_identical_result(): void
    {
        // This is what makes a frozen Result checkable years later: a
        // recomputation CHECKS the freeze, so it must not drift.
        $ballots = array_merge($this->ranked(4, [1, 3]), $this->ranked(3, [2, 3]), $this->ranked(2, [3, 2]));

        $first = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], $ballots);
        $second = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], $ballots);

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_a_result_survives_a_json_round_trip(): void
    {
        $result = Tally::run(TallyMethod::InstantRunoff, [1, 2, 3], array_merge(
            $this->ranked(4, [1, 3]), $this->ranked(3, [2, 3]), $this->ranked(2, [3, 2]),
        ));

        $restored = PollResult::fromArray(json_decode(json_encode($result->toArray()), true));

        // JSON stringifies integer keys; they must come back as integers or the
        // option labels cannot be looked up.
        $this->assertSame($result->toArray(), $restored->toArray());
        $this->assertSame(2, $restored->winnerOptionId);
        $this->assertSame(2, $restored->rounds);
    }

    public function test_whole_numbered_averages_survive_json_as_floats(): void
    {
        // json_encode(5.0) emits `5`, so restoring by guessing the decoded type
        // would turn an average of exactly five into an integer and stop it
        // matching the Result that was frozen. The METHOD decides the cast.
        $result = Tally::run(TallyMethod::AverageScore, [1, 2], [Ballot::scoring([1 => 5, 2 => 4])]);

        $restored = PollResult::fromArray(json_decode(json_encode($result->toArray()), true));

        $this->assertSame([1 => 5.0, 2 => 4.0], $restored->totals);
    }
}
