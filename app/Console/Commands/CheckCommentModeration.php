<?php

namespace App\Console\Commands;

use App\Contracts\Moderation\CommentModerationCheckerContract;
use App\Enums\Moderation\ModerationFlagSource;
use App\Models\Comment;
use App\Models\Moderation\CommentModerationRecord;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Run not-yet-checked, non-deleted comments through the moderation checker and
 * queue any offensive ones for admin review. Idempotent: each comment is marked
 * ai_checked_at once, so re-runs skip it (until an edit nulls it again).
 */
class CheckCommentModeration extends Command
{
    protected $signature = 'comments:check-moderation';

    protected $description = 'Check unmoderated comments for offensive content and queue flagged ones for admin review.';

    public function handle(CommentModerationCheckerContract $checker): int
    {
        $checked = 0;
        $flagged = 0;
        $autoResolved = 0;

        Comment::query()
            ->whereNull('ai_checked_at')
            ->where('is_deleted', false)
            // chunkById paginates by id, so stamping ai_checked_at (the filtered
            // column) mid-run never skips or re-processes rows.
            ->chunkById(100, function (Collection $comments) use ($checker, &$checked, &$flagged, &$autoResolved): void {
                foreach ($comments as $comment) {
                    $result = $checker->check($comment->content);

                    // Mark checked regardless of outcome.
                    $comment->update(['ai_checked_at' => now()]);
                    $checked++;

                    if ($result->containsOffensiveContent) {
                        // Still (or newly) offensive → open/reuse the pending record.
                        // A re-flag after an edit stays pending (fixed_by_author is
                        // already set) for a human to review — no auto-resolve.
                        CommentModerationRecord::open($comment, ModerationFlagSource::Ai, $result->message);
                        $flagged++;

                        continue;
                    }

                    // Clean recheck: if this comment had a pending AI flag (the
                    // author has since fixed it), close it out automatically. A
                    // first-time clean check with nothing pending does nothing.
                    $pending = $comment->moderationRecords()->pendingAi()->first();

                    if ($pending !== null) {
                        $pending->resolveAutoApproved();
                        $autoResolved++;
                    }
                }
            });

        $this->info("{$checked} comments checked, {$flagged} flagged, {$autoResolved} auto-resolved");

        return self::SUCCESS;
    }
}
