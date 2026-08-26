<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Carbon::inDisplayZone() macro every absolute date render goes through.
 *
 * Registered in AppServiceProvider so views can read naturally
 * (`$date->inDisplayZone()->format(...)`) rather than naming the helper each
 * time. Storage stays UTC; only what a human reads is converted.
 */
class DisplayZoneMacroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Johannesburg']);
    }

    public function test_it_shifts_an_instant_into_the_display_wall_clock(): void
    {
        $utc = Carbon::parse('2026-08-26 10:21:00', 'UTC');

        $this->assertSame('12:21', $utc->inDisplayZone()->format('H:i'));
    }

    public function test_it_corrects_the_date_across_midnight(): void
    {
        // The quiet half of the bug: a date-only render is wrong for two hours
        // every night. A comment posted at 00:40 SAST was showing the previous
        // day, because 22:40 UTC is still "yesterday".
        $utc = Carbon::parse('2026-08-26 22:40:00', 'UTC');

        $this->assertSame('26 Aug 2026', $utc->format('d M Y'), 'stored instant is still the 26th in UTC');
        $this->assertSame('27 Aug 2026', $utc->inDisplayZone()->format('d M Y'), 'but locally it is already the 27th');
    }

    public function test_it_does_not_mutate_the_original(): void
    {
        $utc = Carbon::parse('2026-08-26 10:21:00', 'UTC');
        $utc->inDisplayZone();

        $this->assertSame('UTC', $utc->timezoneName);
        $this->assertSame('10:21', $utc->format('H:i'));
    }

    public function test_the_same_instant_is_represented_not_a_different_one(): void
    {
        $utc = Carbon::parse('2026-08-26 10:21:00', 'UTC');

        $this->assertTrue($utc->equalTo($utc->inDisplayZone()));
    }
}
