<?php

namespace App\Support\Polls;

use App\Enums\Polls\TallyMethod;

/**
 * A Poll's frozen outcome: per-option totals, turnout, and the winning option.
 *
 * WHAT `totals` MEANS depends on the method, and the difference matters to
 * anyone checking a Result by hand:
 *  - plurality      — vote counts. They SUM TO TURNOUT, which is the check a
 *                     member performs against the Roster's length.
 *  - instant_runoff — FIRST-PREFERENCE counts, which also sum to turnout. The
 *                     winner comes from the full elimination, so the highest
 *                     total is NOT always the winner; `rounds` says how many
 *                     counts it took. Displaying this needs a word of
 *                     explanation, or it reads as a mistake.
 *  - average_score  — mean score per option. These do NOT sum to turnout.
 *
 * Deliberately NOT recorded: the elimination sequence itself. Recomputing from
 * the stored responses reproduces it exactly, and a recomputation CHECKS a
 * frozen Result rather than replacing it.
 */
final readonly class PollResult
{
    /**
     * @param  array<int,int|float>  $totals  option id => total
     * @param  list<int>  $tiedOptionIds  contenders that could not be separated
     */
    public function __construct(
        public TallyMethod $method,
        public array $totals,
        public int $turnout,
        public ?int $winnerOptionId = null,
        public array $tiedOptionIds = [],
        public ?int $rounds = null,
    ) {}

    /** No winner could be declared because the leaders were level. */
    public function isTie(): bool
    {
        return $this->winnerOptionId === null && count($this->tiedOptionIds) > 1;
    }

    /** Nobody responded, so there is nothing to declare. */
    public function isEmpty(): bool
    {
        return $this->turnout === 0;
    }

    /**
     * One option's total, formatted for reading.
     *
     * Presentation, but it lives here because the rule follows the METHOD —
     * means read to one decimal, counts are whole — and the method is this
     * object's own field. Putting the switch in a view would duplicate
     * knowledge only this class holds.
     *
     * Rounding is DISPLAY ONLY. The stored value keeps its full precision:
     * rounding at tally time would decide winners on rounded numbers and could
     * manufacture ties between totals that genuinely differ.
     */
    public function formattedTotal(int|string $optionId): string
    {
        $total = $this->totals[$optionId] ?? 0;

        return $this->method === TallyMethod::AverageScore
            ? number_format((float) $total, 1)
            : (string) $total;
    }

    /** The shape stored in polls.result. */
    public function toArray(): array
    {
        return [
            'method' => $this->method->value,
            'totals' => $this->totals,
            'turnout' => $this->turnout,
            'winner_option_id' => $this->winnerOptionId,
            'tied_option_ids' => $this->tiedOptionIds,
            'rounds' => $this->rounds,
        ];
    }

    public static function fromArray(array $data): self
    {
        $method = TallyMethod::from($data['method']);

        return new self(
            method: $method,
            totals: self::restoreTotals($data['totals'] ?? [], $method),
            turnout: (int) ($data['turnout'] ?? 0),
            winnerOptionId: isset($data['winner_option_id']) ? (int) $data['winner_option_id'] : null,
            tiedOptionIds: array_map('intval', $data['tied_option_ids'] ?? []),
            rounds: isset($data['rounds']) ? (int) $data['rounds'] : null,
        );
    }

    /**
     * JSON loses two things a Result depends on, and the METHOD is what
     * restores both — never guess from the decoded value:
     *  - integer option keys come back as strings;
     *  - a whole-numbered float is emitted bare (5.0 encodes as `5`), so an
     *    average of exactly 5 would otherwise return as an int and no longer
     *    match the Result that was frozen.
     *
     * @return array<int,int|float>
     */
    private static function restoreTotals(array $totals, TallyMethod $method): array
    {
        $isMean = $method === TallyMethod::AverageScore;
        $restored = [];

        foreach ($totals as $optionId => $total) {
            $restored[(int) $optionId] = $isMean ? (float) $total : (int) $total;
        }

        return $restored;
    }
}
