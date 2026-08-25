<?php

namespace App\Models\Polls;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One labelled step on a Rating Scale, with the numeric value used in tallying. */
class PollRatingScalePoint extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'integer',
        'position' => 'integer',
    ];

    public function scale(): BelongsTo
    {
        return $this->belongsTo(PollRatingScale::class, 'poll_rating_scale_id');
    }
}
