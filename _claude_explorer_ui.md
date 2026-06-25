PHASE 1 SCOPE:
- Skip Step 8 (Map View) entirely for now
- The [🗺 Map] toggle button should be visible but disabled
  with a "Coming soon" tooltip
- All other steps proceed as written
- Only LocationCommunities exist in the database;
  the UI must handle empty states for other types gracefully
  but does not need real data for them yet

Build the Explore Communities page for the platform using Livewire 4.
Do ONE step at a time and stop for my review before proceeding.

Before doing anything, summarise the steps back to me.

═══════════════════════════════════════════════════════════════
CONTEXT
═══════════════════════════════════════════════════════════════

The platform has Circles — collaborative containers wrapping
community types. The relevant models are:

Community types (circleable):
- LocationCommunity
- Organisation
- Campaign
- Course
- ThemeCommunity

Geographic (locatable) levels:
- Country
- Province
- DistrictMunicipality
- LocalMunicipality
- City

The circles table has:
- circleable_id / circleable_type  (polymorphic — community type)
- locatable_id  / locatable_type   (polymorphic — geographic level)
- parent_id                        (self-referencing for nesting)
- path                             (materialized path e.g. "1/4/12")
- name / description

Enums available:
- CommunityType  (cases: Organisation, Campaign, Course,
  LocationCommunity, ThemeCommunity)
- LocatableType  (cases: Country, Province, DistrictMunicipality,
  LocalMunicipality, City)

Currently only LocationCommunities are seeded (~296 nested circles).
Other community types will be added later but the UI must handle
their empty states gracefully.

═══════════════════════════════════════════════════════════════
STEP 1: ROUTES AND PAGE SETUP
═══════════════════════════════════════════════════════════════

Create a public route:
GET /explore  → ExploreCommunities Livewire component

Create the base layout if not already present:
resources/views/layouts/app.blade.php
(standard html shell with @livewireStyles, @livewireScripts,
Tailwind CDN, and a @yield('content') or $slot)

═══════════════════════════════════════════════════════════════
STEP 2: PARENT LIVEWIRE COMPONENT
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/ExploreCommunities.php

State properties:
public ?string $selectedType     = null;
// CommunityType enum value — null means "All / Locations"

public ?int    $selectedCircleId = null;
// The currently selected geographic circle id
// null = national level (Country circle)

public string  $viewMode         = 'browse';
// 'browse' | 'map'

public array   $breadcrumb       = [];
// Array of ['id' => x, 'name' => y] for clicked path
// Always starts with ['id' => null, 'name' => 'South Africa']

Computed methods:

public function selectedCircle(): ?Circle
// Returns Circle::find($this->selectedCircleId)

public function currentLevel(): string
// Returns human-readable level label e.g. "National",
// "Province", "District Municipality" etc.
// Derived from the locatable_type of selectedCircle,
// or "National" if selectedCircleId is null

public function communities(): Collection
// Returns circles at the EXACT selected level
// (not descendants — exact level only)
//
// If selectedType is null or LocationCommunity:
//   Return direct children of selectedCircle
//   (or top-level circles if selectedCircleId is null)
//   where circleable_type = LocationCommunity
//
// If selectedType is a non-location type:
//   Return circles where:
//   - locatable_type matches the selectedCircle's locatable_type
//     and locatable_id matches selectedCircle's locatable_id
//     (or Country level if selectedCircleId is null)
//   - circleable_type matches selectedType

public function communitiesCountBelow(): int
// Count of selectedType communities that exist in
// descendants of selectedCircle (path LIKE "$path/%")
// Returns 0 if selectedCircleId is null or no type selected

public function selectedTypeLabel(): string
// Human-readable plural label for selectedType
// e.g. "Campaigns", "Organisations", "Courses"
// Returns "Communities" if null

public function selectedTypeSingular(): string
// Singular version: "Campaign", "Organisation" etc.

public function selectedTypeIcon(): string
// Emoji icon per type:
// LocationCommunity -> 📍
// Organisation      -> 🏛
// Campaign          -> 📢
// Course            -> 🎓
// ThemeCommunity    -> 💡
// null (All)        -> 🌍

Wire actions:

public function selectType(?string $type): void
// Sets selectedType, resets breadcrumb to national

public function selectCircle(int $circleId): void
// Sets selectedCircleId
// Appends to breadcrumb: ['id' => $circleId, 'name' => $circle->name]

public function navigateToBreadcrumb(?int $circleId): void
// Jumps to a breadcrumb item
// Trims breadcrumb back to that point
// Sets selectedCircleId = $circleId

public function setViewMode(string $mode): void
// Sets viewMode to 'browse' or 'map'

public function startCommunity(): void
// Placeholder — emit an event or redirect to create form
// (full implementation later)

═══════════════════════════════════════════════════════════════
STEP 3: COMMUNITY TYPE FILTER BAR
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/CommunityTypeFilter.php

