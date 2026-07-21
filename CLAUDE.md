# CLAUDE.md — Platform 2027

This file is the authoritative reference for AI-assisted development on
Platform 2027. Read this before touching any file.

---

## Non-Negotiable Rules

1. NEVER install Laravel Breeze, Jetstream, or any auth scaffold
2. NEVER modify `resources/views/layouts/app.blade.php`
3. NEVER remove existing routes from `routes/web.php` — only add
4. NEVER add `tailwind.config.js` — Tailwind 4 is configured via Vite
5. ALWAYS read every file before modifying it
6. ALWAYS make one step at a time and stop for review
7. ALWAYS show the final state of every file you create or modify
8. NEVER proceed to the next step without explicit approval

---

## Tech Stack

- Laravel 12, PHP 8.2+, MySQL
- Livewire 4 — NOT Livewire 3 (syntax differs significantly)
- Tailwind CSS 4 via Vite — NO tailwind.config.js
- Alpine.js (bundled with Livewire 4)
- Filament v5 (filament/filament ^5.6) — admin panel at /admin
- Spatie Laravel Permission (teams mode, team_foreign_key = circle_id)
- Spatie Laravel Translatable (circles.description, content_blocks.content,
  email_templates.subject/body)
- wire-elements/modal

---

## Domain: Circles

Every community is a **Circle**. Circles are the universal container.

```php
Circle {
    circleable_type / circleable_id  // polymorphic community type
    locatable_type  / locatable_id   // polymorphic geographic anchor
    parent_id                        // hierarchical nesting
    path                             // materialised path for tree queries
    name                             // proper noun — NOT translated
    description                      // JSON (spatie/laravel-translatable)
}
```

### Community types (circleable_type)
- LocationCommunity
- ThemeCommunity
- OrganisationCommunity
- CourseCommunity
- Campaign
- Event

### Contracts and Traits
- `Circleable` interface + `HasCircle` trait — all community models
- `Locatable` interface + `HasLocation` trait — all demography models
- `HasLocationLevel` interface — all demography models (see below)
- `ProvidesCircleIdentity` — demography models providing circleName()
  and circleDescription()

---

## Geographic Hierarchy (SA)

```
Country (id=191 for South Africa)
  └── Province
        ├── DistrictMunicipality
        │     └── LocalMunicipality
        │           └── MainPlace     ← TERMINAL (isTerminal() = true)
        └── City
              └── MainPlace           ← TERMINAL (isTerminal() = true)
```

CRITICAL: City is NOT terminal — it has MainPlace children.
Only MainPlace (LocationLevel::Place) is terminal.

### Soft deletes
City, LocalMunicipality, DistrictMunicipality, MainPlace all use
SoftDeletes trait. Province and Country do not (yet).

---

## Geographic Abstraction Layer

Enables future non-SA country support without schema changes.

### LocationLevel enum (`app/Enums/LocationLevel.php`)
```php
enum LocationLevel: string {
    case Country  = 'country';
    case Region   = 'region';    // Province, State, etc.
    case District = 'district';  // DistrictMunicipality, etc.
    case Local    = 'local';     // LocalMunicipality, etc.
    case City     = 'city';      // Metropolitan — NOT terminal
    case Place    = 'place';     // MainPlace — ALWAYS terminal
}
// isTerminal(): true ONLY for Place
```

### HasLocationLevel interface (`app/Contracts/Geographic/`)
```php
interface HasLocationLevel {
    public function locationLevel(): LocationLevel;
    public function locationLabel(): string;
    public function locationParentId(): ?int;
}
```
Implemented on: Country, Province, DistrictMunicipality,
LocalMunicipality, City, MainPlace.

### LocatableType enum additions
- `locationLevel(): LocationLevel`
- `isTerminal(): bool` — proxy for locationLevel()->isTerminal()

### Usage rule
- `isTerminal()` → drives UI hints (no-further-levels message,
  "Your location not listed?" button)
- `$children->isNotEmpty()` → drives whether to render next column
  Never use isTerminal() as the sole check for rendering columns.

---

## Enums

### CommunityType
Maps community type to model class path.
CASE NAMES NEVER CHANGE — only values if needed.

### LocatableType
Maps demography type to model class path.
Now includes locationLevel() and isTerminal() methods.

### LocationLevel
See Geographic Abstraction Layer above.

### CircleStatus (`app/Enums/CircleStatus.php`)
Circle lifecycle, string-backed: Active, Pending, Denied, Suspended, Archived.
`circles.status` column (default `active`); Circle casts it and has
`scopeActive()`. New circles default to Active — approval-gated flows set
Pending explicitly. See Organisation Approval & Requests below.

### RequestType (`app/Enums/RequestType.php`)
Backs `requests.type` (string cast on the Request model). Cases:
`OrganisationApproval`, `CircleJoin`, `LocationRequest`, `CircleAssociation`
(reserved — filter/badge only, never created), `OrganisationMemberClaim`.
`RequestController::approve()/deny()` and the RequestResource type badge match
on this. NEVER compare `->type` to a bare string — the column is enum-cast.

---

## Key Services

### CircleCreationService
Single entry point for creating any circle type.
- Handles name/description auto-population
- Default services are attached by `Circle::booted()` (see Circle services
  below), not here — the service just triggers it via `Circle::create()`
- Wrapped in DB transaction
- Signature: create(type, data, parentCircle?, locatableType, locatableId)
- Circles are created with status Active (DB default) — set
  CircleStatus::Pending after creation for approval-gated types

### CircleMembershipService
Membership management (partially built).

