<?php

namespace App\Models\Moderation;

use App\Contracts\Stewardship\CircleStewardshipQueue;
use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Filament\Resources\CommentModerationRecords\CommentModerationRecordResource;
use App\Models\Circles\Circle;
use App\Models\Comment;
use App\Models\Forums\ForumDiscussion;
use App\Models\User;
use App\Support\Moderation\CommentableTypeLabeler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in the unified comment moderation queue. Created either by the AI
 * checker (comments:check-moderation) or a user's "Flag as offensive" click;
 * both sources land here, an admin resolves them from the Governance dashboard.
 *
 * `content` is a snapshot at creation time (not a live reference) so an admin
 * can compare it against `moderated_content` — what the comment became if the
 * author edited it afterwards.
 */
class CommentModerationRecord extends Model implements CircleStewardshipQueue
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

    /** The owning circle at creation time (snapshot; nullable — survives deletion). */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
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
     * Unresolved AI-sourced records — the single definition of "pending AI
     * review". Drives the quarantine display (Comment::pendingAiReview() and the
     * batched list lookup both go through this so the condition never diverges).
     */
    public function scopePendingAi(Builder $query): void
    {
        $query->where('moderated', false)->where('flagged_by', ModerationFlagSource::Ai->value);
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

        // Snapshot the audit context (circle / kind / link) at creation time —
        // resolved through the extensible labeler so a new commentable type is a
        // one-case change, not a hunt through here.
        $commentable = $comment->commentable;

        return static::create([
            'comment_id' => $comment->getKey(),
            'flagged_by' => $source->value,
            'content' => $comment->content,
            'ai_message' => $aiMessage,
            'circle_id' => CommentableTypeLabeler::circleIdFor($commentable),
            'commentable_type_label' => CommentableTypeLabeler::label($comment->commentable_type),
            'url_to_parent' => CommentableTypeLabeler::urlFor($commentable),
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

    /**
     * Edit & Approve: the admin fixes the wording, then approves it in one step.
     * Distinct action (EditedAndApproved) so the queue can tell "approved as-is"
     * from "admin had to fix it first". moderated_content records the new text.
     * (Comment::applyModeratorEdit deliberately does NOT requeue an AI recheck.)
     */
    public function resolveEditedAndApproved(User $admin, string $content): void
    {
        $this->comment?->applyModeratorEdit($admin, $content);

        $this->update([
            'moderated' => true,
            'moderated_as_ok' => true,
            'moderation_action' => ModerationAction::EditedAndApproved,
            'moderated_by_user_id' => $admin->getKey(),
            'moderated_content' => $content,
        ]);
    }

    /**
     * Auto-approve on a clean recheck (the author fixed a previously-flagged
     * comment). Same shape as a human Approve, but moderated_by_user_id = NULL is
     * exactly what marks it system-resolved. Leaves fixed_by_author /
     * moderated_content as set at edit-time.
     */
    public function resolveAutoApproved(): void
    {
        $this->update([
            'moderated' => true,
            'moderated_as_ok' => true,
            'moderation_action' => ModerationAction::Approved,
            'moderated_by_user_id' => null,
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

    /*
    |--------------------------------------------------------------------------
    | CircleStewardshipQueue (surfaced on the per-circle Oversight page)
    |--------------------------------------------------------------------------
    */

    public static function queueLabel(): string
    {
        return 'Comment Moderation';
    }

    public static function pendingCountForCircle(Circle $circle): int
    {
        return static::pendingForCircleQuery($circle)->count();
    }

    public static function oldestPendingAgeForCircle(Circle $circle): ?Carbon
    {
        return static::pendingForCircleQuery($circle)->oldest()->first()?->created_at;
    }

    public static function filamentUrlForCircle(Circle $circle): string
    {
        return CommentModerationRecordResource::getUrl(
            'index',
            ['tableFilters' => ['circle' => ['circle_id' => $circle->id]]],
            panel: 'admin',
        );
    }

    /**
     * Unresolved records whose comment lives in this circle's forums, reached
     * comment → commentable(ForumDiscussion) → group(ForumGroup) → circle_id.
     */
    protected static function pendingForCircleQuery(Circle $circle): Builder
    {
        return static::query()
            ->pending()
            ->whereHas('comment', fn (Builder $c) => $c->whereHasMorph(
                'commentable',
                [ForumDiscussion::class],
                fn (Builder $d) => $d->whereHas('group', fn (Builder $g) => $g->where('circle_id', $circle->id)),
            ));
    }
}
