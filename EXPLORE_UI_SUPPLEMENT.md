# Platform 2027 — Explore UI Supplement

Detailed Explore page design context (companion to CLAUDE.md).

---

## Explore Page — Full UI Design Reference

### Route
GET /explore — fully public, no auth required.

---

## Overall Page Layout

The page is split into TWO vertically stacked sections that SHARE the
geographic selection (selectedCircleId + breadcrumb) but have INDEPENDENT
type filters. The TOP section is itself a two-column layout (50/50 on
desktop, stacked on mobile).

```
┌──────────────────────────────────────────────────────────────┐
│  TOP SECTION — geographic explorer (two columns)               │
│  ┌───────────────────────────────┬──────────────────────────┐ │
│  │ LEFT — geographic drill-down  │ RIGHT — selected location │ │
│  │                               │                           │ │
│  │ EXPLORE COMMUNITIES [🔍]      │  (card is bottom-aligned) │ │
│  │ [🌍 All]  [📍 Locations]      │                           │ │
│  │ 📍 SA › W Cape › Eden DM      │   LocationCommunity card  │ │
│  │              [🗺 Map][☰ Brws] │   for the selected place  │ │
│  │ [ location column browser ]   │   (📍 icon, level badge,  │ │
│  │                               │    name, [ View → ])      │ │
│  └───────────────────────────────┴──────────────────────────┘ │
├──────────────────────────────────────────────────────────────┤  ← divider
│  BOTTOM SECTION — community types at the selected location     │
│                                                                │
│  Communities in Eden DM                                        │
│  [💡 Theme Communities] [🏛 Organisations] [📢 Campaigns]      │
│  [🎓 Courses] [📅 Events]                                      │
│                                                                │
│  [ card grid for the selected type (defaults to Themes) ]      │
└──────────────────────────────────────────────────────────────┘
```

The right-column card is bottom-aligned so its base sits level with the
bottom of the left column. On mobile the top two columns collapse to a
single column (left/explorer on top, right/selected-location card below).

---

## Page Structure & State Model

### Two sections, shared geography
- The geographic selection lives on the parent and is shared by both
  sections:
  - `selectedCircleId` (?int) — current circle, null = national
  - `breadcrumb` (array) — geographic trail, always starts at South Africa
- The breadcrumb + Map/Browse toggle sit in the TOP section's LEFT column.

### TOP section is two columns
- LEFT column = the geographic drill-down (header + search, All/Locations
  filter, breadcrumb + Map/Browse toggle, location column browser).
- RIGHT column = the LocationCommunity card for the currently selected
  location, driven by the SAME `selectedCircleId` (via the
  `rightColumnCircle` computed) — no extra state. It reuses the standard
  `CommunityCard` (📍 icon, level badge, name, "View →" opening the
  CommunityDetail modal), keyed by the shown circle's id so it swaps when a
  different location is clicked.
  - When nothing is selected (national level) it shows the **national
    circle's card** ("National Level Community for South Africa") rather
    than a placeholder — `rightColumnCircle` falls back to the country
    circle. (The "Select a location…" placeholder remains only as a
    fallback if no country circle exists.)
  - The card is **bottom-aligned** (the column is `flex flex-col` and the
    card has `mt-auto`) so its base sits level with the bottom of the left
    column.

### Two independent type filters
- `selectedType` (TOP) — null (All) or LocationCommunity. Drives the
  location column browser. Set by `selectType()`.
- `selectedCommunityType` (BOTTOM) — Theme / Organisation / Campaign /
  Course / Event (FQCN). **Defaults to ThemeCommunity** (set in `mount()`),
  so the bottom section shows the current location's theme communities on
  load. Drives the bottom card grid. Set by `selectCommunityType()`.

### Independence guarantees (enforced in the component)
- `selectType()` sets ONLY `selectedType` — never the geography or the
  bottom `selectedCommunityType`.
- `selectCommunityType()` sets ONLY `selectedCommunityType` — never the
  geography or the top `selectedType`.
- `selectCircle()` / `navigateToBreadcrumb()` change ONLY the geography —
  both type selections survive, and the bottom grid refreshes for the new
  location.

---

## Top Filter Bar (location explorer)

A horizontal pill bar, rendered by `CommunityTypeFilter` with `group="location"`.

```
[🌍 All]  [📍 Locations]
```

- Both pills drive the location column browser (location mode).
- Clicking a pill calls `$parent.selectType(value)`.

---

## Bottom Filter Bar (community types)

Same `CommunityTypeFilter` component with `group="community"`.
Order (left → right):

```
[💡 Theme Communities]  [🏛 Organisations]  [📢 Campaigns]
[🎓 Courses]  [📅 Events]
```

Theme Communities is the FIRST tab and is **selected by default** on page
load. Clicking a pill calls `$parent.selectCommunityType(value)`.

### Icons per type
- null (All):          🌍
- LocationCommunity:   📍
- ThemeCommunity:      💡
- Organisation:        🏛
- Campaign:            📢
- Course:              🎓
- Event:               📅

