<?php

namespace Database\Seeders\Polls;

use App\Enums\Polls\RatingScalePresentation;
use App\Models\Polls\PollRatingScale;
use App\Models\Polls\PollRatingScalePoint;
use Illuminate\Database\Seeder;

/**
 * The starting set of Rating Scales.
 *
 * Rating Scales are PLATFORM vocabulary, not a circle's: curated centrally and
 * shared by every circle, so "Strongly Agree" means the same thing in two
 * circles' results. Circle admins PICK a scale, never mint one — the same
 * treatment themes get, and the reason poll_rating_scales has no circle_id.
 *
 * Idempotent: scales are matched on their unique name and points on
 * (scale, value), so re-running updates labels and never duplicates. It also
 * never DELETES a point — a cast response references one, and the FK is
 * restrictOnDelete precisely so a scale still in use cannot be pulled out from
 * under a stored result.
 *
 * Adding a scale later is a new entry here plus a re-run.
 */
class PollRatingScaleSeeder extends Seeder
{
    public function run(): void
    {
        $scales = [
            [
                'name' => '5-point agreement',
                // Values are what gets averaged, so they must be ordered and
                // evenly spaced for a mean to mean anything.
                'points' => [
                    ['label' => 'Strongly disagree', 'value' => 1],
                    ['label' => 'Disagree', 'value' => 2],
                    ['label' => 'Neither agree nor disagree', 'value' => 3],
                    ['label' => 'Agree', 'value' => 4],
                    ['label' => 'Strongly agree', 'value' => 5],
                ],
            ],
            [
                'name' => '1–5 stars',
                // Short, ordered and effectively unlabelled — the one scale
                // where a star row reads better than a dropdown.
                'presentation' => RatingScalePresentation::Stars,
                'points' => [
                    ['label' => '1 star', 'value' => 1],
                    ['label' => '2 stars', 'value' => 2],
                    ['label' => '3 stars', 'value' => 3],
                    ['label' => '4 stars', 'value' => 4],
                    ['label' => '5 stars', 'value' => 5],
                ],
            ],
            [
                'name' => '1–10 priority',
                'points' => array_map(
                    fn (int $n): array => ['label' => (string) $n, 'value' => $n],
                    range(1, 10),
                ),
            ],
        ];

        $pointCount = 0;

        foreach ($scales as $scale) {
            /** @var PollRatingScale $model */
            $model = PollRatingScale::updateOrCreate(
                ['name' => $scale['name']],
                ['presentation' => $scale['presentation'] ?? RatingScalePresentation::Select],
            );

            foreach (array_values($scale['points']) as $position => $point) {
                PollRatingScalePoint::updateOrCreate(
                    [
                        'poll_rating_scale_id' => $model->getKey(),
                        'value' => $point['value'],
                    ],
                    [
                        'label' => $point['label'],
                        'position' => $position,
                    ],
                );

                $pointCount++;
            }
        }

        $this->command?->info(sprintf(
            'Seeded %d rating scales (%d points).',
            count($scales),
            $pointCount,
        ));
    }
}
