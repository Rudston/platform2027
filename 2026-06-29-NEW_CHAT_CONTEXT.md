# Platform 2027 — New Chat Context Document

Paste this at the start of any new conversation to restore context.

---

## What We Are Building

Platform 2027 is a South African civic platform providing persistent
collaborative spaces for communities across South Africa. It facilitates
citizen collaboration across location-based and theme-based lines.
No political parties have access.

## Tech Stack

- Laravel 12, PHP 8.2+, MySQL
- Livewire 4 (NOT Livewire 3)
- Tailwind CSS 4 via Vite (NO tailwind.config.js)
- Alpine.js (via Livewire 4)
- Filament (admin panels + forms)
- Spatie Laravel Permission (teams enabled, team_foreign_key = circle_id)
- wire-elements/modal

---

## The Core Concept: Circles

Every community on the platform is a **Circle** — a collaborative
container. Circles wrap a community type (via polymorphic circleable)
and are anchored to a geographic level (via polymorphic locatable).
Circles are hierarchical via parent_id and use a materialized path
column for efficient tree queries.

### Community types (circleable):
- LocationCommunity — geographic communities
- ThemeCommunity — topic/issue-based communities
- OrganisationCommunity — organisation spaces
- CourseCommunity — training/course spaces
- Campaign — campaign spaces

### Geographic levels (locatable):
Country → Province → DistrictMunicipality → LocalMunicipality/City → MainPlace

Every circle has at least a Country-level location (mandatory, not nullable).

---

## Key Architectural Decisions Already Made

1. **Interface + Trait pattern** (not base classes) for communities
    - Circleable interface + HasCircle trait
    - Locatable interface + HasLocation trait
    - ProvidesCircleIdentity interface on all demography models

2. **Enums** for type safety:
    - CommunityType — maps to community model class paths
    - LocatableType — maps to demography model class paths

3. **CircleCreationService** — single service for creating any circle,
   handles name/description auto-population, default services attachment,
   wrapped in DB transaction

4. **Spatie teams** — circle_id nullable on pivot tables (custom migration)
   so global roles and circle-scoped roles coexist

5. **OrganisationCommunity ≠ Organisation** — community wrapper is
   separate from the entity (one-to-one). Same pattern for Course/CourseCommunity.

6. **circle_associations** pivot for cross-community links — preserves
   single parent hierarchy, includes approval fields

7. **Auth built manually** — Breeze was rejected (incompatible with
   Tailwind 4 + Livewire 4)

8. **Explore page** — public Livewire 4 page with column browser,
   type filter, breadcrumb navigation, search, empty states

---

## What Has Been Built

### Database / Models
- Full demography hierarchy (Country → Province → DM → LM/City → MainPlace)
- All 5 community models in app/Models/Communities/
- Organisation and Course entity models in app/Models/
- Circle and Service models in app/Models/Circles/
- All migrations including circle_associations
- Spatie roles seeded: new_user, full_member, curator, trainer,
  admin, superadmin, circle_admin, circle_full_member, circle_visitor
- 9 service stubs seeded

### Seeders run
- LocationCommunitiesSeeder (~296 nested location circles)
- ThemeCommunitiesSeeder (national + WC province + Eden DM)
- South African demography data (provinces, DMs, LMs, cities, main places)

### Services
- CircleCreationService
- CircleMembershipService
- 9 service handler stubs

### Explore Page
- ExploreCommunities (parent Livewire component)
- CommunityTypeFilter, Breadcrumb, ColumnBrowser,
  CommunityCard, CommunityDetail, SearchOverlay
- MapView stub (disabled — "coming soon")
- Empty state component

### Authentication (manual, Livewire 4)
- Login, Register, ForgotPassword, ResetPassword components
- LogoutController
- Guest and authenticated layouts
- Dashboard view

---

## What Is NOT Yet Built

- Full membership system (circle_user pivot + approval workflow)
- Campaign model fields
- Filament admin panels
- Map view for Explore page (SVG sourcing in progress)
- User profile pages
- Notification, voting, social media, learning systems
  (service stubs exist, full implementation pending)
- Payment/subscription system
- API endpoints

---

## Important Rules for This Project

