<?php

namespace App\Models\Polls;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One choice on a ballot. A candidate and a proposal differ only in the label
 * — an election and a proposition are structurally identical.
 */
class PollOption extends Model
{
    protected $guarded = [];

    public function question(): BelongsTo
    {
        return $this->belongsTo(PollQuestion::class, 'poll_question_id');
    }

    public function responseItems(): HasMany
    {
        return $this->hasMany(PollResponseItem::class);
    }
}