### Circle services (as Livewire UI containers)
Services are rows in the `services` table (`key`, `handler_class`,
`container_component`) with a `CircleServiceContract` handler under
`App\Services\Circles\`. Registered/seeded by `ServicesSeeder` (9 services;
`container_component` is read off each handler, the single source of truth).

- **CircleServiceContract::containerComponent(): ?string** — FQCN of the
  Livewire component that renders the service's UI, or null (no UI). Handlers
  with no UI use the `HasNoContainerComponent` trait
  (`App\Services\Circles\Concerns\`) — e.g. Email, Manage Users. The 8 UI
  handlers return an `App\Livewire\Communities\Services\*ServiceContainer`.
- **Container components** (`App\Livewire\Communities\Services\*ServiceContainer`)
  are thin: `mount(Circle $circle)` stores the circle; a `service()` accessor
  resolves the backend handler that real data ops delegate to. Views are
  placeholders for now.
- **Default attachment** — a circleable that implements
  `App\Contracts\Circles\HasDefaultServices` (declares `defaultServices():
  array`) gets exactly those keys attached, IN ORDER, when its circle is
  created. This runs in `Circle::booted()` (created hook), so it covers ALL
  circle creation (service, seeders, tests), NOT just CircleCreationService.
  Only `LocationCommunity` implements it today
  (`news, events, forums, media, voting`); every other circleable attaches
  nothing. Check the capability via `instanceof HasDefaultServices` (NOT
  method_exists — the HasCircle trait gives everyone a `defaultServices()`
  returning []).
- **`circles:backfill-services`** (`app/Console/Commands/BackfillCircleServices.php`)
  — attaches any MISSING default services to existing HasDefaultServices
  circles (chunkById, idempotent, adds only; skips non-implementers; reports a
  count). Manual/occasional; NOT scheduled.

### CoordinateData::nearest(float $lat, float $lng): ?static
Nearest-neighbour lookup:
1. Bounding box ±0.5° + squared Euclidean ORDER BY LIMIT 1
2. Fallback: full table scan if bounding box returns 0 results
   Composite index on (latitude, longitude) exists.
   Do NOT use SQRT — squared distance sufficient for ranking.

---

## Circle Membership

Join/leave with per-community-type limits and optional internal roles.

### circle_memberships table + CircleMembership model (`app/Models/Circles/`)
- `circle_id`, `user_id`, `internal_role` (nullable — e.g. `organisation_member`;
  NOT a Spatie role), `joined_at`, `left_at` (null = active), `metadata`, timestamps.
- Rows are **never deleted** — a membership is closed by setting `left_at`.
- Indexes: `(circle_id, user_id)`, `left_at`, `user_id`. `scopeActive()` = `left_at IS NULL`.

### Membership rules (per community type)
`HasMembershipRules` (`app/Contracts/Communities/`): `maxConcurrentMemberships()`,
`minMembershipMonthsBeforeSwitch()`, `allowedInternalRoles()`. Default trait
`HasStandardMembershipRules` (`app/Models/Communities/Concerns/`) returns
`2, 3, []`. Every CommunityType model implements it; **OrganisationCommunity**
overrides `allowedInternalRoles()` → `['organisation_member']`.

### Circle methods (domain logic lives on Circle, like administrators())
- `memberships()` hasMany; `activeMembership(User): ?CircleMembership`.
- `canUserJoin(User): array{allowed, reason, available_at, swappable}` —
  global `admin`/`superadmin` bypass (NOT circle_admin); else count the user's
  ACTIVE memberships of the SAME circleable type: under `max` → allowed; at cap
  → memberships older than `minMonths` are `swappable` (allowed if any), else
  not allowed with `available_at` = earliest eligible date.
- `joinAsMember(User, ?internalRole, ?dropMembership, bool skipChecks=false)` —
  validates the role against `allowedInternalRoles()`, re-checks `canUserJoin()`
  server-side (unless `skipChecks`), closes `dropMembership` on a swap, creates
  the row. Idempotent (returns the existing active membership).
- `leave(User)` — closes the active membership. Leaving is never rate-limited.

### Where it's wired
- **Org-creator grant:** `RequestController::approve()` AND
  `RequestResource::approveAction()` call `joinAsMember($requester,
  internalRole: …, skipChecks: true)` right after granting `circle_admin`
  (direct grant, not a rate-limited join). The role is `organisation_member`
  for organisation communities, else null — matching the backfill.
- **Community Page:** `membership`/`isVisitor` computeds; both passed into every
  `*ServiceContainer` mount (`?CircleMembership $membership = null, bool
  $isVisitor = false`). Join/Leave UI replaces the old placeholder — join is
  immediate unless there's an internal-role question (org: staff/board) or a
  swap to choose (modal); `wire:confirm` on Leave.
- **Self-service circle admin:** a global admin/superadmin who has JOINED a
  circle (any type) and isn't already its circle_admin sees an "Add me as Circle
  Admin" button (left of Leave) → `Circle::addAdministrator()` (team-scoped
  assignRole, idempotent, additive to existing admins). Gated by
  `CommunityPage::canAddSelfAsCircleAdmin()`; `Circle::isAdministeredBy()` is the
  precise "is circle_admin HERE" check (narrower than isManageableBy).
  Conversely, any circle_admin sees "Remove me as Circle Admin"
  (`removeSelfAsCircleAdmin` → `Circle::removeAdministrator()`) — but if they're
  the **sole** admin the button instead alerts "Please appoint a new Circle
  Admin first" (guarded server-side too: never removes the last admin).
  (Appointing another member as circle_admin is future work.)
- **Leave is blocked while circle_admin:** the "Leave Community" button is still
  shown, but for a circle_admin it pops an alert ("Remove your Circle Admin role
  before leaving") instead of leaving; `leave()` also no-ops server-side while
  the user holds the role here. They must drop it (needs a second admin) first.
- **Explore cards:** `CommunityCard` label is **Enter** (active member) vs
  **Visit** (otherwise). Membership is batch-loaded ONCE per request in
  `ExploreCommunities::memberCircleIds()` (a small set — active memberships are
  capped) and read in-memory via `viewerIsMemberOf()`; never a per-card query.
  The card's member count is likewise batch-loaded — `memberCounts()` runs ONE
  grouped query keyed by `circle_id` for the displayed cards, read via
  `memberCountFor()`; passed in as `:member-count` (no per-card query).
- **Filament:** `CircleMembershipResource` (Governance, admin/superadmin only) —
  **read-only** list of all memberships, filterable by circle/user and
  active/closed. No create/edit.
- **Backfill:** `circles:backfill-admin-memberships` — gives every existing
  `circle_admin` (granted before the membership system) an active membership;
  organisation-community admins get `internal_role = 'organisation_member'`.
  Idempotent, adds-only, manual (NOT scheduled). Consistent with the approval
  flow, which also labels new org creators `organisation_member`.

### Internal-role claims (organisation_member must be confirmed)
A claimed internal role is NOT trusted until the org contact confirms it.
- When `joinAsMember()` gets `internal_role = 'organisation_member'` on a normal
  join (`skipChecks = false` — i.e. NOT the trusted approval-hook creator grant),
  it: creates the membership immediately with `metadata.internal_role_approved
  = 'pending'`, opens a `RequestType::OrganisationMemberClaim` request
  (`Request::createForMemberClaim`; requestable = the CircleMembership;
  respondent = the org's contact email), and emails the contact
  (`email.organisation_member_claim_request`) — the user IS a member right away;
  only the ROLE is gated.
- A **trusted** role grant (`skipChecks` — the org creator at approval, and the
  `circles:backfill-admin-memberships` command) sets `internal_role_approved =
  'approved'` outright (no claim). Any assigned `internal_role` therefore always
  carries a status; a plain member (no role) has null metadata.
- `RequestController` (same token routes) dispatches on type: approve →
  `internal_role_approved = 'approved'` + `email.organisation_member_claim_approved`
  to the claimer; reject → `'rejected'` (internal_role KEPT for audit, never
  nulled) + `email.organisation_member_claim_rejected`.
- **`CircleMembership::hasApprovedInternalRole(): bool`** — the ONLY correct way
  to check elevated access (returns true only when `internal_role` is set AND
  `metadata.internal_role_approved === 'approved'`). NEVER check `internal_role`
  alone — it may be pending or rejected.
- Filament: claim requests show in the Governance Requests table (type badge)
  but the Approve/Deny/Resend row actions are hidden for them — the flow is
  entirely external/token-based.

---

## Forums (groups)

The real implementation behind the `ForumService` / `ForumServiceContainer`
skeleton. Groups: overview + create/edit/deactivate. Discussions
(list/detail/create + a responses/comments thread with likes) are built — see
the Forum Discussions section below. Moderation UI and pin/lock toggles remain
deferred.

### Tables & models
- **forum_groups** (`app/Models/Forums/ForumGroup.php`): `circle_id` (FK cascade),
  `created_by` (FK users, **nullable + nullOnDelete** — preserve content if the
  user is deleted; SET NULL requires nullable), `name`, `slug` (nullable),
  `description`, `visibility`, `settings` (json), `status`, `archived_at`, soft
  deletes. **Unique (circle_id, slug)** — slugs are per-circle, NOT global.
  belongsTo Circle + creator (User); hasMany discussions.
- **forum_discussions** (`ForumDiscussion`): `forum_group_id` (FK cascade),
  `created_by` (nullable FK), `title`, `content`, `slug`, `is_pinned`,
  `is_locked`, `status`, `moderation_status`, `moderation_reason`, soft deletes,
  FULLTEXT(title, content) **MySQL-only** (guarded — sqlite tests skip it). No UI
  reads/writes it yet; it exists so the discussion-count stats are real now.
- **Enums** (`app/Enums/Forums/`, plain backed like CircleStatus):
  `ForumGroupVisibility` (public/private/internal), `ForumGroupStatus`
  (active/deactivated/archived), `ForumDiscussionStatus` (active/deactivated),
  `ForumDiscussionModerationStatus` (pending/approved/rejected). Cast on the models.

### Visibility & participation (the real Internal semantics)
- `ForumGroupVisibility`: **Public** (anyone views; readonly for visitors),
  **Private** (members only, no visitors), **Internal** (members with ANY
  approved internal_role — never hardcode 'organisation_member'; check via
  `CircleMembership::hasApprovedInternalRole()`).
- `ForumGroupVisibility::participationFloor()` — the SINGLE definition of the
  view→participate relationship: Public→Private, Private→Private,
  Internal→Internal (anyone views a public group; only members participate).
- `ForumGroup::canView(?membership, isVisitor)` and
  `canParticipate(?membership, isVisitor)` resolve against visibility (and
  participationFloor for participate). A visitor only ever satisfies Public
  viewing, never participation.
- The overview list + stats are filtered by `canView` (managers bypass — they
  see all to manage). Participation gating (canParticipate) is wired for the
  future Discussions UI. (The old interim "invite-only = members-only" rule is
  gone — this is the permanent rule.)

### Service & UI
All forum Livewire components live under
`App\Livewire\Communities\Services\Forums\` (aliases `communities.services.
forums.*`) with views under `resources/views/livewire/communities/services/
forums/` — the per-service grouping convention (each service keeps its files
together). `ForumService` (the handler) stays under `App\Services\Circles\`.
- **ForumService** (the `CircleServiceContract` handler) holds the writes:
  `createGroup`/`updateGroup` (accept an optional explicit `slug`, else derived
  from name), `deactivateGroup`, `slugFor`, `slugExists`/`slugTaken`.
- **ForumServiceContainer** (the Forums tab): stats (Total Groups, Participants
  [hardcoded 0 — later], real Total Discussions — all scoped to `viewableGroups`),
  search + status filter (default = active only), group grid. Create/Manage/
  ⋯-menu gated by `$this->canManage`. Reads via computeds; writes via ForumService.
- **ForumGroupModal** (wire-elements `ModalComponent`): sectioned create/edit
  form (p-10) — Basic Information (name, editable URL slug, description),
  Visibility & Access (radio group + a live read-only "Group Access" note
  derived from `participationNote`/participationFloor, never submitted), Group
  Images (placeholder), and the Tags picker (edit only). Friendly slug-collision
  error; submit button "Save Group". Manage-gated in BOTH `mount()` (403) and
  `save()`. Opened via a **Blade** `wire:click="$dispatch('openModal', {…})"`
  (the app-wide wire-elements pattern
  — a PHP `$this->dispatch` from the nested container does NOT reach the modal
  host under this Livewire 4 + wire-elements 3.0.4 setup). The community page hosts
  `<livewire:wire-elements-modal />`.
- **Discussions page**: `GET /communities/{circle}/forums/{forumGroup:slug}`
  (route `communities.forums.show`, **`->scopeBindings()`** so the slug resolves
  within the circle), `ForumGroupPage` — placeholder body this pass, but real
  route + circle-scoped binding + stateless `?from=` back-link.

### Authorization (reused, not re-stubbed)
`Circle::isManageableBy(?User): bool` = admin/superadmin OR circle_admin of THIS
circle — composes the existing `Circle::administeredBy()` primitive (the same
one RequestResource uses). Both consumers rest on `administeredBy`; no parallel
mechanism. (RequestResource keeps its own subtree-based inline composition.)

### Forum Discussions (list / detail / create / responses) — BUILT
- **`forum_discussions` table + `ForumDiscussion` model** (soft deletes):
  `forum_group_id` (FK cascade), `created_by` (nullable, nullOnDelete), `title`,
  `content` (text), `slug` (nullable), `is_pinned`/`is_locked` (bool), `status`
  (`ForumDiscussionStatus`: active/deactivated), `moderation_status`
  (`ForumDiscussionModerationStatus`: pending/approved/rejected, default
  approved), `moderation_reason`. **FULLTEXT(title, content)** — MySQL-only
  (guarded). Relations `group()`, `creator()`; `HasTags` applied.
- **Participants (contribution-derived):** a discussion's participant count is
  the count of UNIQUE users who contributed — its `created_by` creator ∪
  everyone who posted a comment, each counted once (`ForumDiscussion::
  participantCount()`). `participantCountsFor(iterable $discussions)` is the
  shared engine: per-discussion counts resolved in ONE comments query (no N+1),
  used by the group + container aggregates. `ForumGroup::participantCount()` =
  the SUM of its discussions' counts (a user active in two of a group's
  discussions counts in each). The Forums tab shows a per-group count and a
  summed `totalParticipants` across viewable groups.
  - **The old explicit Join/Leave subscription was RETIRED** (migration
    `2026_07_21_000004` drops `forum_discussion_participants`; the
    `ForumDiscussionParticipant` model and `join()`/`leave()`/`isJoinedBy()`/
    `participants()`/`activeParticipants()` are gone). Participation is now
    purely contribution-derived — there is no follow/subscribe. (The original
    create migration `2026_07_19_000001` is kept for fresh-migrate history; the
    000004 drop follows it.)
- **Create gating:** `ForumGroup::canCreateDiscussion(?User)` = group creator OR
  circle manager (`isManageableBy`). Gates the "+ Create Discussion" button AND
  `ForumDiscussionModal` (`mount()` + `save()`, 403). Writes via
  `ForumService::createDiscussion()` / `discussionSlugExists()` (slug explicit
  or title-derived, unique per group, friendly collision error).
- **Pages:** `ForumGroupPage` now lists discussions (pinned first, then
  recency; author + date; read-only pinned/locked badges) with a gated create
  button (Blade `$dispatch('openModal', …)` + a modal host). `ForumDiscussionPage`
  (`GET /communities/{circle}/forums/{forumGroup:slug}/{forumDiscussion:slug}`,
  route `communities.forums.discussions.show`, **scopeBindings** — needs
  `ForumGroup::forumDiscussions()`; `discussions()` kept for withCount) — shows
  the "first post" (content, author, timestamp) + the response thread (see
  Responses UI below), with a contribution-derived participant count top-right.
  Both pages **abort 404 unless the viewer canView the group** (managers bypass)
  — closes the direct-URL visibility hole.
- **First-post editing:** the discussion's AUTHOR (only — not managers) may edit
  the content in place on the detail page (`canEditContentBy()`; inline
  textarea via `startEditingContent`/`saveContent` → `ForumService::
  updateDiscussionContent()`). Editing stamps `forum_discussions.content_edited_at`
  (dedicated column, NOT touched by future pin/lock/moderation) → `isEdited()`
  renders an italic "(Edited)" next to the author's name.
- **Responses UI (comments) — BUILT:** the detail page renders the comment
  thread via `$discussion->posts` (the forum-facing alias for the generic
  `comments` relation). Roots pinned-first (by `pinned_position`) then by
  `created_at`; replies nested recursively (recursive Blade partial
  `partials/comment.blade.php`), indentation capped at level 3 with a "replying
  to {author}" label once flattened; `hidden` comments filtered. Inline reply
  composer (one open at a time) + a bottom root composer; per-comment like
  toggle with count. Compose/reply/like are gated on `ForumGroup::
  canParticipate()` (re-checked server-side) — view-only visitors see the
  thread but no composer. Posting a comment refreshes the participant count.
- **Deferred (later phases):** pin/lock toggle UI, moderation UI (the `hidden`/
  `flagged_as_offensive`/`moderated` columns exist, unused), edit/delete
  comments, real-time push, search.

---

## Tagging (Theme-based) & tag suggestions

A lightweight descriptive tagging layer over the existing `themes` vocabulary —
**unrelated to ThemeCommunity** (the Circle-instantiation use of Theme).

- **taggables** polymorphic pivot (`theme_id` + `taggable_type`/`taggable_id`,
  unique per triple). **HasTags trait** (`app/Models/Concerns/HasTags.php`):
  `tags()` morphToMany(Theme, 'taggable', 'taggables') — applied to **Circle,
  ForumGroup, ForumDiscussion ONLY** (Organisation is NOT taggable — tag its
  OrganisationCommunity Circle). Inverses on `Theme`: `circles()`,
  `forumGroups()`, `forumDiscussions()` (morphedByMany). ⚠️ These are DISTINCT
  from `Theme::themeCommunities()` (the theme_id-FK Circle-instantiation
  relation — added this pass; the belongsTo half already existed).
- **Tagging authorization** — uniform `canBeTaggedBy(?User)` on each taggable
  (reuses existing checks, no new mechanism):
  - Circle → `isManageableBy()` (circle_admin of it / admin / superadmin).
  - ForumGroup → owning circle's `isManageableBy()`.
  - ForumDiscussion → the discussion's author (created_by) OR owning group's
    circle `isManageableBy()`.
- **theme_suggestions** + `ThemeSuggestion` model (status enum
  `App\Enums\ThemeSuggestionStatus`): a user's proposed tag. `approve(reviewer,
  ?note)` → `Theme::firstOrCreate` by slug (dedupe, not error), mark reviewed,
  auto-attach to the origin entity if one was recorded, email
  `theme_suggestion_approved`. `reject(reviewer, note)` (note required) → email
  `theme_suggestion_rejected`. Emails are best-effort (never roll back review).
- **TagPicker** (`app/Livewire/Tags/TagPicker.php`) — reusable edit surface:
  attach/detach gated by `canBeTaggedBy`; "Suggest one" form open to ANY
  authenticated user (creates a pending suggestion with origin set, attaches
  nothing). It's the editing surface reached via "Edit tags" (managers only).
- **Display** — `<x-tag-list :tags>` (`resources/views/components/tag-list.blade.php`):
  plain understated bordered pills, alphabetical, no icons/colour; renders
  nothing when empty. Shown under the description on the **community page**
  (Circle tags) and each **ForumGroup card** (group tags). Managers additionally
  see an **"Edit tags"** affordance → the community page reveals an inline
  TagPicker (Alpine toggle); the forum card opens the group's edit modal (which
  hosts the picker). Non-managers see the read-only row only. ForumDiscussion
  has no display surface yet (no discussion page) — relation ready, unused.
- **Filament** `ThemeSuggestionResource` (Platform group, admin/superadmin) —
  list + Approve / Reject (Reject requires a note) row actions.
- **Auto-tag on creation:** `Circle::booted()`'s created hook auto-tags a new
  ThemeCommunity circle with its own theme (when `theme_id` is set) — so new
  theme communities are tagged automatically. `circles:backfill-theme-tags`
  covers legacy circles (idempotent, adds-only, manual, NOT scheduled).

---

## Internationalisation

### Key decisions
- PHP array files under `lang/en/` organised by feature area
- Keys: stable snake_case strings (NOT English sentences)
- `lang/pt/` — shared Portuguese base
- `lang/pt_BR/` — Brazilian Portuguese overrides only
- Fallback chain: pt_BR → pt → en → key itself (visible = bug)

### What IS translated
- All UI strings: labels, buttons, headings, empty states, modals
- Community type names: Organisation, Campaign, Course, Theme, Event
- Validation messages

### What is NOT translated
- Circle names (proper nouns)
- Place/location names (proper nouns: "Western Cape", "Cape Town")
- User-generated content

### circles.description
- JSON column via spatie/laravel-translatable
- Stored as: `{"en": "...", "pt": "..."}`
- Access as plain string: `$circle->description` (auto-resolves locale)
- NEVER treat as a plain text column

### SetLocaleFromBrowser middleware
Priority: saved user preference → session locale → Accept-Language
header → app default.
Sets App::setLocale() AND Carbon::setLocale().
Registered on web middleware group only.

### Language switcher
`LocaleController` (invokable) on `GET /locale/{locale}` (route
`locale.update`) stores a supported locale in `session('locale')` and redirects
back; the middleware applies it next request. Unsupported values ignored. UI:
per-locale links in the main nav (`layouts/main.blade.php`) AND the Filament
admin top-bar (`resources/views/filament/top-bar.blade.php`), highlighting the
active locale. Shown to guests too (Explore is public). No `users.locale`
column yet, so preference is session-scoped.

### Lang file structure
```
lang/en/
  explore.php      — Explore page UI
  communities.php  — community types, cards, modals
  navigation.php   — nav, page titles
  validation.php   — validation messages
  ui.php           — generic: Save, Cancel, Close, Back, View, etc.
