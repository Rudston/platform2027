<?php

namespace App\Enums\Polls;

/**
 * A pure computation over the responses a Poll already holds, producing its
 * Result.
 *
 * Anything requiring a FURTHER ROUND of voting is not a Tally Method and does
 * not belong here: majority-runoff spawns a second Poll rather than computing
 * over the first, which is why it is deferred rather than merely
 * unimplemented. Borda count is deferred too — single-round and cheap, but
 * nothing needs it yet. Adding either later is a case here plus a line in
 * PollResponseShape::allowedTallyMethods(), never a schema change.
 */
enum TallyMethod: string
{
    case Plurality     = 'plurality';
    case InstantRunoff = 'instant_runoff';
    case AverageScore  = 'average_score';
}
