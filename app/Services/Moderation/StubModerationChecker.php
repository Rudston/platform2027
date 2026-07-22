<?php

namespace App\Services\Moderation;

use App\Contracts\Moderation\CommentModerationCheckerContract;
use App\Support\Moderation\ModerationCheckResult;

/**
 * Deterministic, no-external-call moderation checker. Flags content that
 * contains any configured trigger word (config/moderation.php) — obviously-fake
 * test words so the whole pipeline is exercisable end-to-end without an API key.
 * Everything else is treated as clean. Real AI replaces this via the container
 * binding only; never reference this class directly — resolve the contract.
 */
class StubModerationChecker implements CommentModerationCheckerContract
{
    public function check(string $content): ModerationCheckResult
    {
        $haystack = mb_strtolower($content);

        $hits = [];
        foreach ((array) config('moderation.trigger_words', []) as $word) {
            $word = (string) $word;

            if ($word !== '' && str_contains($haystack, mb_strtolower($word))) {
                $hits[] = $word;
            }
        }

        if ($hits === []) {
            return new ModerationCheckResult(false);
        }

        return new ModerationCheckResult(true, 'Contains flagged term(s): '.implode(', ', $hits));
    }
}
