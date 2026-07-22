<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stub checker trigger words
    |--------------------------------------------------------------------------
    |
    | Deterministic triggers for App\Services\Moderation\StubModerationChecker —
    | obviously-fake test words so the moderation pipeline can be exercised
    | end-to-end without a real AI backend or API key. When real AI is bound to
    | CommentModerationCheckerContract this list stops being consulted.
    |
    */

    'trigger_words' => ['moderationtestflag','fuck','shit','cunt','bastard'],

];
