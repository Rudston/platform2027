<?php

namespace App\Models\Polls;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a response says about ONE option:
 *   single_choice — exactly one item, rank and rating point both null;
 *   ranked_choice — one item per ranked option, each with a distinct rank;
 *   rating        — one item per option, each with a rating scale point.
 */
class PollResponseItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rank' => 'integer',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(PollResponse::class, 'poll_response_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function ratingScalePoint(): BelongsTo
    {
        return $this->belongsTo(PollRatingScalePoint::class, 'rating_scale_point_id');
    }
}
