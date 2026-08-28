<?php

namespace App\Support\Polls;

use RuntimeException;

/**
 * A user-triggerable refusal of a poll state change (lifecycle, responding).
 * Extends RuntimeException so existing callers and tests keep their catches.
 */
class PollRefusal extends RuntimeException implements TranslatableRefusal
{
    public function __construct(
        private readonly string $translationKey,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }
}