Props:
public ?string $selectedType;

Renders a horizontal pill/tab bar:
[🌍 All]  [📍 Locations]  [🏛 Organisations]
[📢 Campaigns]  [🎓 Courses]  [💡 Themes]

Active pill is highlighted.
Each pill emits wire:click calling $parent.selectType(value).
"All" passes null.

View: resources/views/livewire/explore/community-type-filter.blade.php

═══════════════════════════════════════════════════════════════
STEP 4: BREADCRUMB COMPONENT
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/Breadcrumb.php (or a Blade component)

Props:
public array   $breadcrumb;
public ?string $selectedType;

Renders:
📍 South Africa  ›  Western Cape  ›  Garden Route DM

"South Africa" always present and clickable (calls
$parent.navigateToBreadcrumb(null)).
Each subsequent crumb is clickable.
Last crumb is not a link (current location).
Append the type label if a type is selected:
📍 South Africa  ›  Western Cape  ›  Campaigns

View: resources/views/livewire/explore/breadcrumb.blade.php

═══════════════════════════════════════════════════════════════
STEP 5: COLUMN BROWSER COMPONENT
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/ColumnBrowser.php

Props (passed from parent):
public Collection $communities;
public ?string    $selectedType;
public ?int       $selectedCircleId;

Behaviour:
When selectedType is null or LocationCommunity:
Show communities as a navigable list in a column panel.
Each item is clickable — calls $parent.selectCircle(id)
Selected item is highlighted.
Clicking a LocationCommunity loads its children in the
next column (handled by parent recomputing communities()).

When selectedType is a non-location type:
Show communities as cards (not navigable columns).
Each card shows: name, description excerpt, member count.
Clicking opens a detail modal (Step 7).

The column panel should feel like a file browser:
┌──────────────────────────────────────┐
│  Western Cape                        │
│  ──────────────────────────────────  │
│  ▸ City of Cape Town          Metro  │
│  ▸ Cape Winelands DM       selected  │ ←
│  ▸ Garden Route DM                   │
│  ▸ Overberg DM                       │
│  ▸ West Coast DM                     │
│  ▸ Central Karoo DM                  │
└──────────────────────────────────────┘

Show the locatable type as a small badge next to each item
(Province, DM, Local Muni, City, Metro).

View: resources/views/livewire/explore/column-browser.blade.php

═══════════════════════════════════════════════════════════════
STEP 6: EMPTY STATE COMPONENT
═══════════════════════════════════════════════════════════════

Create a Blade component:
resources/views/components/explore/empty-state.blade.php

Props:
$icon, $heading, $subheading,
$ctaLabel, $ctaAction,
$belowCount (int), $belowLabel (string)

Renders:

┌──────────────────────────────────────┐
│                                      │
│           {icon}                     │
│                                      │
│        {heading}                     │
│        {subheading}                  │
│                                      │
│      [ {ctaLabel} ]                  │
│                                      │
│   ─────────────────────────────      │
│   {belowCount} {belowLabel}          │
│   in sub-regions  ›                  │
│                                      │
└──────────────────────────────────────┘

Hide the "in sub-regions" section if belowCount is 0.

The parent ExploreCommunities passes the correct values
based on the three states:

State 1 (communities exist):
→ Don't show EmptyState, show ColumnBrowser/cards

State 2 (none at this level, but exist below):
→ Show EmptyState with belowCount > 0
→ heading: "No {type} at {level} level yet"
→ subheading: "Be the first to start one."
→ ctaLabel: "+ Start a {singular}"

State 3 (none anywhere in this branch):
→ Show EmptyState with belowCount = 0
→ heading: "No {type} here yet"
→ subheading: "This is a fresh space waiting to grow."
→ ctaLabel: "+ Be the first"

═══════════════════════════════════════════════════════════════
STEP 7: COMMUNITY CARD + DETAIL MODAL
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/CommunityCard.php

Props:
public Circle $circle;

Displays:
- Icon (from circleable type)
- Name
- Description (truncated to 2 lines)
- Geographic level badge (Province / DM / City etc.)
- Member count (placeholder: 0 for now)
- "View" button → opens detail modal

Create app/Livewire/Explore/CommunityDetail.php (modal)

Displays full community info:
- Name + description
- Geographic location (breadcrumb style)
- Available services (icons for each active service)
- Member count
- [ Join Community ] button (placeholder for now)
- [ Close ] button

Use wire-elements/modal package for the modal:
composer require wire-elements/modal

Views:
resources/views/livewire/explore/community-card.blade.php
resources/views/livewire/explore/community-detail.blade.php

═══════════════════════════════════════════════════════════════
STEP 8: MAP VIEW COMPONENT
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/MapView.php

Use the SVG map of South Africa provinces.
Download from:
https://github.com/datasets/geo-boundaries-world-110m
or use a simple hand-crafted SA provinces SVG.

Save to: resources/svg/south-africa-provinces.svg