```
All keys in lang/en/ must exist before being referenced in views.

---

## Explore Page (/explore)

Always public — no auth middleware. Ever.

### Layout: two vertical sections

**TOP SECTION** — two columns (50/50):
- Left: geographic location browser
    - Header + "Could this be your community?" button (geolocation)
    - Type filter: [All] [Locations] only
    - Breadcrumb + Map/Browse toggle
    - Column browser (max-height: MAX_HEIGHT_LOCATIONS_COLUMN, overflow-y-auto)
- Right: LocationCommunity card for selected location
  (placeholder if nothing selected)

**BOTTOM SECTION:**
- Type tabs: [Organisations] [Campaigns] [Courses] [Themes] [Events]
- Card grid filtered by current geographic selection
- Add Community button (stub, TODO auth guard)

### URL state sync (#[Url] on ExploreCommunities)
- selectedCircleId
- selectedType (top: All/Locations)
- selectedBottomType (bottom: Org/Campaign/Course/Theme/Event)
- viewMode (browse/map)

### Critical interaction rules
1. Switching type NEVER resets geographic selection
2. Clicking breadcrumb preserves type, trims geographic trail
3. "South Africa" crumb always present, always clickable
4. "Also here" badge: column browser ONLY (not on cards)
5. isTerminal() check drives no-further-levels message only
6. Bottom section always filtered by top section's geography

### Column browser terminal behaviour
At MainPlace level (isTerminal() = true):
- Next column: x-explore.no-further-levels ("No further sub-areas")
  NOT x-explore.empty-state
- Bottom of list: "Your location not listed?" button
  → RequestLocationModal(parentLocationName, parentCircleId)
  TODO: auth guard

### Geolocation button
"Could this be your community?" — only shown when full chain resolves:
getUserLocation() → CoordinateData::nearest() → getMainPlace()
→ explorerLocationCommunityUrl() → $suggestedCommunityUrl (non-null)
On failure: silent. No loading state.
MainPlace::explorerLocationCommunityUrl(): string|null
Returns /explore?circle={id} or null.

### Add Community modal (per type)
Bottom section — both empty and non-empty states.
Button label uses correct a/an per type (hardcoded):
- "Add an Organisation Community"
- "Add a Campaign"
- "Add a Course Community"
- "Add a Theme Community"
- "Add an Event"
  Modal body: a collapsible how-to content block per type, resolved by
  `AddCommunityModal::howToKey()` (maps the CommunityType enum →
  `community.how_to_add.*`, language-independent — NOT the translated label);
  types without a block fall back to placeholder text. TODO: save logic + auth.

### Map view
Toggle visible, disabled. "Coming soon" tooltip. Deferred to Phase 2.

---

## Community Page (/communities/{circle})

Route: GET /communities/{circle} — route-model bound to Circle, name
`communities.show`. Public, but `mount()` calls `abort_unless($circle->
isVisibleTo($user), 404)` — pending circles are reachable only by
admin/superadmin (mirrors the Explore `visibleTo()` scope; single source of
truth is `Circle::visibleStatusesFor()`).
Component: CommunityPage (`app/Livewire/Communities/CommunityPage.php`)
View: `resources/views/livewire/communities/community-page.blade.php`
Layout: layouts/main.blade.php (with nav)

### Back link (stateless)
"View →" on CommunityCard generates:
/communities/{circle}?from={urlencoded current explore URL}
CommunityPage reads ?from= for back link. Falls back to /explore.

### Content
Name + type icon, geographic breadcrumb, **circle administrators** (see
below), member count (👥; admins count as members), description, **service
tabs**, Join button (stub, right-aligned).

For **organisation communities** the top row splits into two halves: left =
location/admins/members + the org contact (contact/email/website); right =
"Organisation members" — the APPROVED `organisation_member`s
(`CommunityPage::organisationMembers()`, filtered by `hasApprovedInternalRole`),
in an `overflow-y` list. Non-org communities keep the single (unsplit) top row.

**Service tabs** (replaced the old service icon-badges — badges are gone):
every attached service with a non-null `container_component` renders as a tab,
ordered per `defaultServices()` when the circleable implements
`HasDefaultServices`, else attachment order. First tab active by default; switch
via `selectService($key)`. The active tab syncs to the URL via
`#[Url(as: 'service')]` on `activeServiceKey`, so `?service=<key>` deep-links /
back-links preselect a tab (e.g. a forum group's Discussions back-link points at
`/communities/{id}?service=forums`). The active tab's container renders through
Livewire 4's `<livewire:dynamic-component :component="$this->activeContainer"
:circle=… :key=…/>`. The community-TYPE icon next to the name is unrelated and
unchanged.

### Circle administrators (shown on this page)
- `Circle::administrators(): Collection<User>` — users holding the
  `circle_admin` role scoped to THIS circle. Queries the `model_has_roles`
  pivot directly on `circle_id` (Spatie teams mode), NOT via `roles()` (which
  is scoped to the *current* permissions team). A circle can have zero or many.
- Exposed on the page via a `#[Computed] administrators()` method so the query
  runs once per render; rendered as a comma-joined name list (or a
  `communities.page.no_admins` string when empty).
