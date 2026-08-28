<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Models\Circles\Circle;
use App\Models\Polls\PollGroup;
use App\Services\Circles\VotingService;
use LivewireUI\Modal\ModalComponent;

/**
 * Create / edit a Poll Group (transient form). Opened from
 * PollServiceContainer via the wire-elements modal. Manage-gated in mount()
 * AND save() — the Blade dispatch carries no pre-check.
 */
class PollGroupModal extends ModalComponent
{
    public int $circleId;

    public ?int $groupId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public function mount(int $circleId, ?int $groupId = null): void
    {
        $this->circleId = $circleId;
        $this->groupId = $groupId;

        abort_unless(Circle::findOrFail($circleId)->isManageableBy(auth()->user()), 403);

        if ($groupId !== null) {
            $group = PollGroup::findOrFail($groupId);
            abort_unless($group->circle_id === $circleId, 404);

            $this->name = $group->name;
            $this->slug = (string) $group->slug;
            $this->description = (string) $group->description;
        }
    }

    protected function service(): VotingService
    {
        return app(VotingService::class);
    }

    public function save(): void
    {
        $circle = Circle::findOrFail($this->circleId);

        // Re-checked server-side: the modal can be opened by any dispatch.
        abort_unless($circle->isManageableBy(auth()->user()), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $slugSource = $this->slug !== '' ? $this->slug : $this->name;

        // Str::slug can legitimately yield NOTHING — a name in a non-Latin
        // script, or one made only of punctuation. Both group routes bind by
        // slug, so storing an empty one would make this group unreachable and
        // throw while rendering the whole Polls tab. Ask for something usable
        // instead, exactly as ForumGroupModal does.
        if ($this->service()->slugFor($slugSource) === '') {
            $this->addError('slug', __('polls.group.slug_required'));

            return;
        }

        if ($this->service()->groupSlugTaken($circle, $slugSource, $this->groupId)) {
            // Against the field the person actually typed: the URL slug if they
            // supplied one, else the name it was derived from. Reporting a
            // typed-slug clash under Name sends them off renaming a name that
            // is not the problem.
            $this->addError($this->slug !== '' ? 'slug' : 'name', __('polls.group.slug_taken'));

            return;
        }

        $data = [
            'name' => $this->name,
            'slug' => $slugSource,
            'description' => $this->description !== '' ? $this->description : null,
        ];

        if ($this->groupId === null) {
            /** @var \App\Models\User $user */
            $user = auth()->user();
            $this->service()->createGroup($circle, $user, $data);
        } else {
            $group = PollGroup::findOrFail($this->groupId);
            abort_unless($group->circle_id === $this->circleId, 404);
            abort_unless($group->isManageableBy(auth()->user()), 403);

            $this->service()->updateGroup($group, $data);
        }

        $this->dispatch('poll-groups-changed');
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.communities.services.polls.poll-group-modal');
    }
}
