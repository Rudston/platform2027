<?php

namespace App\Enums\Polls;

/**
 * A pure computation over the responses a Poll already holds, producing its
 * Result.
 *
 * Anything requiring a FURTHER ROUND of voting is not a Tally Method and does
 * not belong here: majority-runoff spawns a second Poll rather than computing
 * over the first, which is why it remains deferred rather than merely
 * unimplemented.
 *
 * InstantRunoff and Borda read the SAME ballot and can disagree entirely, so
 * the choice is the organiser's and belongs on the poll:
 *  - InstantRunoff asks "who can command a majority?" and only ever counts a
 *    ballot for its highest surviving preference. A candidate with no first
 *    preferences is eliminated first, however widely they are liked second.
 *  - Borda asks "who is most broadly acceptable?" — every place on every
 *    ballot scores. It elects the compromise candidate IRV discards, at the
 *    cost of being gameable: ranking a strong rival last drags their score
 *    down, which IRV does not reward.
 */
enum TallyMethod: string
{
    case Plurality     = 'plurality';
    case InstantRunoff = 'instant_runoff';
    case Borda         = 'borda_count';
    case AverageScore  = 'average_score';
}
