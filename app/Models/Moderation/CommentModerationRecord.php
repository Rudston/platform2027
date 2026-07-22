<?php

namespace App\Models\Moderation;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the unified comment moderation queue. Created either by the AI
 * checker (comments:check-moderation) or a user's "Flag as offensive" click;
 * both sources land here, an admin resolves them from the Governance dashboard.
 *
 * `content` is a snapshot at creation time (not a live reference) so an admin
 * can compare it against `moderated_content` — what the comment became if the
 * author edited it afterwards.
 */
class CommentModerationRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'flagged_by' => ModerationFlagSource::class,
        'moderation_action' => ModerationAction::class,
        'moderated' => 'boolean',
        'moderated_as_ok' => 'boolean',
        'fixed_by_author' => 'boolean',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** The admin who resolved this record, if any. */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    /** Unresolved records — the queue an admin still needs to action. */
    public function scopePending(Builder $query): void
    {
        $query->where('moderated', false);
    }

    /**
     * Open a PENDING record for a comment from a given source, create-or-reuse:
     * if an unmoderated record already exists for this comment+source, return it
     * rather than stacking a duplicate. Ai and User sources legitimately coexist
     * as separate rows. Content is snapshotted the first time a row is created.
     */
    public static function open(Comment $comment, ModerationFlagSource $source, ?string $aiMessage = null): self
    {
        $existing = static::query()
            ->where('comment_id', $comment->getKey())
            ->where('flagged_by', $source->value)
            ->where('moderated', false)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'comment_id' => $comment->getKey(),
            'flagged_by' => $source->value,
            'content' => $comment->content,
            'ai_message' => $aiMessage,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin resolutions (the three Filament row actions delegate here)
    |--------------------------------------------------------------------------
    | Kept on the model so the resolution effect is unit-testable without the
    | Filament layer. Caller authorization is enforced by the resource (admin/
    | superadmin, later scoped to circle_admin); the comment-mutating variants
    | additionally re-check via Comment::hide()/deleteBy().
    */

    /** Approve: the comment stands; only this record is resolved. */
    public function resolveApproved(User $admin): void
    {
        $this->update([
            'moderated' => true,
            'moderated_as_ok' => true,
            'moderation_action' => ModerationAction::Approved,
            'moderated_by_user_id' => $admin->getKey(),
        ]);
    }

    /** Hide the underlying comment, then resolve this record as Hidden. */
    public function resolveHidden(User $admin): void
    {
        $this->update([
            'moderated' => true,
            'moderation_action' => ModerationAction::Hidden,
            'moderated_by_user_id' => $admin->getKey(),
        ]);

        $this->comment?->hide($admin);
    }

    /**
     * Delete the underlying comment (existing tombstone-if-has-replies logic),
     * then resolve this record as Deleted. Record fields are written BEFORE the
     * delete so the tombstone case keeps the audit; a hard delete cascades this
     * row away (nothing left to moderate), which is expected.
     */
    public function resolveDeleted(User $admin): void
    {
        $this->update([
            'moderated' => true,
            'moderation_action' => ModerationAction::Deleted,
            'moderated_by_user_id' => $admin->getKey(),
        ]);

        $this->comment?->deleteBy($admin);
    }
}
