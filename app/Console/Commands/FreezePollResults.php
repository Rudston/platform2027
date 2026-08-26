<?php

namespace App\Console\Commands;

use App\Enums\Polls\PollStatus;
use App\Models\Polls\Poll;
use App\Services\Circles\VotingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Freeze the Result of every poll that has Closed and has not been frozen yet.
 *
 * WHY THIS EXISTS, given closing needs no cron: a Result is the decision of
 * record, and freezing it is insurance against the TALLY CODE CHANGING under a
 * settled decision. A rounding fix, an instant-runoff tiebreak adjustment, or
 * adding a new method later would all mean an unfrozen old poll silently
 * adopting the new rule the next time anyone looked at it. Freezing on read
 * alone gives that protection only to polls someone happened to open — and an
 * election's result matters most in the circles nobody is watching closely.
 *
 * THIS DOES NOT VIOLATE ADR-0001. That rule is not "no scheduled jobs" — the
 * scheduler already runs requests:expire and comments:check-moderation. It is
 * narrower: no scheduled job may write poll STATE. This writes `result` and
 * `result_frozen_at` and NOTHING else. Do not extend it to touch `status`,
 * `closes_at` or `archived_at`; closing stays derived from the clock.
 *
 * Idempotent and adds-only: VotingService::freezeResult returns an
 * already-frozen poll untouched, so a re-run can never rewrite a decision.
 * PollPage still freezes on first read, which keeps a result immediate for a
 * visitor rather than waiting for the next tick; the two are safe together
 * precisely because freezing never overwrites.
 */
class FreezePollResults extends Command
{
    protected $signature = 'polls:freeze-results';

    protected $description = 'Freeze the Result of any poll that has closed without one.';

    public function handle(VotingService $service): int
    {
        $frozen = 0;
        $skipped = 0;

        Poll::query()
            // Cancelled polls are excluded at the query: their responses must
            // never be tallied, so they have no Result by definition.
            ->whereIn('status', [PollStatus::Published->value, PollStatus::Concluded->value])
            ->whereNull('result')
            ->whereNotNull('closes_at')
            ->where('closes_at', '<', now())
            // chunkById paginates by id, so writing `result` mid-run — a column
            // the filter depends on — can never skip or re-process a row.
            ->chunkById(100, function (Collection $polls) use ($service, &$frozen, &$skipped): void {
                foreach ($polls as $poll) {
                    // isClosed() is the authority, not the query: a Published
                    // poll is Closed only once its window has passed, and the
                    // WHERE above is a narrowing filter rather than the rule.
                    if (! $poll->isClosed() || $poll->isCancelled()) {
                        $skipped++;

                        continue;
                    }

                    $service->freezeResult($poll);
                    $frozen++;
                }
            });

        $this->info("Froze {$frozen} poll result(s)".($skipped > 0 ? ", skipped {$skipped}." : '.'));

        return self::SUCCESS;
    }
}