1. NEVER install Laravel Breeze, Jetstream, or any auth scaffold
2. NEVER modify resources/views/layouts/app.blade.php
3. NEVER remove existing routes from routes/web.php — only add
4. NEVER add tailwind.config.js — Tailwind 4 is configured via Vite
5. ALWAYS read files before modifying them
6. ALWAYS do one step at a time and stop for review
7. The Explore page (/explore) is always public — no auth middleware
8. CommunityType enum CASE NAMES never change — only values if needed
9. circle_id is nullable on Spatie pivot tables — this is intentional

---

## Folder Structure (key paths)

```
app/
  Contracts/          Circleable, Locatable,
                      CircleServiceContract, ProvidesCircleIdentity
  Enums/              CommunityType, LocatableType
  Livewire/Auth/      Login, Register, ForgotPassword, ResetPassword
  Livewire/Explore/   ExploreCommunities + sub-components
  Models/Circles/     Circle, Service
  Models/Communities/ OrganisationCommunity, Campaign, CourseCommunity,
                      LocationCommunity, ThemeCommunity
  Models/Demography/  Country, Province, DistrictMunicipality,
                      LocalMunicipality, City, MainPlace, UrbanPlace
  Models/             Organisation, Course, User
  Services/Circles/   CircleCreationService, CircleMembershipService,
                      + 9 service handlers
  Traits/             HasCircle, HasLocation

resources/views/
  layouts/            app.blade.php (Explore),
                      guest.blade.php, authenticated.blade.php
  livewire/auth/      login, register, forgot-password, reset-password
  livewire/explore/   all explore components
  components/explore/ empty-state
  welcome.blade.php
  dashboard.blade.php
  
# Platform 2027 — Explore UI Supplement

Add this to NEW_CHAT_CONTEXT.md for detailed Explore page design context.

---

## Explore Page — Full UI Design Reference

### Route
GET /explore — fully public, no auth required.

---

## Overall Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  EXPLORE COMMUNITIES                        [🔍 Search]     │
├─────────────────────────────────────────────────────────────┤
│  [🌍 All] [📍 Locations] [🏛 Organisations]                 │
│  [📢 Campaigns] [🎓 Courses] [💡 Themes]                    │
├─────────────────────────────────────────────────────────────┤
│  📍 South Africa › Western Cape › Eden DM › Themes         │
│                                    [🗺 Map] [☰ Browse]      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   [Main content area — column browser OR map view]         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Community Type Filter Bar

A horizontal pill/tab bar. Cases:

```
[🌍 All]  [📍 Locations]  [🏛 Organisations]
[📢 Campaigns]  [🎓 Courses]  [💡 Themes]
```

### Icons per type
- null (All):          🌍
- LocationCommunity:   📍
- Organisation:        🏛
- Campaign:            📢
- Course:              🎓
- ThemeCommunity:      💡

### Labels (plural, for filter bar)
- null:                "All"
- LocationCommunity:   "Locations"
- Organisation:        "Organisations"
- Campaign:            "Campaigns"
- Course:              "Courses"
- ThemeCommunity:      "Themes"

### Singular labels (for empty states / CTAs)
- Organisation:        "Organisation"
- Campaign:            "Campaign"
- Course:              "Course"
- ThemeCommunity:      "Theme Community"
- LocationCommunity:   "Location Community"

### Behaviour
- Active pill is highlighted (brand colour fill)
- Clicking a pill sets selectedType
- CRITICAL: switching type NEVER resets the geographic
  selection (selectedCircleId) or breadcrumb
- On mobile: scrolls horizontally

---

## Breadcrumb Component

### Format
```
📍 South Africa  ›  Western Cape  ›  Eden DM  ›  Themes
```

### Rules
- "South Africa" is ALWAYS the first crumb (id = null)
- Each geographic crumb is clickable — jumps back to that level
- Last geographic crumb is NOT a link (current location)
- Type label appended when a non-Location type is selected
- Type label removed when switching back to All/Locations
- Clicking any crumb trims the breadcrumb back to that point
- Geographic crumbs are PRESERVED when switching type

### Examples
```
No type selected:
📍 South Africa

Province selected, no type:
📍 South Africa  ›  Western Cape

