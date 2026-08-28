<?php

namespace App\Support\Polls;

/**
 * A service refusal that the UI is allowed to show a user.
 *
 * The exception MESSAGE stays developer-facing — it names ids and rules for
 * the log. The translationKey() is what the UI renders instead, so nothing a
 * respondent or organiser sees is a raw English exception string. Refusals
 * that can only mean a programming error (an invariant, not a user act) keep
 * their plain SPL exceptions and the UI falls back to polls.refusals.generic.
 */
interface TranslatableRefusal
{
    public function translationKey(): string;
}
