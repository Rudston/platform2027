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
