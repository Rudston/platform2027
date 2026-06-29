<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Explore Communities')]
class ExploreCommunities extends Component
{
    /** CommunityType enum value (FQCN); null = All / Locations. Top section (location explorer). */
    public ?string $selectedType = null;

    /**
     * Bottom section type filter — Organisation / Campaign / Course /
     * ThemeCommunity / Event (FQCN); null = none selected yet. Independent of
     * $selectedType; both share the geographic selection below.
     */
    public ?string $selectedCommunityType = null;

    /** Currently selected geographic circle id; null = national level. */
    public ?int $selectedCircleId = null;

    /** 'browse' | 'map' */
    public string $viewMode = 'browse';

    /** Array of ['id' => ?int, 'name' => string]; always starts at South Africa. */
    public array $breadcrumb = [];

    public function mount(): void
    {
        $this->breadcrumb = [
            ['id' => null, 'name' => 'South Africa'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Computed properties
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function selectedCircle(): ?Circle
    {
        return $this->selectedCircleId
            ? Circle::find($this->selectedCircleId)
            : null;
    }

    #[Computed]
    public function currentLevel(): string
    {
        $circle = $this->selectedCircle;

        if (! $circle) {
            return 'National';
        }

        return LocatableType::tryFrom((string) $circle->locatable_type)?->label() ?? 'National';
    }

    /**
     * Circles at the EXACT selected level (not descendants).
     */
    #[Computed]
    public function communities(): Collection
    {
        $circle = $this->selectedCircle;
        $isLocationMode = $this->selectedType === null
            || $this->selectedType === CommunityType::LocationCommunity->value;

        if ($isLocationMode) {
            // Browse the location tree: direct children of the selected circle.
            // At national level (no circle), show the country's children
            // (provinces) rather than the country circle itself.
            $parentId = $circle?->id ?? $this->countryCircleId();

            if ($parentId === null) {
                return collect();
            }

            // Native children of the selected circle.
            $children = Circle::query()
                ->where('circleable_type', CommunityType::LocationCommunity->value)
                ->where('parent_id', $parentId)
                ->with(['circleable', 'locatable', 'services'])
                ->orderBy('name')
                ->get();

            $children->each(fn (Circle $c) => $c->also_here = false);

            // Merge in circles that have approved-associated themselves to the
            // current circle, badged as "also here" so the UI can distinguish
            // them from native children. Dedupe by id — native children win.
            $current = $circle ?? Circle::find($parentId);

            $associated = $current
                ? $current->approvedAssociatedBy()
                    ->with(['circleable', 'locatable', 'services'])
                    ->orderBy('circles.name')
                    ->get()
                : collect();

            $associated->each(fn (Circle $c) => $c->also_here = true);

            $childIds = $children->pluck('id')->all();
            $extra = $associated->reject(fn (Circle $c) => in_array($c->id, $childIds, true));

            return $children->concat($extra)->values();
        }

        // Non-location type: communities of the selected type located at the
        // current place (or Country/South Africa at national level).
        [$locatableType, $locatableId] = $circle
            ? [(string) $circle->locatable_type, (int) $circle->locatable_id]
            : [LocatableType::Country->value, self::SOUTH_AFRICA_ID];

        return Circle::query()
            ->where('circleable_type', $this->selectedType)
            ->where('locatable_type', $locatableType)
            ->where('locatable_id', $locatableId)
            ->with(['circleable', 'locatable', 'services'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Count of selectedType communities in descendants of the selected circle.
     */
    #[Computed]
    public function communitiesCountBelow(): int
    {
        if ($this->selectedCircleId === null || $this->selectedType === null) {
            return 0;
        }

        $circle = $this->selectedCircle;

        if (! $circle || ! $circle->path) {
            return 0;
        }

        return Circle::query()
            ->where('circleable_type', $this->selectedType)
            ->where('path', 'like', $circle->path.'/%')
            ->count();
    }

    #[Computed]
    public function selectedTypeLabel(): string
    {
        return $this->labelFor($this->selectedType);
    }

    #[Computed]
    public function selectedTypeSingular(): string
    {
        return $this->singularFor($this->selectedType);
    }

    #[Computed]
    public function selectedTypeIcon(): string
    {
        return $this->iconFor($this->selectedType);
    }

    /*
    |--------------------------------------------------------------------------
    | Bottom section: non-location community types at the selected location
    |--------------------------------------------------------------------------
    */

    /**
     * Communities of the bottom-section type located at the current place
     * (or Country/South Africa at national level). Empty until a type is picked.
     */
    #[Computed]
    public function typeCommunities(): Collection
    {
        if ($this->selectedCommunityType === null) {
            return collect();
        }

        $circle = $this->selectedCircle;

        [$locatableType, $locatableId] = $circle
            ? [(string) $circle->locatable_type, (int) $circle->locatable_id]
            : [LocatableType::Country->value, self::SOUTH_AFRICA_ID];

        return Circle::query()
            ->where('circleable_type', $this->selectedCommunityType)
            ->where('locatable_type', $locatableType)
            ->where('locatable_id', $locatableId)
            ->with(['circleable', 'locatable', 'services'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Count of bottom-section-type communities in descendants of the selected circle.
     */
    #[Computed]
    public function typeCommunitiesCountBelow(): int
    {
        if ($this->selectedCircleId === null || $this->selectedCommunityType === null) {
            return 0;
        }

        $circle = $this->selectedCircle;

        if (! $circle || ! $circle->path) {
            return 0;
        }

        return Circle::query()
            ->where('circleable_type', $this->selectedCommunityType)
            ->where('path', 'like', $circle->path.'/%')
            ->count();
    }

    #[Computed]
    public function communityTypeLabel(): string
    {
        return $this->labelFor($this->selectedCommunityType);
    }

    #[Computed]
    public function communityTypeSingular(): string
    {
        return $this->singularFor($this->selectedCommunityType);
    }

    #[Computed]
    public function communityTypeIcon(): string
    {
        return $this->iconFor($this->selectedCommunityType);
    }

    /*
    |--------------------------------------------------------------------------
    | Wire actions
    |--------------------------------------------------------------------------
    */

    public function selectType(?string $type): void
    {
        // Switching type changes WHAT is shown at the current location, not
        // WHERE the user is — so it must NOT touch selectedCircleId/breadcrumb.
        // It also must NOT touch the bottom section's selectedCommunityType.
        $this->selectedType = $type;
    }

    public function selectCommunityType(?string $type): void
    {
        // Bottom section's type filter. Must NOT touch the geographic selection
        // (selectedCircleId/breadcrumb) or the top section's selectedType.
        $this->selectedCommunityType = $type;
    }

    public function selectCircle(int $circleId): void
    {
        $circle = Circle::with('locatable')->find($circleId);

        if (! $circle) {
            return;
        }

        $this->selectedCircleId = $circleId;
        // Use the short place name (e.g. "Gauteng") rather than the verbose
        // circle name; fall back to the circle name if locatable is missing.
        $this->breadcrumb[] = [
            'id'   => $circleId,
            'name' => $circle->locatable?->name ?? $circle->name,
        ];
    }

    public function navigateToBreadcrumb(?int $circleId): void
    {
        foreach ($this->breadcrumb as $index => $crumb) {
            if ($crumb['id'] === $circleId) {
                $this->breadcrumb = array_slice($this->breadcrumb, 0, $index + 1);
                break;
            }
        }

        $this->selectedCircleId = $circleId;
    }

    /**
     * Jump the browser to a circle's location, rebuilding the breadcrumb from
     * its ancestor path. Used by the search overlay. (Location circles only;
     * non-location circles open their detail modal without browser navigation.)
     */
    #[On('navigate-to-circle')]
    public function navigateToCircle(int $circleId): void
    {
        $circle = Circle::with('locatable')->find($circleId);

        if (! $circle || $circle->circleable_type !== CommunityType::LocationCommunity->value) {
            return;
        }

        $crumbs = [['id' => null, 'name' => 'South Africa']];

        foreach ($circle->ancestors() as $ancestor) {
            if ($ancestor->parent_id === null) {
                continue; // the root country circle is represented by "South Africa" (null)
            }
            $ancestor->loadMissing('locatable');
            $crumbs[] = ['id' => $ancestor->id, 'name' => $ancestor->locatable?->name ?? $ancestor->name];
        }

        if ($circle->parent_id === null) {
            $this->selectedCircleId = null;
            $this->breadcrumb = $crumbs;

            return;
        }

        $crumbs[] = ['id' => $circle->id, 'name' => $circle->locatable?->name ?? $circle->name];
        $this->selectedCircleId = $circle->id;
        $this->breadcrumb = $crumbs;
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['browse', 'map'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function startCommunity(): void
    {
        // Placeholder — wired to a create flow later.
        $this->dispatch('start-community', type: $this->selectedType, circleId: $this->selectedCircleId);
    }

    public function startCommunityType(): void
    {
        // Placeholder — bottom-section equivalent of startCommunity().
        $this->dispatch('start-community', type: $this->selectedCommunityType, circleId: $this->selectedCircleId);
    }

    public function render()
    {
        return view('livewire.explore.explore-communities');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private const SOUTH_AFRICA_ID = 191;

    private function countryCircleId(): ?int
    {
        return Circle::query()
            ->whereNull('parent_id')
            ->where('circleable_type', CommunityType::LocationCommunity->value)
            ->value('id');
    }

    /*
    | Shared label/singular/icon lookups (used by both the top section's
    | selectedType* computeds and the bottom section's communityType* computeds).
    */

    private function labelFor(?string $type): string
    {
        return match ($type) {
            CommunityType::LocationCommunity->value => 'Locations',
            CommunityType::Organisation->value      => 'Organisations',
            CommunityType::Campaign->value          => 'Campaigns',
            CommunityType::Course->value            => 'Courses',
            CommunityType::Event->value             => 'Events',
            CommunityType::ThemeCommunity->value    => 'Theme Communities',
            default                                 => 'Communities',
        };
    }

    private function singularFor(?string $type): string
    {
        return match ($type) {
            CommunityType::LocationCommunity->value => 'Location',
            CommunityType::Organisation->value      => 'Organisation',
            CommunityType::Campaign->value          => 'Campaign',
            CommunityType::Course->value            => 'Course',
            CommunityType::Event->value             => 'Event',
            CommunityType::ThemeCommunity->value    => 'Theme',
            default                                 => 'Community',
        };
    }

    private function iconFor(?string $type): string
    {
        return match ($type) {
            CommunityType::LocationCommunity->value => '📍',
            CommunityType::Organisation->value      => '🏛',
            CommunityType::Campaign->value          => '📢',
            CommunityType::Course->value            => '🎓',
            CommunityType::Event->value             => '📅',
            CommunityType::ThemeCommunity->value    => '💡',
            default                                 => '🌍',
        };
    }
}
