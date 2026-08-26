<?php

namespace Tests\Feature;

use App\Models\Polls\PollRatingScale;
use App\Models\Polls\PollRatingScalePoint;
use Database\Seeders\Polls\PollRatingScaleSeeder;
use Tests\TestCase;

/**
 * Rating Scales are platform vocabulary shared by every circle, so the seeder
 * must be safe to re-run: adding a scale later means a new entry plus another
 * run, on a database where earlier scales are already referenced by cast
 * responses.
 */
class PollRatingScaleSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only the tables the seeder touches — the full migration set cannot
        // run on sqlite (see the Testing notes in CLAUDE.md). Matched by NAME
        // so a later rating-scale migration is picked up here too, rather than
        // the test silently running against a stale schema.
        foreach (glob(database_path('migrations/*_poll_rating_scale*.php')) as $migration) {
            (include $migration)->up();
        }
    }

    public function test_it_seeds_the_starting_scales_with_ordered_points(): void
    {
        $this->seed(PollRatingScaleSeeder::class);

        $this->assertSame(3, PollRatingScale::count());

        $agreement = PollRatingScale::where('name', '5-point agreement')->firstOrFail();

        $this->assertSame(
            ['Strongly disagree', 'Disagree', 'Neither agree nor disagree', 'Agree', 'Strongly agree'],
            $agreement->points()->pluck('label')->all(),
            'points() orders by position, which must match ascending value',
        );

        $this->assertSame([1, 2, 3, 4, 5], $agreement->points()->pluck('value')->all());
        $this->assertSame(10, PollRatingScale::where('name', '1–10 priority')->firstOrFail()->points()->count());
    }

    public function test_re_running_the_seeder_neither_duplicates_nor_removes_anything(): void
    {
        $this->seed(PollRatingScaleSeeder::class);

        $scales = PollRatingScale::count();
        $points = PollRatingScalePoint::count();
        $pointIds = PollRatingScalePoint::orderBy('id')->pluck('id')->all();

        $this->seed(PollRatingScaleSeeder::class);

        $this->assertSame($scales, PollRatingScale::count());
        $this->assertSame($points, PollRatingScalePoint::count());

        // The SAME rows, not replacements: a cast response references a point
        // by id, and the FK is restrictOnDelete precisely so a scale in use
        // cannot be pulled out from under a stored result.
        $this->assertSame($pointIds, PollRatingScalePoint::orderBy('id')->pluck('id')->all());
    }

    public function test_re_running_restores_an_edited_label_without_touching_the_row(): void
    {
        $this->seed(PollRatingScaleSeeder::class);

        $point = PollRatingScalePoint::whereHas(
            'scale',
            fn ($q) => $q->where('name', '5-point agreement'),
        )->where('value', 5)->firstOrFail();

        $point->update(['label' => 'Edited by hand']);

        $this->seed(PollRatingScaleSeeder::class);

        $point->refresh();
        $this->assertSame('Strongly agree', $point->label, 'the seeder is the source of truth for its own scales');
        $this->assertSame(5, $point->value);
    }
}
