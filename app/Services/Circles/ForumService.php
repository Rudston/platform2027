<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Livewire\Communities\Services\Forums\ForumServiceContainer;
use App\Models\Circles\Circle;
use App\Enums\Forums\ForumDiscussionModerationStatus;
use App\Enums\Forums\ForumDiscussionStatus;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\User;
use App\Services\Circles\Concerns\DerivesScopedSlugs;

class ForumService implements CircleServiceContract
{
    use DerivesScopedSlugs;

    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'forums';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return ForumServiceContainer::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Forum group operations (writes go through here; reads via the container)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{name: string, slug?: ?string, description?: ?string, visibility?: string}  $data
     */
    public function createGroup(Circle $circle, User $creator, array $data): ForumGroup
    {
        return $circle->forumGroups()->create([
            'created_by' => $creator->getKey(),
            'name' => $data['name'],
            // Explicit slug when given, else derived from the name.
            'slug' => $this->requireSlugFor($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? ForumGroupVisibility::Public->value,
            'status' => ForumGroupStatus::Active->value,
        ]);
    }

    /**
     * @param  array{name: string, slug?: ?string, description?: ?string, visibility?: string}  $data
     */
    public function updateGroup(ForumGroup $group, array $data): ForumGroup
    {
        $group->update([
            'name' => $data['name'],
            'slug' => isset($data['slug']) ? $this->requireSlugFor($data['slug']) : $group->slug,
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? $group->visibility->value,
        ]);

        return $group;
    }

    public function deactivateGroup(ForumGroup $group): void
    {
        $group->update(['status' => ForumGroupStatus::Deactivated->value]);
    }

    /**
     * Create a discussion in a group. status/moderation_status take their DB
     * defaults (active / approved). Slug is explicit or derived from the title.
     *
     * @param  array{title: string, slug?: ?string, content?: ?string}  $data
     */
    public function createDiscussion(ForumGroup $group, User $creator, array $data): ForumDiscussion
    {
        // Set the enum/boolean defaults explicitly so the returned model is
        // fully populated (DB defaults aren't reflected in-memory on create).
        return $group->discussions()->create([
            'created_by' => $creator->getKey(),
            'title' => $data['title'],
            'slug' => $this->requireSlugFor($data['slug'] ?? $data['title']),
            'content' => $data['content'] ?? '',
            'status' => ForumDiscussionStatus::Active->value,
            'moderation_status' => ForumDiscussionModerationStatus::Approved->value,
            'is_pinned' => false,
            'is_locked' => false,
        ]);
    }

    /** Edit a discussion's first-post content, stamping content_edited_at. */
    public function updateDiscussionContent(ForumDiscussion $discussion, string $content): ForumDiscussion
    {
        $discussion->update([
            'content' => $content,
            'content_edited_at' => now(),
        ]);

        return $discussion;
    }

    /** Whether a discussion slug already exists in a group (optionally ignoring one). */
    public function discussionSlugExists(ForumGroup $group, string $slug, ?int $ignoreId = null): bool
    {
        return $this->slugExistsAmong($group->discussions(), $slug, $ignoreId);
    }

    /** Whether a name's slug already exists in this circle (optionally ignoring one group). */
    public function slugTaken(Circle $circle, string $name, ?int $ignoreId = null): bool
    {
        return $this->slugExists($circle, $this->slugFor($name), $ignoreId);
    }

    /** Whether an exact slug already exists in this circle (optionally ignoring one group). */
    public function slugExists(Circle $circle, string $slug, ?int $ignoreId = null): bool
    {
        return $this->slugExistsAmong($circle->forumGroups(), $slug, $ignoreId);
    }
}
