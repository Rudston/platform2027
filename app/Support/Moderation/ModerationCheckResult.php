<?php

namespace App\Support\Moderation;

/**
 * The outcome of running a piece of content through a moderation checker.
 * Deliberately minimal — a real AI backend returns the same shape as the stub.
 */
final class ModerationCheckResult
{
    public function __construct(
        public readonly bool $containsOffensiveContent,
        public readonly ?string $message = null,
    ) {}
}
