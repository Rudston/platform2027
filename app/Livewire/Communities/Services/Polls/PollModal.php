<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Enums\Polls\PollEligibility;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollRatingScale;
use App\Services\Circles\VotingService;
use App\Support\DisplayTime;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Livewire\Attributes\Computed;
use LivewireUI\Modal\ModalComponent;

/**
 * Compose a Poll as a Draft, or amend one that nobody has answered yet.
 *
 * Publishing is a separate, deliberate act on the poll's own page — it fixes
 * the Electorate — so it is not folded into "save". Publishing is NOT the point
 * of no return either: a published poll stays editable until its first
 * response, which is what Poll::isAmendable() answers.
 *
 * Manage-gated in mount() AND save(); the Blade dispatch carries no pre-check.
 */
class PollModal extends ModalComponent
{
    public int $groupId;

    /** Null when composing; set when amending an existing poll. */
    public ?int $pollId = null;

    public string $title = '';

    public string $description = '';

    public string $prompt = '';

    public string $shape = 'single_choice';

    public string $tallyMethod = 'plurality';

    public string $eligibility = 'private';

    /** @var list<string> */
    public array $options = ['', ''];

    public bool $requireFullRanking = false;

    public bool $allowResponseUpdate = false;

    public bool $publishResults = false;

    public ?int $ratingScaleId = null;

    public string $opensAt = '';

    public string $closesAt = '';

    public string $qualifyingDate = '';

    public function mount(int $groupId, ?int $pollId = null): void
    {
        $group = PollGroup::findOrFail($groupId);

        abort_unless($group->isManageableBy(auth()->user()), 403);

        $this->groupId = $groupId;
        $this->pollId = $pollId;

        if ($pollId !== null) {
            $this->hydrateFrom($this->poll());
        }
    }

    /** The poll being amended, re-fetched and re-gated on every use. */
    protected function poll(): Poll
    {
        $poll = Poll::findOrFail($this->pollId);

        abort_unless($poll->poll_group_id === $this->groupId, 404);
        abort_unless($poll->isManageableBy(auth()->user()), 403);

        // Defence in depth: the affordance is hidden once a poll has responses,
        // but a stale tab could still dispatch this modal open.
        abort_unless($poll->isAmendable(), 403);

        return $poll;
    }

    private function hydrateFrom(Poll $poll): void
    {
        $question = $poll->question;

        $this->title = $poll->title;
        $this->description = (string) $poll->description;
        $this->eligibility = $poll->eligibility->value;
        $this->allowResponseUpdate = $poll->allow_response_update;
        $this->publishResults = $poll->publish_results;

        // Rendered in the DISPLAY timezone, so reopening the form shows the
        // wall clock the organiser typed rather than its UTC equivalent.
        $this->opensAt = DisplayTime::toInput($poll->opens_at);
        $this->closesAt = DisplayTime::toInput($poll->closes_at);
        $this->qualifyingDate = DisplayTime::toInput($poll->qualifying_date);

        if ($question === null) {
            return;
        }

        $this->prompt = $question->text;
        $this->shape = $question->type->value;
        $this->tallyMethod = $question->tally_method->value;
        $this->requireFullRanking = $question->require_full_ranking;
        $this->ratingScaleId = $question->rating_scale_id;
        $this->options = $question->options()->pluck('label')->all();
    }

    protected function service(): VotingService
    {
        return app(VotingService::class);
    }

    #[Computed]
    public function group(): PollGroup
    {
        return PollGroup::findOrFail($this->groupId);
    }

    /**
     * The tally methods legal for the chosen shape. The rule itself lives in
     * PollResponseShape::allowedTallyMethods() — this only reads it, so the UI
     * cannot drift from what the service will accept.
     *
     * @return list<TallyMethod>
     */
    #[Computed]
    public function allowedTallyMethods(): array
    {
        return PollResponseShape::from($this->shape)->allowedTallyMethods();
    }

    /** @return Collection<int, PollRatingScale> */
    #[Computed]
    public function ratingScales(): Collection
    {
        return PollRatingScale::query()->orderBy('name')->get();
    }

    /** Keep the tally method valid whenever the shape changes. */
    public function updatedShape(): void
    {
        unset($this->allowedTallyMethods);

        $allowed = $this->allowedTallyMethods();

        if (! in_array($this->tallyMethod, array_map(fn (TallyMethod $m): string => $m->value, $allowed), true)) {
            $this->tallyMethod = $allowed[0]->value;
        }

        if ($this->shape !== PollResponseShape::Rating->value) {
            $this->ratingScaleId = null;
        }

        if ($this->shape !== PollResponseShape::RankedChoice->value) {
            $this->requireFullRanking = false;
        }
    }

    public function addOption(): void
    {
        $this->options[] = '';
    }

    public function removeOption(int $index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function save(): void
    {
        $group = $this->group();

        abort_unless($group->isManageableBy(auth()->user()), 403);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prompt' => ['required', 'string', 'max:500'],
        ]);

        $labels = array_values(array_filter(
            array_map('trim', $this->options),
            fn (string $label): bool => $label !== '',
        ));

        if (count($labels) < 2) {
            $this->addError('options', __('polls.poll.min_options'));

            return;
        }

        $opensAt = DisplayTime::fromInput($this->opensAt);
        $closesAt = DisplayTime::fromInput($this->closesAt);

        // Checked here as well as in the service so the message lands ON the
        // offending field rather than as a general form error.
        if ($opensAt !== null && $closesAt !== null && $closesAt->lessThanOrEqualTo($opensAt)) {
            $this->addError('closesAt', __('polls.poll.closes_before_opens'));

            return;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $data = [
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'prompt' => $this->prompt,
            'shape' => PollResponseShape::from($this->shape),
            'tally_method' => TallyMethod::from($this->tallyMethod),
            'options' => $labels,
            'eligibility' => PollEligibility::from($this->eligibility),
            'require_full_ranking' => $this->requireFullRanking,
            'rating_scale_id' => $this->ratingScaleId,
            'allow_response_update' => $this->allowResponseUpdate,
            'publish_results' => $this->publishResults,
            // A datetime-local field carries a WALL CLOCK with no zone. Read it
            // in the display timezone, not the app's — parsing "12:21" as UTC
            // when the organiser meant 12:21 SAST opened polls two hours late.
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'qualifying_date' => DisplayTime::fromInput($this->qualifyingDate),
        ];

        try {
            if ($this->pollId === null) {
                $this->service()->createPoll($group, $user, $data);
            } else {
                // guardAmendable inside updatePoll re-checks isAmendable(), so a
                // response landing between opening this modal and saving it is
                // refused rather than quietly rewriting the ballot underneath.
                $this->service()->updatePoll($this->poll(), $data);
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            // The service is the authority on legal combinations; surface its
            // refusal rather than duplicating the rule here.
            $this->addError('title', $e->getMessage());

            return;
        }

        $this->dispatch('polls-changed');
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.communities.services.polls.poll-modal');
    }
}