- `Circle::administeredBy(?User): Collection<Circle>` — the INVERSE: every
  circle a user holds `circle_admin` on (same direct `model_has_roles` query;
  the pattern for "does this user hold a team-scoped role on ANY team", since
  `hasRole()` is scoped to the current team). Drives Filament Governance access.
- `Circle::responsibleAdminFor(Circle): ?User` — escalation/notification
  resolver. Call it on **the circle the request concerns** (e.g.
  `$request->circle` — for an org approval, the pending organisation's own
  circle). Walks the circle + its ancestors nearest→root, returns the first
  `circle_admin` of the nearest **LocationCommunity** that has one; falls back
  to the first global `admin`, then `superadmin`. Null only if none exist.
  - **Climb rule (intentional):** only `circle_admin`s on **LocationCommunity**
    circles count on the way up — non-location circles (the org circle itself,
    theme circles, etc.) are skipped. The intent is "route to the geographic
    steward for that area," NOT "any circle_admin above." Do not broaden this
    to all circle types without an explicit decision.
  - **Wired into requests:** `Request::createForOrganisation()` stores the
    result in `requests.responsible_admin_id` (nullable FK → users) at
    creation. On submission, `AddCommunityModal` emails that admin the
    `email.organisation_approval_admin_notice` template (link to the Filament
    request view; no-op + logged when null). Surfaced in the Governance
    RequestResource (view field, table column, "Assigned to me" filter) —
    notification/discovery only; it NEVER gates who can act (see below).

