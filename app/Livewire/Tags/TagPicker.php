<?php

namespace App\Livewire\Tags;

use App\Models\Circles\Circle;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Theme;
use App\Models\ThemeSuggestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Reusable tag picker for a taggable entity (Circle / ForumGroup /
 * ForumDiscussion). Attach/detach mirror the entity's manage rights
 * (canBeTaggedBy); "suggest a tag" is open to any authenticated user and only
 * creates a pending ThemeSuggestion (attaches nothing).
 */
class TagPicker extends Component
{
    /** Morph class (FQCN) of the taggable. */
    public string $taggableType;

    public int $taggableId;

    public string $search = '';

    // Suggest-a-tag form.
    public bool $showSuggest = false;

    public string $suggestName = '';

    public string $suggestDescription = '';

    /** Only these models are taggable. */
    private const ALLOWED = [
        Circle::class,
        ForumGroup::class,
        ForumDiscussion::class,
    ];

    public function mount(string $taggableType, int $taggableId): void
    {
        abort_unless(in_array($taggableType, self::ALLOWED, true), 404);

        $this->taggableType = $taggableType;
        $this->taggableId = $taggableId;
    }

    protected function taggable(): Model
    {
        return $this->taggableType::findOrFail($this->taggableId);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->taggable()->canBeTaggedBy(auth()->user());
    }

    /** @return Collection<int, Theme> */
    #[Computed]
    public function tags(): Collection
    {
        return $this->taggable()->tags()->orderBy('name')->get();
    }

    /**
     * Existing themes matching the search that aren't already attached — the
     * attach suggestions. Empty until at least 2 characters are typed.
     *
     * @return Collection<int, Theme>
     */
    #[Computed]
    public function matches(): Collection
    {
        if (mb_strlen($this->search) < 2) {
            return new Collection;
        }

        $attachedIds = $this->tags->pluck('id');

        return Theme::query()
            ->where('name', 'like', '%'.$this->search.'%')
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function attach(int $themeId): void
    {
        $taggable = $this->taggable();
        abort_unless($taggable->canBeTaggedBy(auth()->user()), 403);

        $taggable->tags()->syncWithoutDetaching([$themeId]);
        $this->search = '';
        unset($this->tags, $this->matches);
    }

    public function detach(int $themeId): void
    {
        $taggable = $this->taggable();
        abort_unless($taggable->canBeTaggedBy(auth()->user()), 403);

        $taggable->tags()->detach($themeId);
        unset($this->tags, $this->matches);
    }

    /** Any authenticated user may suggest a tag (regardless of manage rights). */
    public function submitSuggestion(): void
    {
        abort_unless(auth()->check(), 403);

        $data = $this->validate([
            'suggestName' => ['required', 'string', 'max:255'],
            'suggestDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        ThemeSuggestion::create([
            'name' => $data['suggestName'],
            'description' => $data['suggestDescription'] !== '' ? $data['suggestDescription'] : null,
            'requested_by' => auth()->id(),
            'origin_taggable_type' => $this->taggableType,
            'origin_taggable_id' => $this->taggableId,
        ]);

        $this->reset(['suggestName', 'suggestDescription', 'showSuggest']);
        $this->dispatch('tag-suggested');
    }

    public function render()
    {
        return view('livewire.tags.tag-picker');
    }
}
