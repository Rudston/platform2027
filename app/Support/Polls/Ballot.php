<?php

namespace App\Support\Polls;

/**
 * One Respondent's answer, reduced to pure data. Deliberately carries no user
 * id: a Tally never knows who cast what, which is what lets the whole
 * computation be tested — and reasoned about — without the Attribution rules.
 */
final readonly class Ballot
{
    /** @param list<Mark> $marks */
    public function __construct(public array $marks) {}

    /** Convenience for a single-choice ballot. */
    public static function choosing(int $optionId): self
    {
        return new self([new Mark($optionId)]);
    }

    /**
     * Convenience for a ranked ballot: option ids in preference order, best
     * first. Ranks are assigned 1..N from that order.
     *
     * @param list<int> $optionIdsInOrder
     */
    public static function ranking(array $optionIdsInOrder): self
    {
        $marks = [];

        foreach (array_values($optionIdsInOrder) as $index => $optionId) {
            $marks[] = new Mark($optionId, rank: $index + 1);
        }

        return new self($marks);
    }

    /**
     * Convenience for a rating ballot.
     *
     * @param array<int,int> $valuesByOptionId
     */
    public static function scoring(array $valuesByOptionId): self
    {
        $marks = [];

        foreach ($valuesByOptionId as $optionId => $value) {
            $marks[] = new Mark((int) $optionId, value: $value);
        }

        return new self($marks);
    }

    /** The single option chosen, or null if this ballot marks nothing. */
    public function chosenOptionId(): ?int
    {
        return $this->marks[0]->optionId ?? null;
    }

    /**
     * Option ids in preference order, best first. Marks with no rank are
     * ignored, so a ballot that mixes shapes cannot silently pollute a ranked
     * count.
     *
     * @return list<int>
     */
    public function preferenceOrder(): array
    {
        $ranked = array_filter($this->marks, fn (Mark $m): bool => $m->rank !== null);

        usort($ranked, fn (Mark $a, Mark $b): int => $a->rank <=> $b->rank);

        return array_map(fn (Mark $m): int => $m->optionId, array_values($ranked));
    }

    /**
     * Scores by option id. Marks with no value are ignored.
     *
     * @return array<int,int>
     */
    public function scores(): array
    {
        $scores = [];

        foreach ($this->marks as $mark) {
            if ($mark->value !== null) {
                $scores[$mark->optionId] = $mark->value;
            }
        }

        return $scores;
    }
}
