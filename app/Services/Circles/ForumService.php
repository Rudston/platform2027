<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Livewire\Communities\Services\ForumServiceContainer;
use App\Models\Circles\Circle;
use App\Enums\Forums\ForumDiscussionModerationStatus;
use App\Enums\Forums\ForumDiscussionStatus;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\User;
use Illuminate\Support\Str;

class ForumService implements CircleServiceContract
{
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
            'slug' => $this->slugFor($data['slug'] ?? $data['name']),
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
            'slug' => isset($data['slug']) ? $this->slugFor($data['slug']) : $group->slug,
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
            'slug' => $this->slugFor($data['slug'] ?? $data['title']),
            'content' => $data['content'] ?? '',
            'status' => ForumDiscussionStatus::Active->value,
            'moderation_status' => ForumDiscussionModerationStatus::Approved->value,
            'is_pinned' => false,
            'is_locked' => false,
        ]);
    }

    /** Whether a discussion slug already exists in a group (optionally ignoring one). */
    public function discussionSlugExists(ForumGroup $group, string $slug, ?int $ignoreId = null): bool
    {
        return $group->discussions()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /** Slug derived from a group name. */
    public function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /** Whether a name's slug already exists in this circle (optionally ignoring one group). */
    public function slugTaken(Circle $circle, string $name, ?int $ignoreId = null): bool
    {
        return $this->slugExists($circle, $this->slugFor($name), $ignoreId);
    }

    /** Whether an exact slug already exists in this circle (optionally ignoring one group). */
    public function slugExists(Circle $circle, string $slug, ?int $ignoreId = null): bool
    {
        return $circle->forumGroups()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
