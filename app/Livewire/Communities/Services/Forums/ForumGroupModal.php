<?php

namespace App\Livewire\Communities\Services\Forums;

use App\Enums\Forums\ForumGroupVisibility;
use App\Models\Circles\Circle;
use App\Models\Forums\ForumGroup;
use App\Services\Circles\ForumService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use LivewireUI\Modal\ModalComponent;

/**
 * Create / edit a forum group (transient form). Opened from
 * ForumServiceContainer via the wire-elements modal. Manage-gated in mount()
 * and save().
 */
class ForumGroupModal extends ModalComponent
{
    public int $circleId;

    public ?int $groupId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $visibility = 'public';

    public function mount(int $circleId, ?int $groupId = null): void
    {
        $this->circleId = $circleId;
        $this->groupId = $groupId;

        // Manage-gated at open too (the Blade dispatch has no pre-check; save()
        // re-checks as well).
        abort_unless(Circle::findOrFail($circleId)->isManageableBy(auth()->user()), 403);

        if ($groupId !== null) {
            $group = ForumGroup::findOrFail($groupId);
            abort_unless($group->circle_id === $circleId, 404);

            $this->name = $group->name;
            $this->slug = (string) $group->slug;
            $this->description = (string) $group->description;
            $this->visibility = $group->visibility->value;
        }
    }

    /**
     * Fixed "Group Access" copy derived live from the selected visibility's
     * participation floor (purely display — never submitted).
     */
    #[Computed]
    public function participationNote(): string
    {
        $floor = ForumGroupVisibility::from($this->visibility)->participationFloor();

        return $floor === ForumGroupVisibility::Internal
            ? __('forums.access.internal')
            : __('forums.access.members');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
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

        // Resolve the intended slug (explicit input, else derived from the name)
        // and surface a friendly collision message rather than a raw DB error.
        $slug = $service->slugFor($this->slug !== '' ? $this->slug : $this->name);

        if ($slug === '') {
            $this->addError('slug', __('forums.validation.slug_required'));

            return;
        }

        if ($service->slugExists($circle, $slug, $this->groupId)) {
            $this->addError('slug', __('forums.validation.slug_taken'));

            return;
        }

        $data = [
            'name' => $this->name,
            'slug' => $slug,
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
        return view('livewire.communities.services.forums.forum-group-modal');
    }
}
