<?php

namespace App\Livewire\Communities;

use App\Models\Forums\ForumGroup;
use App\Services\Circles\ForumService;
use LivewireUI\Modal\ModalComponent;

/**
 * Create a forum discussion (transient form; same conventions as
 * ForumGroupModal). Opened from ForumGroupPage via the wire-elements modal.
 * Gated by ForumGroup::canCreateDiscussion() in mount() and save().
 */
class ForumDiscussionModal extends ModalComponent
{
    public int $forumGroupId;

    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public function mount(int $forumGroupId): void
    {
        $this->forumGroupId = $forumGroupId;

        abort_unless(
            ForumGroup::findOrFail($forumGroupId)->canCreateDiscussion(auth()->user()),
            403,
        );
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
        ];
    }

    public function save(): void
    {
        $group = ForumGroup::findOrFail($this->forumGroupId);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        abort_unless($group->canCreateDiscussion($user), 403);

        $this->validate();

        $service = app(ForumService::class);

        $slug = $service->slugFor($this->slug !== '' ? $this->slug : $this->title);

        if ($slug === '') {
            $this->addError('slug', __('forums.validation.discussion_slug_required'));

            return;
        }

        if ($service->discussionSlugExists($group, $slug)) {
            $this->addError('slug', __('forums.validation.discussion_slug_taken'));

            return;
        }

        $service->createDiscussion($group, $user, [
            'title' => $this->title,
            'slug' => $slug,
            'content' => $this->content,
        ]);

        $this->dispatch('forum-discussions-changed');
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.communities.forum-discussion-modal');
    }
}
