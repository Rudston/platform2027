<?php

use App\Models\Communication\Request;
use App\Models\Moderation\CommentModerationRecord;

/*
|--------------------------------------------------------------------------
| Circle stewardship queue registry
|--------------------------------------------------------------------------
|
| Fully-qualified class names implementing
| App\Contracts\Stewardship\CircleStewardshipQueue. The per-circle Oversight
| page iterates this list to render one health row per queue — adding a new
| queue is a one-line change here, nothing else.
|
*/

return [
    Request::class,
    CommentModerationRecord::class,
];
