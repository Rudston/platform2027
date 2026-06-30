<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Explore Communities')]
class ExploreCommunities extends Component
{
    /**
     * CommunityType enum value (FQCN); null = All / Locations. Top section
     * (location explorer). Persisted to the URL via $topTypeParam (short name).
     */
    public ?string $selectedType = null;

    /**
     * Bottom section type filter — Organisation / Campaign / Course /
     * ThemeCommunity / Event (FQCN). Defaults to ThemeCommunity (set in
     * mount()). Independent of $selectedType; both share the geographic
     * selection below. Persisted to the URL via $bottomTypeParam (short name).
     */
    public ?string $selectedCommunityType = null;

    /** Currently selected geographic circle id; null = national level. */
    #[Url(as: 'circle')]
    public ?int $selectedCircleId = null;

    /** 'browse' | 'map' */
    #[Url(as: 'view')]
    public string $viewMode = 'browse';

    /**
     * URL-facing type params holding enum CASE NAMES (e.g. 'LocationCommunity',
     * 'Campaign'), kept as short mirrors of $selectedType / $selectedCommunityType
     * so the query string stays clean (?type=…&community=…) while the internal
     * properties remain FQCNs used by the queries and child components.
     */
    #[Url(as: 'type')]
    public ?string $topTypeParam = null;

    #[Url(as: 'community')]
    public ?string $bottomTypeParam = null;

    /** Array of ['id' => ?int, 'name' => string]; always starts at South Africa. */
    public array $breadcrumb = [];

    public function mount(): void
    {
        // The URL-bound props (selectedCircleId, viewMode, topTypeParam,
        // bottomTypeParam) are already hydrated from the query string here.

        // Resolve the internal FQCN type properties from the short URL params.
        $this->selectedType = $this->fqcnForName($this->topTypeParam);

        // Bottom section defaults to Theme Communities when no ?community param.
        $this->selectedCommunityType = $this->fqcnForName($this->bottomTypeParam)
            ?? CommunityType::ThemeCommunity->value;

        // Rebuild the geographic breadcrumb from the (possibly URL-provided) circle.
        $this->buildBreadcrumbForSelectedCircle();
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

    /**
     * Circle shown in the top section's right-column card: the selected
     * circle, or the national (country) circle when nothing is selected.
     */
    #[Computed]
    public function rightColumnCircle(): ?Circle
    {
        if ($this->selectedCircle) {
            return $this->selectedCircle;
        }

        $countryId = $this->countryCircleId();

        return $countryId ? Circle::find($countryId) : null;
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
        $this->topTypeParam = $this->nameForFqcn($type);
    }

    public function selectCommunityType(?string $type): void
    {
        // Bottom section's type filter. Must NOT touch the geographic selection
        // (selectedCircleId/breadcrumb) or the top section's selectedType.
        $this->selectedCommunityType = $type;
        $this->bottomTypeParam = $this->nameForFqcn($type);
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

    /**
     * Rebuild the geographic breadcrumb from $selectedCircleId (e.g. on a
     * direct URL load) by walking the circle's ancestor path. Mirrors the
     * trail produced incrementally by selectCircle().
     */
    private function buildBreadcrumbForSelectedCircle(): void
    {
        $crumbs = [['id' => null, 'name' => 'South Africa']];

        if ($this->selectedCircleId === null) {
            $this->breadcrumb = $crumbs;

            return;
        }

        $circle = Circle::with('locatable')->find($this->selectedCircleId);

        if (! $circle) {
            // Stale/invalid ?circle — fall back to national.
            $this->selectedCircleId = null;
            $this->breadcrumb = $crumbs;

            return;
        }

        foreach ($circle->ancestors() as $ancestor) {
            if ($ancestor->parent_id === null) {
                continue; // the root country circle is represented by "South Africa" (null)
            }
            $ancestor->loadMissing('locatable');
            $crumbs[] = ['id' => $ancestor->id, 'name' => $ancestor->locatable?->name ?? $ancestor->name];
        }

        if ($circle->parent_id === null) {
            // Selected circle is the country root → national level (no extra crumb).
            $this->selectedCircleId = null;
        } else {
            $crumbs[] = ['id' => $circle->id, 'name' => $circle->locatable?->name ?? $circle->name];
        }

        $this->breadcrumb = $crumbs;
    }

    /** FQCN from an enum case name (e.g. 'Campaign' → CommunityType::Campaign->value). */
    private function fqcnForName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        foreach (CommunityType::cases() as $case) {
            if ($case->name === $name) {
                return $case->value;
            }
        }

        return null;
    }

    /** Enum case name from an FQCN (e.g. CommunityType::Campaign->value → 'Campaign'). */
    private function nameForFqcn(?string $fqcn): ?string
    {
        return $fqcn ? CommunityType::tryFrom($fqcn)?->name : null;
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
