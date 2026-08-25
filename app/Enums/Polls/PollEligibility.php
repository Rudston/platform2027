<?php

namespace App\Enums\Polls;

/**
 * Who may respond to a Poll. The same underlying concept as forum
 * participation — a membership test against the circle — kept in its own enum
 * so the two can diverge later, and deliberately named with
 * ForumGroupVisibility's vocabulary so the shared origin stays legible.
 *
 * There is no Public case: a Poll is never answerable from outside its circle.
 */
enum PollEligibility: string
{
    case Private  = 'private';
    case Internal = 'internal';
}
