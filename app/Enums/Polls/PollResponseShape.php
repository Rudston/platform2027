<?php

namespace App\Enums\Polls;

/**
 * What a Respondent physically does when answering: pick one option, rank
 * several, or score several. Distinct from the Tally Method — one describes
 * the ballot, the other the arithmetic — and the Shape decides which Tally
 * Methods are legal.
 *
 * Backs `poll_questions.type`. A future 'free_text' case is anticipated for
 * Surveys; not built now.
 */
enum PollResponseShape: string
{
    case SingleChoice = 'single_choice';
    case RankedChoice = 'ranked_choice';
    case Rating       = 'rating';

    /**
     * The ONLY definition of which Tally Methods a Shape permits — the same
     * single-source-of-truth pattern as allowedInternalRoles() on a community
     * type. The creation UI picks a Shape first and offers only these, so no
     * invalid pairing is ever reachable.
     *
     * @return list<TallyMethod>
     */
    public function allowedTallyMethods(): array
    {
        return match ($this) {
            self::SingleChoice => [TallyMethod::Plurality],
            self::RankedChoice => [TallyMethod::InstantRunoff, TallyMethod::Borda],
            self::Rating       => [TallyMethod::AverageScore],
        };
    }

    /** Is this Tally Method legal for this Shape? */
    public function allows(TallyMethod $method): bool
    {
        return in_array($method, $this->allowedTallyMethods(), true);
    }
}
