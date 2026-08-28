<?php

namespace Tests\Feature;

use App\Enums\Polls\RatingScalePresentation;
use App\Models\Polls\PollRatingScale;
use App\Models\Polls\PollRatingScalePoint;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestView;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * The <x-polls.star-rating> component.
 *
 * It is a shared component, so what it writes to must come from its host: it
 * used to write to a hardcoded `scores.<optionId>` path, which meant it did
 * nothing at all in any form whose state happened to be called something else
 * — and silently, since a click still lit the stars up
 * (.scratch/polls/issues/14).
 */
class PollStarRatingTest extends TestCase
{
    private Collection $points;

    protected function setUp(): void
    {
        parent::setUp();

        TestSchema::make()->pollRatingScales();

        $scale = PollRatingScale::create([
            'name' => '3-point',
            'presentation' => RatingScalePresentation::Stars->value,
        ]);

        $this->points = collect(['Poor', 'Fair', 'Good'])
            ->map(fn (string $label, int $i) => PollRatingScalePoint::create([
                'poll_rating_scale_id' => $scale->id,
                'label' => $label,
                'value' => $i + 1,
                'position' => $i,
            ]));
    }

    private function render(array $props = []): TestView
    {
        $props = array_merge(['property' => 'scores.12', 'points' => $this->points], $props);

        return $this->blade(
            '<x-polls.star-rating :points="$points" :property="$property" :selected="$selected" :label="$label" :disabled="$disabled" />',
            $props + ['selected' => null, 'label' => 'Roads', 'disabled' => false],
        );
    }

    /** The whole point of the ticket: the host names the property. */
    public function test_it_writes_to_the_property_its_host_gives_it(): void
    {
        $this->render(['property' => 'answers.7'])
            ->assertSee("\$wire.set('answers.7'", false)
            ->assertDontSee('scores', false);
    }

    /** And the existing rating form is unchanged. */
    public function test_the_existing_scores_path_still_works(): void
    {
        $ids = $this->points->pluck('id')->all();

        $view = $this->render(['property' => 'scores.12']);

        // One write per star, each committing that point's id.
        foreach ($ids as $id) {
            $view->assertSee("\$wire.set('scores.12', {$id})", false);
        }
    }

    /**
     * A property path is interpolated into a JS string, so it must be encoded
     * rather than concatenated — otherwise a quote in the path breaks the
     * handler and takes the form's JS with it.
     */
    public function test_the_property_path_is_js_encoded(): void
    {
        $this->render(['property' => "it's.odd"])
            ->assertDontSee("\$wire.set('it's.odd'", false);
    }

    /** Everything the ticket asked to keep working. */
    public function test_it_keeps_its_accessibility_and_hover_behaviour(): void
    {
        $view = $this->render(['selected' => $this->points[1]->id, 'label' => 'Roads']);

        $view->assertSee('role="radiogroup"', false)
            ->assertSee('aria-label="Roads"', false)
            ->assertSee('x-on:mouseenter="hover = 1"', false)
            ->assertSee('x-on:focus="hover = 2"', false)
            ->assertSee('x-on:blur="hover = null"', false)
            ->assertSee(':aria-checked=', false)
            ->assertSee('aria-live="polite"', false);

        // Each point contributes a radio and its own label.
        foreach ($this->points as $point) {
            $view->assertSee('aria-label="'.$point->label.'"', false);
        }

        // The stored point id is translated into its star POSITION (2nd of 3).
        $view->assertSee('chosen: 2', false);
    }

    /** Read-only means read-only: no writes and no hover wiring at all. */
    public function test_disabled_renders_without_any_write(): void
    {
        $this->render(['disabled' => true])
            ->assertDontSee('$wire.set', false)
            ->assertDontSee('x-on:mouseenter', false)
            ->assertSee('role="radiogroup"', false);
    }
}
