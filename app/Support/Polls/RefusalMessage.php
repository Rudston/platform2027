<?php

namespace App\Support\Polls;

/**
 * The ONE place a service refusal becomes something a user reads.
 *
 * What the UI shows is the refusal's lang KEY, translated — never the
 * exception's own message, which names ids and rules for developers and
 * belongs in logs. A refusal with no key (a plain SPL exception) is an
 * invariant the user could not have caused, so it gets the generic line.
 *
 * Every Livewire component that catches a VotingService refusal goes through
 * here, so the rule cannot fork between call sites.
 */
class RefusalMessage
{
    public static function for(\Throwable $e): string
    {
        return __($e instanceof TranslatableRefusal ? $e->translationKey() : 'polls.refusals.generic');
    }
}
