<?php

namespace App\Support\Polls;

use App\Enums\Polls\TallyMethod;

/**
 * Turns a Poll's Ballots into a PollResult. PURE: no Eloquent, no database, no
 * user identities, no clock — the same inputs always give the same Result,
 * which is what makes a frozen Result checkable by recomputation years later.
 *
 * Nothing here knows about eligibility. Whether a Ballot should exist was
 * decided when it was cast (docs/adr/0002); a Tally counts what it is given
 * and never filters.
 */
final class Tally
{
    /**
     * @param  list<int>  $optionIds  every option on the ballot, including ones nobody picked
     * @param  list<Ballot>  $ballots
     */
    public static function run(TallyMethod $method, array $optionIds, array $ballots): PollResult
    {
        $optionIds = array_values(array_unique($optionIds));

        return match ($method) {
            TallyMethod::Plurality => self::plurality($optionIds, $ballots),
            TallyMethod::InstantRunoff => self::instantRunoff($optionIds, $ballots),
            TallyMethod::Borda => self::borda($optionIds, $ballots),
            TallyMethod::AverageScore => self::averageScore($optionIds, $ballots),
        };
    }

    /**
     * Most first-choice votes wins — no majority required. Totals sum to
     * turnout.
     *
     * @param  list<int>  $optionIds
     * @param  list<Ballot>  $ballots
     */
    private static function plurality(array $optionIds, array $ballots): PollResult
    {
        $totals = array_fill_keys($optionIds, 0);
        $turnout = 0;

        foreach ($ballots as $ballot) {
            $chosen = $ballot->chosenOptionId();

            // A ballot marking nothing, or marking an option not on this
            // ballot paper, is not a vote and is not counted in turnout.
            if ($chosen === null || ! array_key_exists($chosen, $totals)) {
                continue;
            }

            $totals[$chosen]++;
            $turnout++;
        }

        return self::decide(TallyMethod::Plurality, $totals, $turnout);
    }

    /**
     * The mean of the scores each option was given. An option nobody scored
     * stays at 0. Totals do NOT sum to turnout.
     *
     * @param  list<int>  $optionIds
     * @param  list<Ballot>  $ballots
     */
    private static function averageScore(array $optionIds, array $ballots): PollResult
    {
        $sums = array_fill_keys($optionIds, 0);
        $counts = array_fill_keys($optionIds, 0);
        $turnout = 0;

        foreach ($ballots as $ballot) {
            $scores = $ballot->scores();
            $counted = false;

            foreach ($scores as $optionId => $value) {
                if (! array_key_exists($optionId, $sums)) {
                    continue;
                }

                $sums[$optionId] += $value;
                $counts[$optionId]++;
                $counted = true;
            }

            if ($counted) {
                $turnout++;
            }
        }

        $totals = [];

        foreach ($optionIds as $optionId) {
            // Rounded so a frozen Result serialises identically every time it
            // is recomputed — a Result that differs in the twelfth decimal
            // place would read as tampering.
            $totals[$optionId] = $counts[$optionId] === 0
                ? 0.0
                : round($sums[$optionId] / $counts[$optionId], 4);
        }

        return self::decide(TallyMethod::AverageScore, $totals, $turnout);
    }