---

## Filament Admin Panel (/admin)

AdminPanelProvider (`app/Providers/Filament/AdminPanelProvider.php`).
- Path `/admin`, panel id `admin`, `->login()`, dark mode on, primary = Amber
- `User::canAccessPanel()` admits `admin` + `superadmin` (global roles) AND any
  `circle_admin` (via `Circle::administeredBy($this)->isNotEmpty()` — a
  team-scoped role checked across all teams, not `hasRole`)
- **Because the panel is now reachable by circle_admins, every resource gates
  itself explicitly** (they were previously protected only by nobody else
  reaching `/admin`):
  - `ContentBlockResource`, `EmailTemplateResource`: `canViewAny()` →
    `admin`/`superadmin` only (canAccess() defaults to canViewAny(), covering
    nav + all pages)
  - `Dashboard` (`app/Filament/Pages/Dashboard.php`, subclass registered in the
    panel): it's the panel HOME (`/admin`), so `canAccess()` stays `true` —
    denying it would 403 the home route, not redirect. Instead
    `shouldRegisterNavigation()` hides it from circle_admins and `mount()`
    redirects them to the Requests index (admins see it normally)
  - `RequestResource`: visible to admins AND circle_admins, but role-scoped —
    see Governance admin below
- Nav group `Platform` registered for platform-management resources
- Auto-discovers Resources/Pages/Widgets under `app/Filament/`