Province selected, Themes type:
📍 South Africa  ›  Western Cape  ›  Themes

DM selected, Campaigns type:
📍 South Africa  ›  Western Cape  ›  Eden DM  ›  Campaigns

Switch to Courses (same geographic level):
📍 South Africa  ›  Western Cape  ›  Eden DM  ›  Courses
```

---

## Column Browser (Browse View)

### When selectedType is null or LocationCommunity

Three-panel file-browser style (like macOS Finder columns).
Each click on a location loads its children in the next panel.

```
┌──────────────────┬─────────────────────┬──────────────────────┐
│  SOUTH AFRICA    │  WESTERN CAPE        │  CAPE WINELANDS DM   │
│                  │                      │                      │
│  Western Cape ●  │  City of Cape Town   │  Drakenstein         │
│  KZN             │  Cape Winelands DM ● │  Stellenbosch        │
│  Gauteng         │  Garden Route DM     │  Witzenberg          │
│  Eastern Cape    │  Overberg DM         │  Breede Valley  ●    │
│  Free State      │  West Coast DM       │  Langeberg           │
│  ...             │  Central Karoo DM    │                      │
└──────────────────┴─────────────────────┴──────────────────────┘
```

- Selected item highlighted in each column
- Small badge showing level type next to each item:
  Province / DM / Local Muni / City / Metro
- Clicking an item loads its children in the next column
- Columns scroll independently if content overflows

### When selectedType is a non-Location type

Column browser switches to a card grid layout in the
main area. Communities of the selected type at the
selected geographic level shown as cards.

---

## Community Card

Displayed in card grid when a non-Location type is selected,
or when drilling into a specific location community.

```
┌─────────────────────────────────┐
│  📢  [Type icon]                │
│                                 │
│  Community Name                 │
│  Description truncated to       │
│  2 lines of text...             │
│                                 │
│  [Province badge]  [0 members]  │
│                                 │
│  [also here] ← if associated    │
│                                 │
│         [ View → ]              │
└─────────────────────────────────┘
```

### "also here" badge
Communities that appear via circle_associations
(not direct children) show a subtle "also here" badge.
This distinguishes them from communities whose primary
home is this circle.

---

## Community Detail Modal (wire-elements/modal)

Opens when "View" is clicked on a card.

```
┌────────────────────────────────────────┐
│  📢  Campaign Name              [✕]   │
│  ─────────────────────────────────     │
│  📍 South Africa › Western Cape        │
│                                        │
│  Full description text here.           │
│                                        │
│  Services available:                   │
│  [📅 Events] [🗳 Voting] [📣 News]    │
│                                        │
│  Members: 0                            │
│                                        │
│  [ Join Community ]  [ Close ]         │
└────────────────────────────────────────┘
```

- Join Community button: placeholder for now (emits event)
- Services shown as icon badges for each active service

---

## Empty States

Three distinct states when communities() returns empty.

### State 1: Communities exist → show cards/column browser
(not an empty state — normal display)

### State 2: None at this level, but exist below

```
┌──────────────────────────────────────┐
│                                      │
│           📢                         │
│                                      │
│   No Campaigns at Province level yet │
│                                      │
│   Be the first to start one that     │
│   matters to all of Western Cape.    │
│                                      │
│      [ + Start a Campaign ]          │
│                                      │
│   ──────────────────────────         │
│   14 Campaigns in sub-regions  ›     │
│                                      │
└──────────────────────────────────────┘
```

- Count of communities below powered by path LIKE query
- "› View all" drills down to find them
- CTA: "+ Start a {singular}"

### State 3: None anywhere in this branch

```
┌──────────────────────────────────────┐
│                                      │
│           🌱                         │
│                                      │
│      No Campaigns here yet           │
│                                      │
│   This is a fresh space waiting      │
│   to grow.                           │
│                                      │
│      [ + Be the first ]              │
│                                      │
└──────────────────────────────────────┘
```

### Empty state props (Blade component)
x-explore.empty-state accepts:
- $icon        — emoji
- $heading     — main message
- $subheading  — supporting text
- $ctaLabel    — button text
- $ctaAction   — wire:click action
- $belowCount  — int, communities in sub-regions
- $belowLabel  — e.g. "Campaigns in sub-regions"
(hide below section if $belowCount === 0)

---

## Search Overlay

Activated by clicking 🔍 Search button.
Overlays the full page.

### Behaviour
- Minimum 2 characters before results appear
- Searches circle.name (LIKE %term%)
- Optionally filtered by selectedType if one is active
- Results show: name + geographic breadcrumb + type badge
- Maximum 10 results
- Clicking a result:
  - Closes search overlay
  - Navigates to that circle's location in the browser
  - Opens its detail modal

### Livewire properties
- query: string = ''
- open: bool = false

---

## Map View (DEFERRED — Phase 2)

### Current state
The [🗺 Map] toggle button is visible but disabled
with a "Coming soon" tooltip.
No SVG map is loaded yet.

### Planned behaviour (for when implemented)
- Clickable SVG map of SA provinces
- Clicking a province highlights it and shows sidebar
- Sidebar lists communities of selectedType at that level
- Shows count of communities in sub-regions
- "Browse this province →" button
- Alpine.js handles hover/highlight on SVG
- Livewire handles data fetch on click

### Recommended SVG source
amCharts SA provinces SVG:
https://www.amcharts.com/svg-maps/?map=southAfrica
Each province path needs data-province="{name}" attribute.

---

## Mobile Behaviour

- Type filter bar: scrolls horizontally
- Column browser: single column with back button
  (instead of three side-by-side panels)
- Map view: full width with bottom sheet for results
- Search: overlays full screen
- Geographic selector collapses to dropdown on small screens

---

## View Mode Toggle

```
[🗺 Map]  [☰ Browse]
```

- Browse is the default
- Map is disabled with "Coming soon" tooltip
- Toggle sits in the breadcrumb row, right-aligned
- Switching view mode preserves both selectedType
  and selectedCircleId

---

## Key Interaction Rules (Summary)

1. Switching community TYPE → preserves geographic selection
2. Clicking geographic item → preserves selected type
3. Clicking breadcrumb crumb → preserves selected type,
   trims geographic trail back to that crumb
4. "South Africa" crumb always present and always clickable
5. Type label in breadcrumb is display-only — not clickable
6. "also here" communities merged into results but badged
7. Join/Start buttons are placeholders — emit Livewire events
8. All queries use eager loading: with(['circleable','locatable','services'])
9. Path column used for all ancestor/descendant queries
   (no recursive CTEs needed)
10. Map view disabled until SVG sourced and integrated

```
# Platform 2027 — Explore UI Supplement

