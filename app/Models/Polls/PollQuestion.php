<?php

namespace App\Models\Polls;

use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Structural grouping of a Poll's options. NEVER surfaces in the UI as
 * "question" — `text` is the Prompt a Respondent reads. Exists in this shape
 * so a future Surveys service can hold several per Poll; Polls produces one.
 */
class PollQuestion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => PollResponseShape::class,
        'tally_method' => TallyMethod::class,
        'require_full_ranking' => 'boolean',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    public function ratingScale(): BelongsTo
    {
        return $this->belongsTo(PollRatingScale::class, 'rating_scale_id');
    }

    /** The Response Shape — what a Respondent physically does. */
    public function shape(): PollResponseShape
    {
        return $this->type;
    }

    public function isSingleChoice(): bool
    {
        return $this->type === PollResponseShape::SingleChoice;
    }

    public function isRankedChoice(): bool
    {
        return $this->type === PollResponseShape::RankedChoice;
    }

    public function isRating(): bool
    {
        return $this->type === PollResponseShape::Rating;
    }

    /**
     * Whether the stored pairing is legal. The rule itself lives ONLY in
     * PollResponseShape::allowedTallyMethods(); this is a guard against rows
     * written before a Shape's allowed set changed, never a second definition.
     */
    public function hasLegalTallyMethod(): bool
    {
        return $this->type->allows($this->tally_method);
    }
}