### Content Blocks (admin-editable copy)

Small pieces of locale-aware copy rendered into public views
(banners, hints, instructions) — editable in the admin panel.

**content_blocks table** (base + `2026_07_07` add-collapsible migration)
- `key` (string, unique) — stable lookup handle used in views
- `description` (string) — admin-facing note
- `content` (JSON, translatable via spatie/laravel-translatable) —
  `{"en": "...", "pt_BR": "..."}`
- `title` (JSON, translatable, nullable) — heading for collapsible blocks
- `is_html` (bool, default true) — rich HTML vs plain text
- `collapsible` (bool, default false) — render as expand/collapse disclosure
- `default_collapsed` (bool, default true) — initial state when collapsible

**ContentBlock model (`app/Models/ContentBlock.php`)**
- `$translatable = ['content', 'title']`; `is_html`/`collapsible`/
  `default_collapsed` cast to boolean
- `ContentBlock::get(string $key, string $fallback = ''): string`
  - Cached 1h per key+locale
  - Resolution: current locale → `app.fallback_locale` (en) → `$fallback`
  - Markup/whitespace-only content (e.g. `<p></p>`) treated as blank
- Cache auto-flushed on saved/deleted (`booted()` hooks), per supported locale

**ContentBlockResource** (`app/Filament/Resources/ContentBlocks/`)
- Under `Platform` nav group
- `key` disabled on edit (stable handle)
- Toggles: `is_html`, `collapsible` (live), `default_collapsed` (hidden
  unless `collapsible`)
- Per-locale tabs (from `config('app.supported_locales')`): `title` TextInput
  (visible only when `collapsible`) + content RichEditor (`is_html`) / Textarea
- Table: per-locale content checkmark + a `collapsible` boolean icon column
- `EditContentBlock` hydrates full `content` AND `title` translations on fill

**ContentBlockSeeder** — registered in DatabaseSeeder, idempotent
(`updateOrCreate` by key). Seeds English only; pt_BR left blank (falls
back to English). Keys: `explore.welcome_banner`,
`explore.column_browser_hint`, `community.join_instructions`,
`onboarding.new_user_welcome`, plus 4 collapsible how-to blocks
`community.how_to_add.{campaign,course,event,theme}` (title "How this works",
placeholder content). NOTE: `community.how_to_add.organisation` exists in the
dev DB but is NOT in the seeder yet.

**x-content-block Blade component**
`<x-content-block key="explore.welcome_banner" fallback="…" />`
- Props: `key`, `fallback`, `collapsible`, `collapsed`, `title` —
  collapsible/collapsed/title default to the block's stored values; a non-null
  inline value overrides
- Non-collapsible: renders `ContentBlock::get()` directly (`{!! !!}` when
  `is_html`, else escaped)
