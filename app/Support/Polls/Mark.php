<?php

namespace App\Support\Polls;

/**
 * One thing a Ballot says about ONE option — the pure-data mirror of a
 * poll_response_items row, carrying no Eloquent and no identity beyond ids.
 *
 *   single_choice — one Mark, everything but optionId null;
 *   ranked_choice — one Mark per ranked option, each with a distinct rank
 *                   (1 = top choice);
 *   rating        — one Mark per option, carrying a scale point.
 *
 * A rating Mark has TWO fields because the stored row and the arithmetic need
 * different things, and conflating them is a real trap:
 *   - `ratingScalePointId` is what a Respondent picked and what is stored;
 *   - `value` is that point's numeric score, resolved from the scale when a
 *     Tally is built. A Tally only ever reads `value`.
 * On the way in, supply the point id; on the way to a Tally, supply the value.
 */
final readonly class Mark
{
    public function __construct(
        public int $optionId,
        public ?int $rank = null,
        public ?int $value = null,
        public ?int $ratingScalePointId = null,
    ) {}

    /** A rating Mark as submitted: the point chosen, before its value is known. */
    public static function scoredWithPoint(int $optionId, int $ratingScalePointId): self
    {
        return new self($optionId, ratingScalePointId: $ratingScalePointId);
    }
}
