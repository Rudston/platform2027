<?php

namespace App\Enums\Forums;

enum ForumGroupVisibility: string
{
    case Public   = 'public';
    case Private  = 'private';
    case Internal = 'internal';

    /**
     * The visibility level that governs PARTICIPATION for this group. Private
     * and Internal already are their own floor; Public is bumped to Private
     * (i.e. anyone can VIEW a public group, but only members participate).
     *
     * This is the single definition of the view→participate relationship — do
     * not duplicate it elsewhere.
     */
    public function participationFloor(): self
    {
        return $this === self::Public ? self::Private : $this;
    }
}
