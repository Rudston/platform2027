<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Livewire\Communities\Services\ForumServiceContainer;
use App\Models\Circles\Circle;
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
     * @param  array{name: string, description?: ?string, visibility?: string}  $data
     */
    public function createGroup(Circle $circle, User $creator, array $data): ForumGroup
    {
        return $circle->forumGroups()->create([
            'created_by' => $creator->getKey(),
            'name' => $data['name'],
            'slug' => $this->slugFor($data['name']),
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? ForumGroupVisibility::Public->value,
            'status' => ForumGroupStatus::Active->value,
        ]);
    }

    /**
     * @param  array{name: string, description?: ?string, visibility?: string}  $data
     */
    public function updateGroup(ForumGroup $group, array $data): ForumGroup
    {
        // Slug is kept stable on edit to preserve the group's Discussions URL.
        $group->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? $group->visibility->value,
        ]);

        return $group;
    }

    public function deactivateGroup(ForumGroup $group): void
    {
        $group->update(['status' => ForumGroupStatus::Deactivated->value]);
    }

    /** Slug derived from a group name. */
    public function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /** Whether a name's slug already exists in this circle (optionally ignoring one group). */
    public function slugTaken(Circle $circle, string $name, ?int $ignoreId = null): bool
    {
        return $circle->forumGroups()
            ->where('slug', $this->slugFor($name))
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
