<?php

namespace Tests\Unit;

use App\Support\DisplayTime;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The boundary between stored time (UTC) and typed/read wall-clock time.
 *
 * The bug this exists to prevent: an organiser in SAST typed 12:21 into a
 * datetime-local field, it was parsed as 12:21 UTC, and the poll opened two
 * hours later than intended — reporting "not accepting responses" while its
 * own page showed an opening time already in the past.
 */
class DisplayTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Johannesburg']);
    }

    public function test_a_typed_wall_clock_is_read_in_the_display_timezone(): void
    {
        $stored = DisplayTime::fromInput('2026-08-26T12:21');

        // 12:21 SAST is 10:21 UTC — two hours earlier, not the same number.
        $this->assertSame('2026-08-26 10:21:00', $stored->utc()->toDateTimeString());
    }

    public function test_a_time_just_past_reads_as_past_not_future(): void
    {
        // The exact shape of the reported bug.
        Carbon::setTestNow(Carbon::parse('2026-08-26 10:31:00', 'UTC')); // 12:31 SAST

        $opensAt = DisplayTime::fromInput('2026-08-26T12:21'); // ten minutes ago

        $this->assertTrue($opensAt->isPast());
        $this->assertFalse($opensAt->isFuture(), 'a poll opened ten minutes ago must be open');

        Carbon::setTestNow();
    }

    public function test_a_stored_instant_renders_back_as_the_wall_clock_that_was_typed(): void
    {
        $typed = '2026-08-26T12:21';

        $this->assertSame($typed, DisplayTime::toInput(DisplayTime::fromInput($typed)));
    }

    public function test_blank_input_stays_null_so_optional_fields_remain_empty(): void
    {
        $this->assertNull(DisplayTime::fromInput(''));
        $this->assertNull(DisplayTime::fromInput('   '));
        $this->assertNull(DisplayTime::fromInput(null));
        $this->assertSame('', DisplayTime::toInput(null));
    }

    public function test_for_display_moves_an_instant_without_changing_it(): void
    {
        $utc = Carbon::parse('2026-08-26 10:21:00', 'UTC');
        $shown = DisplayTime::forDisplay($utc);

        $this->assertSame('12:21', $shown->format('H:i'), 'same instant, local wall clock');
        $this->assertTrue($utc->equalTo($shown), 'the instant itself is unchanged');
        $this->assertSame('UTC', $utc->timezoneName, 'the original is not mutated');
    }

    public function test_it_falls_back_to_the_app_timezone_when_none_is_configured(): void
    {
        config(['app.display_timezone' => null]);

        $this->assertSame('UTC', DisplayTime::timezone());
        $this->assertSame('2026-08-26 12:21:00', DisplayTime::fromInput('2026-08-26T12:21')->toDateTimeString());
    }
}