- Collapsible: Alpine disclosure — title left, +/- toggle right, body via
  `x-show` + `x-collapse` (Livewire's bundled Alpine). Initial state is
  server-rendered to avoid FOUC (project has no `x-cloak` CSS)
- Renders nothing when empty and the viewer cannot edit
- Inline edit pencil (top-right, on hover) for admin/superadmin only
- Used on the Explore page (`explore.welcome_banner`) and in the Add Community
  modals (collapsible how-to blocks — see below)

### Email Templates (DB-backed, locale-aware transactional email)

**email_templates table** (migration `2026_07_06_000001`)
- `key` (string 150, unique) — stable lookup handle used in code
- `description` (string 255, nullable) — admin hint
- `subject` (JSON, translatable) — `{"en": "...", "pt_BR": "..."}`
- `body` (JSON, translatable)
- `is_html` (bool, default true) — HTML vs plain-text rendering
- `available_variables` (JSON array, nullable) — variable whitelist,
  e.g. `["user_name", "action_url"]`; developer-set, NOT admin-edited
- `is_active` (bool, default true) — inactive templates cannot be sent

**EmailTemplate model (`app/Models/Communication/EmailTemplate.php`)**
- `$translatable = ['subject', 'body']`; casts `is_html`/`is_active` bool,
  `available_variables` array
- `EmailTemplate::getByKey(string $key): ?self` — cached 1h per key+locale;
  cache flushed on saved/deleted per supported locale (mirrors ContentBlock)

**EmailServiceHandler (`app/Services/Communication/EmailServiceHandler.php`)**
- Implements `CircleServiceContract`; `getKey()` = `'email'`
- `sendTemplate(key, toAddress, variables = [], ?Circle)` — synchronous
- `queueTemplate(key, toAddress, variables = [], ?Circle)` — queued
- Both delegate to private `buildMailable()`: resolves the template, throws
  `RuntimeException` if missing/inactive, substitutes `{{ variable_name }}`
  via `strtr()`, returns a `TemplateMailable`. `$circle` reserved for future use.

**TemplateMailable (`app/Mail/TemplateMailable.php`)**
- Constructor `(subject, body, isHtml)`; assigns `subject` to the inherited
  `Mailable::$subject` (do NOT promote it — typing the inherited untyped
  property is a fatal error)
- HTML → `resources/views/mail/template.blade.php`
- Plain → `resources/views/mail/template-plain.blade.php`
- Minimal inline-styled views, no external CSS

**EmailTemplateResource** (`app/Filament/Resources/EmailTemplates/`)
- Under a `Communication` nav group (separate from `Platform`)
- `key` disabled on edit; `available_variables` shown as read-only chips
  (disabled TagsInput, `dehydrated(false)`)
- Per-locale tabs (from `config('app.supported_locales')`): subject TextInput
  + body RichEditor (when `is_html`) / Textarea (plain)
- Table: key, description, per-locale "Complete/Missing" badge,
  `is_active` ToggleColumn, updated_at

**EmailTemplateSeeder** — registered in DatabaseSeeder, idempotent
(`updateOrCreate` by key). English stubs, empty pt_BR (falls back). 12 keys:
`email.welcome`, `email.circle_invitation`, `email.password_reset`,
`email.organisation_approval_request`, `email.organisation_approval_confirmed`,
`email.organisation_approval_denied`, `email.organisation_approval_admin_notice`,
`email.organisation_member_claim_request`, `email.organisation_member_claim_approved`,
`email.organisation_member_claim_rejected`, `email.theme_suggestion_approved`,
`email.theme_suggestion_rejected`.

Local mail: MailHog via MAMP — SMTP `localhost:1025`, UI at
`http://localhost:8025/mailhog` (note the `/mailhog` web path).

---

## Organisation Approval & Requests

External-approval workflow: a logged-in user submits a new Organisation
Community; it stays PENDING until the organisation's contact approves it via
an emailed link. Only `organisation_approval` is implemented end-to-end.

### requests table + Request model (`app/Models/Communication/Request.php`)
Generic request record: `type`, `status` (default pending), `direction`
(external|internal), `requester_id`, `circle_id`, polymorphic `requestable`,
`respondent_email`, `respondent_user_id`, `responsible_admin_id` (FK users,
nullable — see Circle administrators), `token` (unique) + `token_expires_at`,
`responded_at`, `response_note`, `metadata` (JSON), `ulid` (public id), soft deletes.
- `booted()` auto-generates `ulid` (`Str::ulid`) + `token` (`Str::random(64)`)
- Scopes: `pending()`, `expired()`, `external()`, `internal()`
- `createForOrganisation(requester, circle, organisation, respondentEmail, metadata=[])`
  — 7-day token, metadata seeded with an empty `email_log`
- `logEmail(template, recipient, status, error?)` — appends to
  `metadata.email_log` (audit of every send attempt)
- `isExpired()` — `token_expires_at` in the past
- The model is `App\Models\Communication\Request` — alias it
  (`as RequestModel`) wherever `Illuminate\Http\Request` is also used
  (e.g. RequestController)

### Submission (Explore → AddCommunityModal)
- Auth-guarded org form (name, website, description, contact name/email/job
  title) + duplicate check (`whereHas('community')`)
- `submitOrganisation()`: create Organisation + a **Pending** circle (via
  CircleCreationService) + `Request::createForOrganisation()`, then email the
  contact (outside the txn, logged)
- `circleId` (parent = geographic selection) passed from BOTH the Add bar and
  the empty-state dispatches
- `organisations.contact_job_title` column added (migration `2026_07_07_000004`)

### Public approval pages (no auth, token-based)
- `RequestController` show/approve/deny (`app/Http/Controllers/RequestController.php`)
- Routes (routes/web.php):
  - GET `/requests/confirm/{token}` → `requests.confirm`
  - POST `/requests/confirm/{token}/approve` → `requests.confirm.approve`
  - POST `/requests/confirm/{token}/deny` → `requests.confirm.deny`
- Views `resources/views/requests/{confirm,confirmed,denied,expired}.blade.php`
  on `layouts/public.blade.php` (nav-free, external-facing — created here)
- **approve**: txn → request approved + circle `Active` + requester granted
  Spatie `circle_admin` scoped to `circle_id`; then emails both parties
- **deny**: txn → request denied (+ optional note); circle stays pending
- invalid / expired / already-actioned / unknown token → expired view
- Approval emails link to the GET landing page (`requests.confirm`), never the
  POST approve/deny routes (email clicks are GET → 405)

### Email templates (EmailTemplateSeeder)
`email.organisation_approval_request` (single "Review this request" button →
`review_url`), `…_confirmed`, `…_denied`, and `…_admin_notice` (internal
heads-up to the responsible admin → `review_url` = Filament request view).

### Governance admin (Filament)
- `RequestResource` (`app/Filament/Resources/Requests/`) under a `Governance`
  nav group (auto-rendered; provider unchanged)
- List: type/status/direction badges + filters; View: read-only detail +
  email-log table
- Row actions (pending/expired only): **Approve**, **Deny** (optional note),
  **Resend** (regenerates token+expiry, resends request email). Each mirrors
  the controller, logs the email, shows a success/warning notification
- **Role-scoped visibility.** `getEloquentQuery()` is the single choke point
  (Filament resolves route records through it, so it scopes BOTH listing and
  record pages):
  - `admin`/`superadmin`: unscoped — see and act on ALL requests (the
    escalation net: if the responsible circle_admin doesn't act, they can).
  - `circle_admin` (non-privileged): only requests where
    `responsible_admin_id = them`, OR whose circle is one they administer or a
    **descendant** of it (`Circle::administeredBy` + `path LIKE`/`isNestedIn` —
    subtree, matching `responsibleAdminFor`'s upward walk). NOT ancestors.
- **Action visibility** (Approve/Deny/Resend) = request status AND
  `userMayActOn($record)`: privileged act on any; circle_admins only within the
  same directed-or-subtree scope. So a circle_admin cannot act on a request
  outside their subtree even though admins can act on any pending request.

### Expiry
- `requests:expire` (`app/Console/Commands/ExpireRequests.php`) flips
  past-expiry pending requests to `expired` (`chunkById`, 100). Scheduled
  daily in `routes/console.php`.

---

## Authentication

Built manually — Livewire 4 components.
NO Breeze, NO Jetstream, EVER.

Components: Login, Register, ForgotPassword, ResetPassword
Controller: LogoutController
Layouts: guest.blade.php, authenticated.blade.php
main.blade.php: public pages with nav — used by Explore + CommunityPage
public.blade.php: nav-free layout — used ONLY by the external request
approval pages (resources/views/requests/*)

---

## Spatie Permissions

Teams mode enabled. team_foreign_key = circle_id.
circle_id is NULLABLE on pivot tables — intentional, allows global roles.

Seeded roles: new_user, full_member, curator, trainer, admin,
superadmin, circle_admin, circle_full_member, circle_visitor

---

## Seeders (already run — do not re-run blindly)

- LocationCommunitiesSeeder — country → LM/City circles
- MainPlaceCommunitiesSeeder — ~14,039 MainPlace circles (idempotent)
- ThemeCommunitiesSeeder — national + WC + Eden DM
- ContentBlockSeeder — 8 content blocks (4 page-copy + 4 collapsible how-to;
  idempotent, updateOrCreate by key)
- EmailTemplateSeeder — 12 email templates (welcome/invitation/reset + 4
  organisation-approval incl. the responsible-admin notice + 3
  organisation-member-claim [request/approved/rejected] + 2 theme-suggestion
  [approved/rejected]; idempotent, updateOrCreate by key)
- Full SA demography (provinces, DMs, LMs, cities, main places)

MainPlaceCommunitiesSeeder is idempotent — checks before creating.
Always use chunk()/lazy() for large demography queries.

---

## JavaScript

### resources/js/utils/geolocation.js
Exports: getUserLocation(): Promise<{latitude, longitude}>
Rejection codes: 'denied' | 'unavailable' | 'timeout'
Defaults: enableHighAccuracy:false, timeout:10000, maximumAge:300000

Import in app.js. Call from Alpine x-init on ExploreCommunities.
On success: $wire.setUserLocation(lat, lng)
On failure: silent.

---

## What Is NOT Yet Built

- Auth/permission guards on buttons (TODO comments in place)
- Campaign model fields
- Filament resources beyond ContentBlock + EmailTemplate + Request
- Request types other than organisation_approval — circle_join,
  location_request, circle_association are reserved type strings only
- Membership approval (circle_join) + internal-direction request flows
- Role transition after organisation approval: the requester is granted
  circle_admin on approval (intended, even for platform admins) — switching
  that to a dedicated organisation-staff role during onboarding is future work
- Wiring EmailServiceHandler into other flows (registration welcome, circle
  invitations, password reset) — templates exist but aren't triggered by
  app events yet (the organisation-approval flow IS fully wired)
- Map view (SVG sourcing in progress)
- User profile pages + saved locale preference
- CommunityPage type-specific nested components
- Notification, voting, social media, learning service implementations
- Payment/subscription system
- API endpoints
- In-app notification templates (email templates are built — see
  Email Templates section)

---

## Testing

- PHPUnit (not Pest); namespaced test classes under `tests/`
- `tests/Services` has its own `Services` testsuite in `phpunit.xml`
- Test DB is sqlite `:memory:` with `MAIL_MAILER=array` (phpunit.xml)
- NEVER use `RefreshDatabase` — the full migration set fails on sqlite
  (a demography backfill references a `countries` table that no migration
  creates). Build only the tables a test needs by running their specific
  migrations' `up()` in `setUp()`
- Tests never hit MailHog: `array` mailer + `Mail::fake()`

---

## Common Mistakes to Avoid

- Convention: "create new entity" buttons are prefixed "+ " (e.g. "+ Create
  Group"); the in-modal submit is a plain "Save …"
- Convention: any "Edit" affordance shows the shared pencil icon
  `<x-icons.edit class="h-3.5 w-3.5" />` (or h-4 w-4) before the label
- Using Livewire 3 syntax (wire:model.defer, etc.) — this is Livewire 4
- Blade component props: declare them with `/** @var … */` hints (in a
  comment-free `@php` block) so the IDE doesn't flag them as undefined
  variables — and NEVER put `//` comments inside `@props([...])`, it breaks
  the IDE's prop parsing (see resources/views/components/content-block.blade.php)
- Alpine handlers in Blade: use `x-on:click` (not `@click`) so neither the
  IDE nor Blade mistakes it for a directive
- Passing `auth()->user()` where an `App\Models\User` is required — it's typed
  `Authenticatable|null`, so the IDE flags a mismatch. Capture it into a
  `/** @var \App\Models\User $user */` variable with a null-guard, then pass
  `$user`. NEVER drop the `()` — `auth()->user` is a non-existent property (null)
- Adding tailwind.config.js — never
- Modifying app.blade.php — never
- Treating circles.description as a plain string — it is JSON
- Treating ContentBlock.content as a plain string — it is translatable
  JSON; always read via ContentBlock::get()
- Branching on a TRANSLATED string (e.g. __('...add_label...')) — breaks in
  non-English locales; key off a stable identifier (CommunityType enum,
  lang array key) instead. See AddCommunityModal::howToKey()
- Treating email_templates.subject/body as plain strings — translatable
  JSON; send via EmailServiceHandler (never build/send mail ad hoc)
- Promoting $subject in TemplateMailable — fatal (inherited untyped
  Mailable::$subject); assign it in the constructor body instead
- Using RefreshDatabase in tests — migrations fail on sqlite (see Testing)
- Forgetting the Request name clash — the Eloquent model is
  App\Models\Communication\Request; alias it (…\Communication\Request as
  RequestModel) in files that also import Illuminate\Http\Request
- Linking approval emails to the POST approve/deny routes — email clicks are
  GET (405); link to route('requests.confirm', $token) (the landing page)
- Assuming a new circle is Pending — CircleCreationService creates it Active;
  set CircleStatus::Pending explicitly for approval-gated circles
- Marking City as terminal — only Place (MainPlace) is terminal
- Using isTerminal() to decide whether to render a next column —
  use $children->isNotEmpty() for that
- Creating duplicate lang keys or using keys not in lang/en/
- Forgetting TODO auth guard comments on new buttons/actions
- Running LocationCommunitiesSeeder again (use MainPlaceCommunitiesSeeder
  for MainPlace level — it is idempotent)
- Using SQRT in CoordinateData::nearest() — squared distance is enough
