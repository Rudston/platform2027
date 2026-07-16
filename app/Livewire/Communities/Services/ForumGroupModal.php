<?php

namespace App\Livewire\Communities\Services;

use App\Enums\Forums\ForumGroupVisibility;
use App\Models\Circles\Circle;
use App\Models\Forums\ForumGroup;
use App\Services\Circles\ForumService;
use Illuminate\Validation\Rule;
use LivewireUI\Modal\ModalComponent;

/**
 * Create / edit a forum group (transient form). Opened from
 * ForumServiceContainer via the wire-elements modal. Manage-gated in save().
 */
class ForumGroupModal extends ModalComponent
{
    public int $circleId;

    public ?int $groupId = null;

    public string $name = '';

    public string $description = '';

    public string $visibility = 'public';

    public function mount(int $circleId, ?int $groupId = null): void
    {
        $this->circleId = $circleId;
        $this->groupId = $groupId;

        if ($groupId !== null) {
            $group = ForumGroup::findOrFail($groupId);
            abort_unless($group->circle_id === $circleId, 404);

            $this->name = $group->name;
            $this->description = (string) $group->description;
            $this->visibility = $group->visibility->value;
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in(array_map(fn ($c) => $c->value, ForumGroupVisibility::cases()))],
        ];
    }

    public function save(): void
    {
        $circle = Circle::findOrFail($this->circleId);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        abort_unless($circle->isManageableBy($user), 403);

        $this->validate();

        $service = app(ForumService::class);

        // Friendly collision message instead of a raw unique-constraint error.
        if ($service->slugTaken($circle, $this->name, $this->groupId)) {
            $this->addError('name', __('forums.validation.name_taken'));

            return;
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'visibility' => $this->visibility,
        ];

        if ($this->groupId !== null) {
            $group = ForumGroup::findOrFail($this->groupId);
            abort_unless($group->circle_id === $circle->id, 404);
            $service->updateGroup($group, $data);
        } else {
            $service->createGroup($circle, $user, $data);
        }

        $this->dispatch('forum-groups-changed');
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.communities.services.forum-group-modal');
    }
}
