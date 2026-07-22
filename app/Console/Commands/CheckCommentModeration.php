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

        Comment::query()
            ->whereNull('ai_checked_at')
            ->where('is_deleted', false)
            // chunkById paginates by id, so stamping ai_checked_at (the filtered
            // column) mid-run never skips or re-processes rows.
            ->chunkById(100, function (Collection $comments) use ($checker, &$checked, &$flagged): void {
                foreach ($comments as $comment) {
                    $result = $checker->check($comment->content);

                    // Mark checked regardless of outcome.
                    $comment->update(['ai_checked_at' => now()]);
                    $checked++;

                    if ($result->containsOffensiveContent) {
                        CommentModerationRecord::open($comment, ModerationFlagSource::Ai, $result->message);
                        $flagged++;
                    }
                }
            });

        $this->info("{$checked} comments checked, {$flagged} flagged for moderation");

        return self::SUCCESS;
    }
}