### Labels (plural, for filter bars)
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

### Behaviour (both bars)
- Active pill is highlighted (brand colour fill)
- On mobile: scrolls horizontally
- The `CommunityTypeFilter` component is parameterised by `group`
  ('location' | 'community') + a generic reactive `active` value; it
  derives the parent action (`selectType` vs `selectCommunityType`).

---

## Breadcrumb Component

Lives in the TOP section. It is now a PURELY GEOGRAPHIC trail — the top
filter is only ever All / Locations, so no community-type label is
appended (the type context now lives in the bottom section heading
instead). The component still supports a type label, but it resolves to
null for All/Locations.

### Format
```
📍 South Africa  ›  Western Cape  ›  Eden DM
```

### Rules
- "South Africa" is ALWAYS the first crumb (id = null)
- Each geographic crumb is clickable — jumps back to that level
- Last geographic crumb is NOT a link (current location)
- Clicking any crumb trims the breadcrumb back to that point
- Both type selections (top and bottom) are PRESERVED when navigating crumbs

### Examples
```
National:
  📍 South Africa

Province selected:
  📍 South Africa  ›  Western Cape

DM selected:
  📍 South Africa  ›  Western Cape  ›  Eden DM
```

---

## Column Browser (Browse View)

The same `x-explore.column-browser` Blade component renders BOTH:
- the TOP location list (when `selectedType` is null / LocationCommunity), and
- the BOTTOM card grid (when given a non-location `selectedCommunityType`).

### Location list (top section)

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

### "Also here" badge (location list only)

Circles linked in via APPROVED `circle_associations`
(i.e. not direct children) are merged into the location
list and carry a subtle "Also here" badge, distinguishing
them from circles whose primary home is the current circle.

NOTE: the badge lives ONLY in the location column-browser
list, because that is where the association merge happens
(children + approvedAssociatedBy, deduped by id, native
children winning). It is NOT on the bottom-section cards —
passing a Circle into the child CommunityCard Livewire
component re-serialises it and drops the transient
`also_here` flag. Surfacing it on cards is future work.

### Card grid (bottom section)

When the bottom `selectedCommunityType` is set, the browser renders a card
grid of communities of that type at the selected geographic level.

---

## Bottom Section Content

```
Communities in {current place}
{Organisations, campaigns, courses, theme communities and events ...}

[💡 Theme Communities] [🏛 Organisations] [📢 Campaigns] [🎓 Courses] [📅 Events]

[ content ]
```

On load the bottom tab defaults to **Theme Communities**, so a type is
always selected — the "Pick a community type" prompt below only appears in
the edge case where `selectedCommunityType` is somehow null.

Content states:
- Type picked (default Theme Communities), communities exist: card grid
  (x-explore.column-browser).
- Type picked, none here but some below: State 2 empty state with the
  sub-region count (typeCommunitiesCountBelow).
- Type picked, none anywhere in branch: State 3 empty state.
- No type picked (`selectedCommunityType === null`, fallback only): a dashed
  prompt "Pick a community type above to see what's here."

The heading uses the current breadcrumb location name (e.g. "Eden DM" or
"South Africa" at national level).

---

## Community Card

Displayed in the bottom-section card grid.

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

Both sections use the same `x-explore.empty-state` Blade component.
Three distinct states when the relevant communities query returns empty.

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
- $ctaAction   — wire:click action (startCommunity for the top section,
                 startCommunityType for the bottom section)
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
- Optionally filtered by the TOP selectedType if one is active
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
The [🗺 Map] toggle button (top section) is visible but disabled
with a "Coming soon" tooltip.
No SVG map is loaded yet.

### Planned behaviour (for when implemented)
- Clickable SVG map of SA provinces
- Clicking a province highlights it and shows sidebar
- Sidebar lists communities at that level
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

- Top section: the two columns collapse to one — geographic explorer on
  top, selected-location card below
- Both filter bars: scroll horizontally
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

- Browse is the default; lives in the top section's breadcrumb row, right-aligned
- Map is disabled with "Coming soon" tooltip
- Switching view mode preserves selectedType, selectedCommunityType
  and selectedCircleId

---

## Key Interaction Rules (Summary)

1. Switching the TOP type (All / Locations) → preserves geography AND the
   bottom section's selected type
2. Switching the BOTTOM type → preserves geography AND the top type
3. Clicking a geographic item → preserves BOTH type selections
4. Clicking a breadcrumb crumb → preserves both type selections, trims the
   geographic trail back to that crumb; the bottom grid re-queries the new
   location
5. "South Africa" crumb always present and always clickable
6. Breadcrumb is geographic-only (no community-type label appended)
7. "Also here" circles (approved circle_associations) are merged into the
   location list and badged "Also here" (top section only)
8. Join/Start buttons are placeholders — emit Livewire events
9. All queries use eager loading: with(['circleable','locatable','services'])
10. Path column used for all ancestor/descendant queries
    (no recursive CTEs needed)
11. Map view disabled until SVG sourced and integrated
```