IMPORTANT: Detailed Explore page design context (companion to CLAUDE.md).

---

## Explore Page — Full UI Design Reference

### Route
GET /explore — fully public, no auth required.

---

## Overall Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  EXPLORE COMMUNITIES                        [🔍 Search]      │
├─────────────────────────────────────────────────────────────┤
│  [🌍 All] [📍 Locations] [💡 Theme Communities]              │
│  [🏛 Organisations] [📢 Campaigns] [🎓 Courses] [📅 Events]  │
├─────────────────────────────────────────────────────────────┤
│  📍 South Africa › Western Cape › Eden DM › Theme Communities │
│                                    [🗺 Map] [☰ Browse]       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   [Main content area — column browser OR map view]          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Community Type Filter Bar

A horizontal pill/tab bar. Order (left → right):

```
[🌍 All]  [📍 Locations]  [💡 Theme Communities]
[🏛 Organisations]  [📢 Campaigns]  [🎓 Courses]  [📅 Events]
```

Theme Communities sits immediately after Locations.

### Icons per type
- null (All):          🌍
- LocationCommunity:   📍
- ThemeCommunity:      💡
- Organisation:        🏛
- Campaign:            📢
- Course:              🎓
- Event:               📅

### Labels (plural, for filter bar)
- null:                "All"
- LocationCommunity:   "Locations"
- ThemeCommunity:      "Theme Communities"
- Organisation:        "Organisations"
- Campaign:            "Campaigns"
- Course:              "Courses"
- Event:               "Events"

### Singular labels (for empty states / CTAs)
- LocationCommunity:   "Location"
- ThemeCommunity:      "Theme"
- Organisation:        "Organisation"
- Campaign:            "Campaign"
- Course:              "Course"
- Event:               "Event"

