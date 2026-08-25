<?php

namespace App\Models\Polls;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Respondent's answer. Identity is ALWAYS stored, even when the Poll
 * withholds Attribution — that flag governs display, not storage, which is
 * precisely why no Poll here is a secret ballot.
 *
 * One row per Respondent per question, enforced by a unique index. When the
 * Poll allows revision this row is updated in place and submitted_at
 * refreshed, rather than a second row inserted.
 */
class PollResponse extends Model
{
    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(PollQuestion::class, 'poll_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PollResponseItem::class);
    }

    /**
     * Whether $user may see WHAT this response chose. Only the Respondent
     * themselves, and only when the Poll withholds Attribution — no role,
     * including the Organiser and superadmin, is granted another user's
     * choice. When Attribution is not withheld, results are attributed and
     * anyone who can see the Result can see this.
     */
    public function isChoiceVisibleTo(?User $user, Poll $poll): bool
    {
        if (! $poll->hide_voter_identities) {
            return true;
        }

        return $user !== null && $user->getKey() === $this->user_id;
    }
}
