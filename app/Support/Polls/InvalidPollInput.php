<?php

namespace App\Support\Polls;

use InvalidArgumentException;

/**
 * A user-triggerable refusal of poll INPUT (an illegal ballot or amendment).
 * Extends InvalidArgumentException so existing callers and tests keep their
 * catches.
 */
class InvalidPollInput extends InvalidArgumentException implements TranslatableRefusal
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