### Behaviour
- Active pill is highlighted (brand colour fill)
- Clicking a pill sets selectedType
- CRITICAL: switching type NEVER resets the geographic
  selection (selectedCircleId) or breadcrumb
- On mobile: scrolls horizontally

---

## Breadcrumb Component

### Format
```
📍 South Africa  ›  Western Cape  ›  Eden DM  ›  Theme Communities
```

### Rules
- "South Africa" is ALWAYS the first crumb (id = null)
- Each geographic crumb is clickable — jumps back to that level
- Last geographic crumb is NOT a link (current location)
- Type label appended when a non-Location type is selected
- Type label removed when switching back to All/Locations
- Clicking any crumb trims the breadcrumb back to that point
- Geographic crumbs are PRESERVED when switching type

### Examples
```
No type selected:
  📍 South Africa

Province selected, no type:
  📍 South Africa  ›  Western Cape

Province selected, Theme Communities type:
  📍 South Africa  ›  Western Cape  ›  Theme Communities

DM selected, Campaigns type:
  📍 South Africa  ›  Western Cape  ›  Eden DM  ›  Campaigns

Switch to Courses (same geographic level):
  📍 South Africa  ›  Western Cape  ›  Eden DM  ›  Courses
```

---

## Column Browser (Browse View)

### When selectedType is null or LocationCommunity

File-browser style list that drills down. Each click on a
location loads its children.

```
┌──────────────────┬─────────────────────┬──────────────────────┐
│  SOUTH AFRICA    │  WESTERN CAPE        │  CAPE WINELANDS DM   │
│                  │                      │                      │
│  Western Cape ●  │  City of Cape Town   │  Drakenstein         │
│  KZN             │  Cape Winelands DM ● │  Stellenbosch        │
│  Gauteng         │  Garden Route DM     │  Witzenberg          │
│  Eastern Cape    │  Overberg DM         │  Breede Valley  ●    │
│  Free State      │  West Coast DM       │  Langeberg           │
│  ...             │  Central Karoo DM    │                      │
└──────────────────┴─────────────────────┴──────────────────────┘
```

- Selected item highlighted in each column
- Small badge showing level type next to each item:
  Country / Province / DM / Local Muni / City / Metro
- Clicking an item loads its children in the next column
- Columns scroll independently if content overflows

### "Also here" badge (location browse mode)

Circles linked in via APPROVED `circle_associations`
(i.e. not direct children) are merged into this location
list and carry a subtle "Also here" badge, distinguishing
them from circles whose primary home is the current circle.

NOTE: the badge currently lives ONLY in the location
column-browser list, because that is where the association
merge happens (children + approvedAssociatedBy, deduped by
id, native children winning). It is NOT on the non-location
cards — passing a Circle into the child CommunityCard
Livewire component re-serialises it and drops the transient
`also_here` flag. Surfacing it on cards is future work.

### When selectedType is a non-Location type

The browser switches to a card grid in the main area.
Communities of the selected type at the selected geographic
level are shown as cards.

---

## Community Card

Displayed in the card grid when a non-Location type is selected.

```
┌─────────────────────────────────┐
│  📢  [Type icon]                │
│                                 │
│  Community Name                 │
│  Description truncated to       │
│  2 lines of text...             │
│                                 │
│  [Provincial badge]  [0 members]│
│                                 │
│         [ View → ]              │
└─────────────────────────────────┘
```

### Level badge (card)
Short badge for the community's geographic level
(CommunityCard::levelBadge):
- Country:              "National"
- Province:             "Provincial"
- DistrictMunicipality: "DM"
- LocalMunicipality:    "LM"
- City:                 "City" (or "Metro" if locatable->metropolis)

Member count is a placeholder (0) until membership is built.

---

## Community Detail Modal (wire-elements/modal)

Opens when "View" is clicked on a card.

```
┌────────────────────────────────────────┐
│  📢  Campaign Name              [✕]   │
│  ─────────────────────────────────     │
│  📍 South Africa › Western Cape        │
│                                        │
│  Full description text here.           │
│                                        │
│  Services available:                   │
│  [📅 Events] [🗳 Voting] [📣 News]    │
│                                        │
│  Members: 0                            │
│                                        │
│  [ Join Community ]  [ Close ]         │
└────────────────────────────────────────┘
```

