<?php

namespace App\Models\Polls;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Respondent's answer. Identity is ALWAYS stored — withholding Attribution
 * governs display, not storage, which is precisely why no Poll here is a secret
 * ballot and must never be described as one.
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
     * Whether $user may see WHAT this response chose: the Respondent
     * themselves, and no one else. Not the Organiser, not a circle admin, not a
     * platform admin, not a superadmin.
     *
     * There is deliberately no Poll argument and no flag to consult — US35 asks
     * for "a real guarantee and not a courtesy", and a guarantee with a switch
     * beside it is a courtesy (docs/adr/0004). If Attribution is ever wanted as
     * a per-Poll choice, it needs its own decision, not a condition here.
     */
    public function isChoiceVisibleTo(?User $user): bool
    {
        return $user !== null && $user->getKey() === $this->user_id;
    }
}