    /**
     * Count first preferences; if nobody has MORE THAN HALF of the continuing
     * ballots, eliminate the lowest and redistribute those ballots to their
     * next surviving preference. Repeat until someone crosses the line.
     *
     * Rules this implementation commits to, each of which real-world IRV
     * varies on:
     *  - the majority is measured against CONTINUING ballots, so a ballot
     *    whose every ranked option has been eliminated (an "exhausted" ballot)
     *    leaves the denominator rather than propping up the threshold;
     *  - candidates tied for last are eliminated TOGETHER, which is
     *    deterministic where "pick one of the tied" needs an arbitrary
     *    tiebreak;
     *  - if that would eliminate everyone still standing, the contest is a tie
     *    and no winner is declared;
     *  - unranked options are simply absent from a ballot's preference order,
     *    so partial rankings work without special handling.
     *
     * @param  list<int>  $optionIds
     * @param  list<Ballot>  $ballots
     */
    private static function instantRunoff(array $optionIds, array $ballots): PollResult
    {
        $preferences = [];
        $firstPreferences = array_fill_keys($optionIds, 0);

        foreach ($ballots as $ballot) {
            $order = array_values(array_filter(
                $ballot->preferenceOrder(),
                fn (int $id): bool => array_key_exists($id, $firstPreferences),
            ));

            if ($order === []) {
                continue;
            }

            $preferences[] = $order;
            $firstPreferences[$order[0]]++;
        }

        $turnout = count($preferences);

        if ($turnout === 0) {
            return new PollResult(TallyMethod::InstantRunoff, $firstPreferences, 0, rounds: 0);
        }

        $eliminated = [];
        $round = 0;

        while (true) {
            $round++;

            $counts = array_fill_keys(
                array_values(array_diff($optionIds, $eliminated)),
                0,
            );
            $continuing = 0;

            foreach ($preferences as $order) {
                foreach ($order as $optionId) {
                    if (! array_key_exists($optionId, $counts)) {
                        continue; // eliminated — fall through to the next preference
                    }

                    $counts[$optionId]++;
                    $continuing++;
                    break;
                }
                // Every preference eliminated: the ballot is exhausted and
                // counts towards nothing from here on.
            }

            // Everyone exhausted before anyone won: nothing can be declared.
            if ($continuing === 0) {
                return new PollResult(
                    TallyMethod::InstantRunoff, $firstPreferences, $turnout,
                    tiedOptionIds: array_keys($counts), rounds: $round,
                );
            }

            $highest = max($counts);

            // Strictly more than half of the continuing ballots.
            if ($highest * 2 > $continuing) {
                return new PollResult(
                    TallyMethod::InstantRunoff, $firstPreferences, $turnout,
                    winnerOptionId: array_keys($counts, $highest, true)[0], rounds: $round,
                );
            }

            $lowest = min($counts);
            $trailing = array_keys($counts, $lowest, true);

            // Nobody can be eliminated without eliminating everyone left —
            // the survivors are level, so the contest is tied.
            if (count($trailing) === count($counts)) {
                return new PollResult(
                    TallyMethod::InstantRunoff, $firstPreferences, $turnout,
                    tiedOptionIds: array_keys($counts), rounds: $round,
                );
            }

            $eliminated = array_merge($eliminated, $trailing);
        }
    }

    /**
     * Every place on a ballot scores: with N options a first preference is
     * worth N-1, a second N-2, and the last 0. Highest total wins.
     *
     * Unlike instant-runoff this never eliminates anyone, so a candidate who
     * is nobody's first choice but everybody's second can win — which is the
     * point of offering it. Points are scored against the number of options
     * RANKED ON THAT BALLOT, so a voter who ranks only some options does not
     * inflate their favourite relative to a voter who ranked them all.
     *
     * Totals are points, not votes: they do NOT sum to turnout.
     *
     * @param  list<int>  $optionIds
     * @param  list<Ballot>  $ballots
     */
    private static function borda(array $optionIds, array $ballots): PollResult
    {
        $totals = array_fill_keys($optionIds, 0);
        $turnout = 0;

        foreach ($ballots as $ballot) {
            $order = array_values(array_filter(
                $ballot->preferenceOrder(),
                fn (int $id): bool => array_key_exists($id, $totals),
            ));

            if ($order === []) {
                continue;
            }

            $places = count($order);

            foreach ($order as $place => $optionId) {
                $totals[$optionId] += $places - 1 - $place;
            }

            $turnout++;
        }

        return self::decide(TallyMethod::Borda, $totals, $turnout);
    }

    /**
     * Highest total wins; level leaders are a tie with no winner. Shared by
     * the two single-pass methods so "what counts as winning" is defined once.
     *
     * @param  array<int,int|float>  $totals
     */
    private static function decide(TallyMethod $method, array $totals, int $turnout): PollResult
    {
        if ($turnout === 0 || $totals === []) {
            return new PollResult($method, $totals, $turnout);
        }

        $highest = max($totals);
        $leaders = array_keys($totals, $highest, true);

        return count($leaders) === 1
            ? new PollResult($method, $totals, $turnout, winnerOptionId: $leaders[0])
            : new PollResult($method, $totals, $turnout, tiedOptionIds: $leaders);
    }
}