Each province path in the SVG must have:
data-province="{province_name}"
class="province-region"

Behaviour:
- Hovering a province highlights it
- Clicking a province calls wire:click="selectProvince(name)"
- Selected province is filled with brand colour
- A sidebar panel appears on the right showing:
    - Province name
    - List of communities at that level matching selectedType
    - Count of communities in sub-regions
    - "Browse this province →" button

Alpine.js handles the hover/highlight on the SVG directly.
Livewire handles the data fetch when a province is clicked.

Wire action:
public function selectProvince(string $provinceName): void
// Finds the Province by name
// Finds its LocationCommunity circle
// Calls selectCircle($circle->id)

View: resources/views/livewire/explore/map-view.blade.php

═══════════════════════════════════════════════════════════════
STEP 9: SEARCH OVERLAY
═══════════════════════════════════════════════════════════════

Create app/Livewire/Explore/SearchOverlay.php

Behaviour:
- Activated by clicking the 🔍 Search button
- Full-width search input with live results
- Searches circle.name (LIKE %term%)
- Optionally filtered by selectedType if one is active
- Results show name + geographic breadcrumb + type badge
- Clicking a result:
    - Closes search
    - Navigates to that circle's location in the browser
    - Opens its detail modal

Wire properties:
public string $query = '';
public bool   $open  = false;

#[Computed]
public function results(): Collection
{
if (strlen($this->query) < 2) return collect();

      return Circle::where('name', 'like', "%{$this->query}%")
                   ->when($this->selectedType, fn($q) =>
                       $q->whereHasMorph('circleable',
                           [CommunityType::from($this->selectedType)->modelClass()])
                   )
                   ->with('circleable')
                   ->limit(10)
                   ->get();
}

View: resources/views/livewire/explore/search-overlay.blade.php

═══════════════════════════════════════════════════════════════
STEP 10: MAIN EXPLORE VIEW — ASSEMBLE ALL COMPONENTS
═══════════════════════════════════════════════════════════════

Create resources/views/livewire/explore/explore-communities.blade.php

Layout:

┌─────────────────────────────────────────────────────┐
│  EXPLORE COMMUNITIES                    [🔍 Search] │
├─────────────────────────────────────────────────────┤
│  <livewire:explore.community-type-filter />         │
├─────────────────────────────────────────────────────┤
│  <livewire:explore.breadcrumb />                    │
│                      [🗺 Map]  [☰ Browse]           │
├─────────────────────────────────────────────────────┤
│                                                     │
│  @if($viewMode === 'browse')                        │
│    @if($communities->isNotEmpty())                  │
│      <livewire:explore.column-browser />            │
│    @else                                            │
│      <x-explore.empty-state ... />                  │
│    @endif                                           │
│  @else                                              │
│    <livewire:explore.map-view />                    │
│  @endif                                             │
│                                                     │
│  <livewire:explore.search-overlay />                │
│  <livewire:modal />  (wire-elements/modal)          │
└─────────────────────────────────────────────────────┘

Mobile behaviour:
- Type filter scrolls horizontally
- Column browser becomes a single column with back button
- Map view shows full width with bottom sheet for results
- Search overlays full screen

═══════════════════════════════════════════════════════════════
FOLDER STRUCTURE SUMMARY
═══════════════════════════════════════════════════════════════

app/Livewire/Explore/
ExploreCommunities.php
CommunityTypeFilter.php
Breadcrumb.php
ColumnBrowser.php
CommunityCard.php
CommunityDetail.php
MapView.php
SearchOverlay.php

resources/views/
livewire/explore/
explore-communities.blade.php
community-type-filter.blade.php
breadcrumb.blade.php
column-browser.blade.php
community-card.blade.php
community-detail.blade.php
map-view.blade.php
search-overlay.blade.php
components/explore/
empty-state.blade.php

resources/svg/
south-africa-provinces.svg

═══════════════════════════════════════════════════════════════
DEPENDENCIES TO INSTALL FIRST
═══════════════════════════════════════════════════════════════

composer require wire-elements/modal
(Livewire 4 and Filament already installed)

Tailwind CSS already available via Filament.
Alpine.js already available via Livewire 4.

═══════════════════════════════════════════════════════════════
NOTES FOR IMPLEMENTATION
═══════════════════════════════════════════════════════════════

1. All queries use eager loading to avoid N+1 problems:
   Circle::with(['circleable', 'locatable', 'services'])

2. The path column on circles enables all ancestor/descendant
   queries without recursive CTEs.

3. Member counts are placeholders (0) for now — the
   membership system will be implemented separately.

4. The [ Join Community ] and [ Start a Community ] buttons
   are placeholders — emit Livewire events for now.

5. Do not implement authentication checks yet — the Explore
   page is fully public. Auth will be layered on later.

6. Use Tailwind utility classes throughout — no custom CSS
   unless absolutely necessary.

7. Each Livewire component should be independently testable
   with a simple render test.
