<?php

namespace App\Models\Polls;

use App\Enums\Polls\RatingScalePresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, ordered set of labelled points — "Strongly Disagree".."Strongly
 * Agree" — used to score each option in a rating Poll.
 *
 * PLATFORM vocabulary, not a Circle's: curated centrally and shared by every
 * Circle, so the same label means the same thing in two Circles' results.
 * Circle admins PICK a scale, never mint one — deliberately no circle_id.
 */
class PollRatingScale extends Model
{
    protected $guarded = [];

    protected $casts = [
        'presentation' => RatingScalePresentation::class,
    ];

    /** Should the respond form draw this scale as a row of stars? */
    public function rendersAsStars(): bool
    {
        return $this->presentation === RatingScalePresentation::Stars;
    }

    public function points(): HasMany
    {
        return $this->hasMany(PollRatingScalePoint::class)->orderBy('position');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PollQuestion::class, 'rating_scale_id');
    }
}
