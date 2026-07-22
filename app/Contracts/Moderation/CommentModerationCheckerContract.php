<?php

namespace App\Contracts\Moderation;

use App\Support\Moderation\ModerationCheckResult;

/**
 * Checks comment content for offensive material. The stub implementation is
 * bound today; a real AI backend (OpenAI, or a future local LLM) swaps in by
 * changing ONLY the container binding — nothing else references a concrete
 * implementation.
 */
interface CommentModerationCheckerContract
{
    public function check(string $content): ModerationCheckResult;
}
