<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mark expired pending requests each day.
Schedule::command('requests:expire')->daily();

// Run unchecked comments through the moderation checker frequently. Cadence is
// cheap to change; it only starts to matter once a real (paid) AI backend is
// bound in place of the stub.
Schedule::command('comments:check-moderation')->everyTenMinutes();

// Freeze the Result of any poll that has closed without one. Insurance against
// the tally code changing under a settled decision — see the command's docblock.
// It writes `result` only and never poll STATE, which is what keeps it
// compatible with ADR-0001's "closing is derived from the clock" rule.
Schedule::command('polls:freeze-results')->hourly();
