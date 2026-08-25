<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Enums\Polls\PollEligibility;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollRatingScale;
use App\Services\Circles\VotingService;
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

    public bool $hideVoterIdentities = true;

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
        $this->hideVoterIdentities = $poll->hide_voter_identities;
        $this->publishResults = $poll->publish_results;

        // datetime-local wants exactly this shape; a stored null stays blank.
        $this->opensAt = $poll->opens_at?->format('Y-m-d\TH:i') ?? '';
        $this->closesAt = $poll->closes_at?->format('Y-m-d\TH:i') ?? '';
        $this->qualifyingDate = $poll->qualifying_date?->format('Y-m-d\TH:i') ?? '';

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
            'hide_voter_identities' => $this->hideVoterIdentities,
            'publish_results' => $this->publishResults,
            'opens_at' => $this->opensAt !== '' ? now()->parse($this->opensAt) : null,
            'closes_at' => $this->closesAt !== '' ? now()->parse($this->closesAt) : null,
            'qualifying_date' => $this->qualifyingDate !== '' ? now()->parse($this->qualifyingDate) : null,
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