- Join Community button: placeholder for now (emits event)
- Services shown as icon badges for each active service

---

## Empty States

Three distinct states when communities() returns empty.

### State 1: Communities exist → show cards/column browser
(not an empty state — normal display)

### State 2: None at this level, but exist below

```
┌──────────────────────────────────────┐
│                                      │
│           📢                         │
│                                      │
│   No Campaigns at Province level yet │
│                                      │
│   Be the first to start one that     │
│   matters to all of Western Cape.    │
│                                      │
│      [ + Start a Campaign ]          │
│                                      │
│   ──────────────────────────         │
│   14 Campaigns in sub-regions  ›     │
│                                      │
└──────────────────────────────────────┘
```

- Count of communities below powered by path LIKE query
- "› View all" drills down to find them
- CTA: "+ Start a {singular}"

### State 3: None anywhere in this branch

```
┌──────────────────────────────────────┐
│                                      │
│           🌱                         │
│                                      │
│      No Campaigns here yet           │
│                                      │
│   This is a fresh space waiting      │
│   to grow.                           │
│                                      │
│      [ + Be the first ]              │
│                                      │
└──────────────────────────────────────┘
```

### Empty state props (Blade component)
x-explore.empty-state accepts:
- $icon        — emoji
- $heading     — main message
- $subheading  — supporting text
- $ctaLabel    — button text
- $ctaAction   — wire:click action
- $belowCount  — int, communities in sub-regions
- $belowLabel  — e.g. "Campaigns in sub-regions"
  (hide below section if $belowCount === 0)

---

## Search Overlay

Activated by clicking 🔍 Search button.
Overlays the full page.

### Behaviour
- Minimum 2 characters before results appear
- Searches circle.name (LIKE %term%)
- Optionally filtered by selectedType if one is active
- Results show: name + geographic breadcrumb + type badge
  (type badge uses the singular label: Location, Theme,
  Organisation, Campaign, Course, Event)
- Maximum 10 results
- Clicking a result:
    - Closes search overlay
    - Navigates to that circle's location in the browser
    - Opens its detail modal

### Livewire properties
- query: string = ''
- open: bool = false

---

## Map View (DEFERRED — Phase 2)

### Current state
The [🗺 Map] toggle button is visible but disabled
with a "Coming soon" tooltip.
No SVG map is loaded yet.

### Planned behaviour (for when implemented)
- Clickable SVG map of SA provinces
- Clicking a province highlights it and shows sidebar
- Sidebar lists communities of selectedType at that level
- Shows count of communities in sub-regions
- "Browse this province →" button
- Alpine.js handles hover/highlight on SVG
- Livewire handles data fetch on click

### Recommended SVG source
amCharts SA provinces SVG:
https://www.amcharts.com/svg-maps/?map=southAfrica
Each province path needs data-province="{name}" attribute.

---

## Mobile Behaviour

- Type filter bar: scrolls horizontally
- Column browser: single column with back button
  (instead of three side-by-side panels)
- Map view: full width with bottom sheet for results
- Search: overlays full screen
- Geographic selector collapses to dropdown on small screens

---

## View Mode Toggle

```
[🗺 Map]  [☰ Browse]
```

- Browse is the default
- Map is disabled with "Coming soon" tooltip
- Toggle sits in the breadcrumb row, right-aligned
- Switching view mode preserves both selectedType
  and selectedCircleId

---

## Key Interaction Rules (Summary)

1. Switching community TYPE → preserves geographic selection
2. Clicking geographic item → preserves selected type
3. Clicking breadcrumb crumb → preserves selected type,
   trims geographic trail back to that crumb
4. "South Africa" crumb always present and always clickable
5. Type label in breadcrumb is display-only — not clickable
6. "Also here" circles (approved circle_associations) are
   merged into the location browse list and badged "Also here"
7. Join/Start buttons are placeholders — emit Livewire events
8. All queries use eager loading: with(['circleable','locatable','services'])
9. Path column used for all ancestor/descendant queries
   (no recursive CTEs needed)
10. Map view disabled until SVG sourced and integrated
```
