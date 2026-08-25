<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Enums\Polls\PollEligibility;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\TallyMethod;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollRatingScale;
use App\Services\Circles\VotingService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use LivewireUI\Modal\ModalComponent;

/**
 * Compose a Poll as a Draft. Publishing is a separate, deliberate act on the
 * poll's own page — it fixes the Electorate and cannot be undone once anyone
 * responds, so it is not folded into "save".
 *
 * Manage-gated in mount() AND save(); the Blade dispatch carries no pre-check.
 */
class PollModal extends ModalComponent
{
    public int $groupId;

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

    public function mount(int $groupId): void
    {
        $group = PollGroup::findOrFail($groupId);

        abort_unless($group->isManageableBy(auth()->user()), 403);

        $this->groupId = $groupId;
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

        try {
            $this->service()->createPoll($group, $user, [
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
            ]);
        } catch (InvalidArgumentException $e) {
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
