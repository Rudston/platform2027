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
  email_templates.subject/body, services.name)
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
- **Two-way parent/locatable derivation** — a caller may supply either and the
  missing side is inferred, so a new circle is never left dangling:
  - no `locatableType` + a parent → inherit the parent's locatable (a child sits
    at its parent's location unless placed elsewhere);
  - no `parentCircle` → anchor under the LocationCommunity circle for the
    resolved locatable, via **`locationCircleFor()`** (the ONE place that rule
    lives — `circles:backfill-root-parents` reuses it).
  - **LocationCommunity is excluded from parent derivation**: the country circle
    IS the root and must stay parentless (LocationCommunitiesSeeder creates it
    through this same method with no parent), and nested location circles always
    pass an explicit parent. `parent_id IS NULL` therefore means "the root
    country circle" and nothing else — keep that invariant.
  - Why it matters: **Explore represents the national level as `selectedCircleId
    = null`** (breadcrumb `['id' => null, 'name' => 'South Africa']`), NOT as
    circle 7, and all three add-community dispatch sites pass that value
    straight through. Without the derivation an organisation added at country
    level became a SECOND ROOT (locatable Country #191 but parent_id NULL,
    depth 0, path "<own id>") — no ancestors, so no geographic breadcrumb,
    `responsibleAdminFor()` skipping the LocationCommunity climb, and invisible
    to every `path LIKE` subtree query. It still showed in Explore because the
    bottom section filters on locatable, not parent — which is why it went
    unnoticed.
- **`circles:backfill-root-parents`** (`app/Console/Commands/BackfillRootParents.php`)
  — re-anchors legacy parentless non-location circles under their location
  circle, recomputing `depth`/`path` and shifting any descendants' paths
  (`Circle::booted()` maintains depth/path on CREATE only, so an update must set
  them explicitly). `--dry-run` reports without writing. Idempotent (a fixed
  circle no longer matches), manual, NOT scheduled.

### CircleMembershipService
Membership management (partially built).

### Circle services (as Livewire UI containers)
Services are rows in the `services` table (`key`, `handler_class`,
`container_component`) with a `CircleServiceContract` handler under
`App\Services\Circles\`. Registered/seeded by `ServicesSeeder` (9 services;
`container_component` is read off each handler, the single source of truth).

**`services.name` is TRANSLATABLE** (spatie/laravel-translatable; the `Service`
model declares `$translatable = ['name']`), so it stores
`{"en": "...", "pt_BR": "..."}` — not a plain string. Assigning a plain string
through the model sets the CURRENT locale's value and leaves the others intact,
which is why `ServicesSeeder` can pass `'name' => 'Polls'` safely. Writing it
with the QUERY BUILDER would overwrite the whole JSON and destroy every other
translation. `key` is the stable handle and never changes; the label may.
(Renaming a label therefore leaves the other locales stale until updated —
`voting` is `{"en": "Polls", "pt_BR": "Votações"}` today.)

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

**Location communities are capped per GEOGRAPHIC BUCKET, not type-wide.**
`HasGeographicMembershipBuckets` (`app/Contracts/Communities/`):
`maxConcurrentTerminalMemberships()` + `maxConcurrentUpperMemberships()` —
implemented ONLY by `LocationCommunity` (2 and 2). A user may hold 2 memberships
at the terminal ("lowest") level AND 2 above it; the buckets never consume each
other's allowance, and the `minMembershipMonthsBeforeSwitch` swap applies WITHIN
a bucket (an aged provincial membership can't be dropped to free a main-place
slot). `LocationCommunity::maxConcurrentMemberships()` returns the SUM (4) as an
informational total — `canUserJoin()` applies the bucket caps, so it is never the
operative limit for a join.

**"Lowest level" is `LocationLevel::Place`, NEVER `circles.depth`.** The bucket
comes from the circle's `locatable_type` via **`Circle::isAtTerminalLocationLevel()`**
(→ `LocatableType::isTerminal()`), with **`LocatableType::terminalValues()`** as
the query-side list for `whereIn('locatable_type', …)`. Depth is NOT usable: the
City branch is one level shorter than the DistrictMunicipality one, so SA main
places sit at depth 3 (649 of them, the metro places) AND depth 4 (13,390) —
a `depth = 4` rule would silently mis-bucket every metro place. It is also
country-agnostic for free: a new country's bottom tier maps onto
`LocationLevel::Place`, so there is nothing to configure per country (do NOT add
a config entry for it; if a country ever bottoms out at a non-Place level, put
`lowestLocationLevel()` on the `Country` model, next to the data). A circle with
NO locatable counts as UPPER (it is not a lowest-level place).

### Circle methods (domain logic lives on Circle, like administrators())
- `memberships()` hasMany; `activeMembership(User): ?CircleMembership`.
- `canUserJoin(User): array{allowed, reason, available_at, swappable}` —
  global `admin`/`superadmin` bypass (NOT circle_admin); else count the user's
  ACTIVE memberships of the SAME circleable type — and, for a
  `HasGeographicMembershipBuckets` type, only those in THIS circle's bucket
  (terminal vs upper, one `whereIn`/`whereNotIn` on `locatable_type`, no
  PHP-side filtering): under the applicable `max` → allowed; at cap →
  memberships older than `minMonths` are `swappable` (allowed if any), else not
  allowed with `available_at` = earliest eligible date. Because the count is
  bucket-scoped, `swappable` is too.
- `isAtTerminalLocationLevel(): bool` — is this circle at the lowest geographic
  level (`LocationLevel::Place`)? The ONE definition; `ExploreCommunities::
  isAtTerminalLevel()` delegates to it. Never keys off `depth`.
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

### Internal groups restrict the PLATFORM ADMIN role — `isAccessibleByPlatformAdmin`
A deliberate divergence from `isManageableBy` for **forum/comment content only**:
a global platform `admin` must NOT reach an **Internal**-visibility group's
content, while superadmin and that circle's own circle_admin still do.
`ForumGroup::isAccessibleByPlatformAdmin(?User): bool` is the SINGLE source of
truth — the three-way split:
- **superadmin** → always (any visibility);
- **this circle's own circle_admin** (`Circle::isAdministeredBy`) → always,
  Internal included;
- **global platform admin** (manages via the generic `admin` role, not by being
  this circle's circle_admin) → only when the group is **NOT** Internal.

Layer it ON TOP of `isManageableBy` at every forum-group/comment-scoped manager
gate; NEVER fold this into `isManageableBy` (that gate governs many non-forum,
circle-level actions — including forum-group CREATION, which stays circle-level).
It's implemented `isManageableBy → visibility → superadmin/circle_admin` so a
platform admin costs no extra query on non-Internal groups. Threaded through:
`ForumGroup::canCreateDiscussion`; `Comment::canModerate` (→ `canDelete`/`hide`,
via commentable→group); the `ForumGroupPage`/`ForumDiscussionPage` mount 404
view-gates; `ForumDiscussionPage::canManageThread`; `ForumServiceContainer`'s
group-list filter (guarded by the memoised `canManage` so non-managers pay no
per-group query) and `deactivate`; and `ForumGroupModal`'s **edit** branch.
NOTE: `ForumGroup::canView`/`canParticipate` are membership/participationFloor
only (no admin bypass), so they're UNCHANGED — a user who is a genuine member
with an approved internal_role still views an Internal group via `canView`,
independent of any admin role. The admin-bypass lived at the call sites above,
which is where the restriction is applied. (`canBeTaggedBy` is intentionally
left on plain `isManageableBy`: its only entry points sit behind the gated
view/edit surfaces, so an admin can't reach an Internal group's tag picker.)

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
- **Comment actions (edit / delete / flag / hide) — BUILT:** on each comment —
  **Edit** (author-only, `Comment::editBy`; stamps `edited_at` +
  `last_edited_by_user_id`, nulls `ai_checked_at` to requeue an AI recheck),
  **Delete** (`Comment::canDelete` = author OR circle manager; `deleteBy()` —
  hard delete when no replies, else tombstone `is_deleted` so replies keep a
  valid parent), **Hide** (`Comment::canModerate` = circle manager only;
  `hide()` sets `hidden` — the comment AND its replies drop out of the thread,
  no tombstone), **Flag** (any participant, others' comments only — sets
  `flagged_as_offensive` + opens a User-sourced moderation record; transient
  toast, no visible effect for anyone else). Tombstones render as `[deleted]`;
  authorization gates: `canDelete` layers authorship on the shared `canModerate`
  owning-circle check. `deleteBy`/`editBy` are the method names (Eloquent owns
  `delete()`); an admin edit uses `applyModeratorEdit()` (see Comment
  Moderation).
- **Near-live updates — Tier-0 polling:** `wire:poll.10s="refreshComments"` on
  the responses block (NOT the root) re-fetches the thread; the polled method
  no-ops while a reply/edit composer holds unsaved text so a tick never wipes
  in-progress typing. No websockets.
- **Pending-AI-review quarantine display — BUILT:** while an unresolved
  AI-sourced moderation record exists for a comment, it's quarantined three ways
  by viewer (author warning / moderator badge+link / everyone-else tombstone) —
  see Comment Moderation below.
- **Deferred (later phases):** pin/lock toggle UI, discussion-level
  moderation_status workflow, search.

---

## Comment Moderation & AI review

A unified moderation queue over the generic `comments` table. Both the AI
checker and users' "Flag" clicks feed ONE queue an admin resolves from Filament.

### Comment model (`app/Models/Comment.php`)
Generic (one model, one table; "posts" is a cosmetic alias in forum context —
never fork). Self-nesting via `parent_id`. Key columns/methods:
- Delete/edit/hide: `deleteBy` / `editBy` / `hide` / `applyModeratorEdit` (see
  Forum Discussions comment actions). Audit columns: `deleted_at`/
  `deleted_by_user_id`, `edited_at`/`last_edited_by_user_id`, `hidden_at`/
  `hidden_by_user_id`, `ai_checked_at`. `last_edited_by_user_id !== user_id`
  signals "an admin touched the author's words" (same pattern as
  `deleted_by_user_id`).
- **`pendingAiReview(): bool`** — derived, NOT a stored column: an unresolved
  **AI-sourced** record exists (`CommentModerationRecord::scopePendingAi`). Drives
  the quarantine display. Batch the equivalent lookup for a list (never per-row)
  — `ForumDiscussionPage::responses()` does ONE query keyed
  `[comment_id => record_id]`.

### comment_moderation_records + CommentModerationRecord (`app/Models/Moderation/`)
- Direct `comment_id` FK (cascade). `flagged_by` (`ModerationFlagSource`: ai |
  user), `content` (snapshot at creation), `ai_message`, `moderated`,
  `moderated_as_ok`, `moderation_action` (`ModerationAction`: approved | hidden |
  deleted | edited_and_approved), `moderated_by_user_id` (**null = system/auto,
  not a human**), `fixed_by_author`, `moderated_content` (what the content became
  — author fix OR admin edit). Snapshot audit fields: `circle_id` (nullable,
  nullOnDelete — survives circle deletion), `commentable_type_label`,
  `url_to_parent` (front-end link, a creation-time snapshot; may go stale),
  `forum_group_visibility` (nullable string, backing value of the owning group's
  `ForumGroupVisibility` at flag time — drives the Internal-group exclusion for
  platform admins; see below).
- **`open(Comment, ModerationFlagSource, ?aiMessage)`** — create-or-reuse: never
  duplicates a pending record for the same comment+source (Ai and User coexist).
  Populates the snapshot fields via `App\Support\Moderation\CommentableTypeLabeler`
  (`label` / `circleIdFor` / `urlFor` / `forumGroupVisibilityFor` — one
  match-case per commentable type; "add a case" to support a new type). Both
  creation paths (the `comments:check-moderation` command and the user-flag
  action) flow through `open()`, so the snapshot happens in ONE place.
- **`forum_group_visibility` is forward-only**: rows created before the column
  existed are NULL. NULL means "flagged before this rule" — treated as
  UNRESTRICTED, NEVER as 'internal'. Only an explicit `'internal'` is ever
  hidden. It's a SNAPSHOT: a later visibility change does not touch old rows. No
  backfill (`2026_07_24_000001` adds the column only).
- **Resolutions** (model methods, so testable without Filament):
  `resolveApproved($admin)`, `resolveEditedAndApproved($admin, $content)` (fixes
  the wording via `applyModeratorEdit` — does NOT requeue an AI recheck),
  `resolveHidden($admin)`, `resolveDeleted($admin)`, and
  `resolveAutoApproved()` (moderated_by_user_id = null).

### AI checker — interface now, stub today, real AI later
- `App\Contracts\Moderation\CommentModerationCheckerContract::check(string):
  ModerationCheckResult` (`containsOffensiveContent`, `message`).
- `StubModerationChecker` (deterministic, matches `config('moderation.trigger_words')`
  — no external call). **Bound in `AppServiceProvider::register()` — the ONLY
  place to change for real AI; never reference the concrete class.**
- **`comments:check-moderation`** (`chunkById`, idempotent; scheduled
  `everyTenMinutes`): checks `ai_checked_at IS NULL` non-deleted comments, stamps
  `ai_checked_at`, opens an Ai record for offensive ones. **Auto-resolve:** a
  clean recheck of a comment that has a pending Ai record (author fixed it)
  auto-approves it (`resolveAutoApproved`); still-offensive stays pending (no
  dupe); first-time-clean does nothing. Approve therefore "reverts" quarantine
  with no unhide step.

### Filament — CommentModerationRecordResource (Governance)
List + View pages. **Role-scoped** in `getEloquentQuery` (comment →
forumDiscussion → forumGroup → circle). The scoping now has THREE branches:
**superadmin** unscoped (Internal included); **platform admin** (`admin`, not
superadmin) sees every circle's records EXCEPT those with
`forum_group_visibility = 'internal'`, UNLESS the record's snapshot `circle_id`
is one they're circle_admin of — NULL visibility stays visible (only explicit
`'internal'` is hidden); **pure circle_admin** only their own circles (Internal
fine there — they ARE the circle's admin). Uses the snapshot columns, no live
group join. `canViewAny` admits circle_admins. Row actions AND View-page header actions
(shared `public static` handlers, visible while `moderated = false`): **Approve**,
**Edit & Approve** (pre-fills current content → `EditedAndApproved`), **Hide**,
**Delete**. "Moderated by" column reads "Auto-approved" for system-resolved
(null) records. A circle filter (pre-fillable from the Oversight page). The
front-end "Pending Review" badge deep-links `getUrl('view', ['record' => …])`.

---

## Polls (the `voting` service)

Structured collective decisions inside a Circle: elections, propositions and
rating exercises. BUILT end to end — schema, models, a pure tally, the service
handler, UI and tests.

**Vocabulary is governed by `CONTEXT.md`**, the project glossary. Three terms
are already taken by other subsystems and MUST NOT be reused here:
- a poll answerer is a **Respondent**, never a "Participant" (forums own that
  word — a contribution-derived count);
- the sentence above the options is the **Prompt**, never a "Question"
  (`poll_questions` is structural and never surfaces in the UI);
- "Anonymous" must never appear — see Attribution below.
Other defined terms: Poll (the genus — an Election or Proposition is a
*description*, never stored), Response Shape vs Tally Method, Electorate,
Qualifying Date, Roster, Result, Organiser, Poll Group.

### THREE DECISIONS THAT LOOK LIKE BUGS WITHOUT THEIR ADR

Read `docs/adr/0001`–`0003` before changing any of this. Each describes
something a reasonable person would try to "fix".

1. **There is no `closed` status, and a finished poll still says `published`**
   (ADR-0001). `status` records WHY a poll stopped EARLY — `draft`,
   `published`, `concluded`, `cancelled` — and never WHETHER it is open.
   Scheduled/Open/Closed are DERIVED from `opens_at`/`closes_at`, so the two
   can never disagree and no cron is needed. Concluding and cancelling both
   stamp `closes_at`, so status annotates the clock rather than competing with
   it. `archived_at` is a separate timestamp, NOT a status case, so archiving
   never erases how a poll ended.
   A **Result** is frozen on first read after close AND by
   **`polls:freeze-results`** (hourly), so a poll nobody visits still gets a
   record — freezing is insurance against the tally code changing under a
   settled decision, not merely a cache. Both paths are idempotent and never
   overwrite. That command writes `result`/`result_frozen_at` ONLY: a scheduled
   job must never write poll state.
2. **`poll_electorate` is materialised even though `circle_memberships` is an
   append-only log** (ADR-0002), so it looks like redundant denormalisation. It
   is not: `metadata.internal_role_approved` is MUTATED IN PLACE and keeps no
   history, so deriving a past electorate answers with today's approvals — the
   wrong electorate on exactly the polls whose eligibility is most restrictive.
   Written once at publish from the membership log as of `qualifying_date`.
3. **`polls.poll_group_id` is NOT NULL with no default group** (ADR-0003).
   Every poll belongs to exactly one Poll Group; there is no "General" bucket.
   Groups are archived, never deleted (`restrictOnDelete`), because a Concluded
   poll is a record of a community decision.

### Attribution — verifiable without surveillance

`hide_voter_identities` is a DISPLAY rule, not storage: `poll_responses.user_id`
is always written. It is withheld from EVERYONE — members, the Organiser,
platform admins and superadmins alike — the sole exception being a user viewing
their own response. **This is NOT a secret ballot** and must never be described
as one.

What lets a member trust a result anyway, and why all four parts matter:
- the **Electorate** is fixed at publish, so the denominator cannot move;
- the **Roster** publishes a live COUNT while the poll runs and the NAMES only
  once it Closes — a live list of who responded is a list of who has yet to
  comply;
- the **Result** freezes at Close (per-option totals, turnout, winner), so
  later recomputation CHECKS it rather than replacing it;
- totals sum to turnout, so the arithmetic is checkable by hand.

`Poll::roster()` THROWS when `rosterIsVisible()` is false rather than returning
an empty collection — an empty roster is indistinguishable from "nobody
responded", so a caller who forgot to check would render a plausible falsehood.
Always gate on `rosterIsVisible()`.

### Tables and where the code lives

`poll_groups`, `polls`, `poll_electorate`, `poll_questions`, `poll_options`,
`poll_responses`, `poll_response_items`, plus platform-curated
`poll_rating_scales` / `poll_rating_scale_points`.

- **`App\Enums\Polls`** — `PollStatus`, `PollEligibility`, `PollResponseShape`,
  `TallyMethod`. A ranked ballot may be counted by instant runoff OR Borda, and
  they can disagree entirely — instant runoff eliminates a candidate with no
  first preferences before their second-place support counts, Borda scores
  every place — so the choice is the organiser's, per poll.
  `PollResponseShape::allowedTallyMethods()` is the ONE
  definition of which counting rules a ballot shape permits (the same
  single-source pattern as `allowedInternalRoles()`); the creation UI reads it,
  so no invalid pairing is reachable.
- **`App\Models\Polls`** — domain predicates live here: `Poll::isOpen()`,
  `isClosed()`, `isEntitled()`, `canRespond()`, `canBeEndedBy()`,
  `isAmendable()`, `stateKey()`, `rosterIsVisible()`.
- **`App\Support\Polls`** — `Tally`, `Ballot`, `Mark`, `PollResult`. PURE: no
  Eloquent, no clock, no user identities. Same inputs always give the same
  Result, which is what makes a frozen Result checkable years later.
- **`VotingService`** (`App\Services\Circles\`) — the single write entry
  point, as `ForumService` is for forums.
- **`App\Livewire\Communities\Services\Polls\`** + matching views — the
  per-service grouping convention.

**The service key stays `voting`** (a stable handle, like `content_blocks.key`);
the LABEL is "Polls". `services.name` is translatable — do not confuse the two.

### Authorization

Reuses existing primitives; no parallel mechanism.
- Creating/managing groups and composing polls → `Circle::isManageableBy()`.
- Conclude / Cancel → `Poll::canBeEndedBy()`: the Organiser **while they remain
  a member**, or any circle manager unconditionally. Leaving the Circle ends an
  Organiser's authority without unmaking them the Organiser. A circle admin can
  END a poll they cannot READ — power over process, none over content.
- Responding → `Poll::canRespond()`, re-checked inside `VotingService::respond`.
  Eligibility is tested when a response is CAST, never at tally time, so no
  published count moves after the fact.
- Editing → `Poll::isAmendable()` (no responses yet). Publishing is NOT the
  point of no return; the first response is.

### Rating scales are platform vocabulary

`poll_rating_scales` has no `circle_id` deliberately: scales are curated
centrally and shared, so "Strongly Agree" means the same thing in two circles'
results. Circle admins PICK, never mint. Seeded by
`Database\Seeders\Polls\PollRatingScaleSeeder` (idempotent; matches points on
`(scale, value)` and NEVER deletes one — a cast response references it and the
FK is `restrictOnDelete`).

### Tests — three seams

- `tests/Unit/PollTallyTest.php` — the pure tally, no database. Instant-runoff
  is tested exhaustively here, with every expected winner worked out by hand.
- `tests/Feature/PollModelsTest.php` — model predicates.
- `tests/Services/VotingServiceTest.php` — every state change.

### Deferred by decision (not oversight)

Majority-runoff (not a Tally Method at all — it spawns a second poll rather
than computing over the first),
Surveys, completion actions, secret ballots, a publicly-viewable LIVE poll, a
stored `kind` on polls, and NOTIFICATIONS (unresolved — see the end of
`POLLING_SERVICE.md` and `.scratch/polls/issues/02`).

---

## Circle Stewardship (Oversight)

A layer ABOVE circle_admins for platform admins to watch queue health per circle.

- **`Circle::scopeManageableBy(Builder, ?User)`** — the query counterpart to
  `isManageableBy()` (single-record). Both share ONE rule:
  `administeredCircleIdsSubquery()` (the circle_admin check, off `model_has_roles`
  — Spatie teams mode) + `isPrivilegedManager()` (admin/superadmin bypass). Admin
  → unfiltered; circle_admin → only their circles; guest → none.
- **`App\Contracts\Stewardship\CircleStewardshipQueue`** — `queueLabel()`,
  `pendingCountForCircle(Circle, User $viewer)`,
  `oldestPendingAgeForCircle(Circle, User $viewer)`, `filamentUrlForCircle()`.
  Implemented by **`Request`** ("Pending Requests") and **`CommentModerationRecord`**
  ("Comment Moderation"). Registry: **`config/stewardship.php`** (a flat FQCN
  list; add a queue = one line). NB: this makes those models reference their
  Filament resources for the URL — inherent to the contract.
- **The `$viewer` parameter is for per-viewer visibility narrowing.** `Request`
  ignores it (no visibility concept). `CommentModerationRecord` uses it: for a
  plain platform admin (not superadmin, and not THIS circle's own circle_admin)
  it EXCLUDES `forum_group_visibility = 'internal'` records from BOTH the count
  and the oldest-pending age — fully invisible, so an oversight row gives no hint
  an Internal group has anything pending (superadmin / this circle's circle_admin
  see the full set; NULL visibility always counts). The Oversight page passes
  `auth()->user()` (non-null — its mount is admin/superadmin-only).
- **Oversight page** — `GET /communities/{circle}/oversight`
  (`CircleOversightPage`, route `communities.oversight`), **platform
  admin/superadmin ONLY** (403 for everyone else, circle_admins included). One
  row per registered queue: label, pending count, oldest-pending age, a View
  link; rows past the neglect threshold get an amber "Overdue" highlight. An
  amber, admin-only "Oversight" link sits in the community-page header
  (`CommunityPage::canOverseeCircle`).
- **Neglect threshold** — `stewardship_neglect_days` (default 7) stored as a
  **ContentBlock** (the existing admin-configurable-value store; NOT a new
  settings table), read via `ContentBlock::get('stewardship.neglect_days', '7')`.

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
- Fallback chain: pt_BR → pt → en → key itself (visible = bug).
  NOT stock Laravel, which resolves only [locale, fallback_locale]. The base
  language is inserted by `Lang::determineLocalesUsing()` in
  `AppServiceProvider::boot()`. `pt` is therefore deliberately ABSENT from
  `config('app.supported_locales')` (`["en","pt_BR"]`): it is a shared base
  LAYER, not a selectable locale, so `lang/pt/` is loaded but never offered in
  the switcher. A new translation goes in `lang/pt/`; `lang/pt_BR/` is for
  Brazilian overrides only.

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

### Time: stored UTC, displayed in a wall clock

`config('app.timezone')` is UTC and STAYS UTC — storage, `now()`, comparisons.
`config('app.display_timezone')` (default `Africa/Johannesburg`) is the wall
clock the INTERFACE speaks. The two are converted only at the boundary, via
**`App\Support\DisplayTime`**:

- `fromInput()` — a wall-clock string a user typed (a `datetime-local` field)
  read in the display zone. Never `now()->parse()` such a value: parsing "12:21"
  as UTC when the user meant 12:21 SAST silently shifts it two hours.
- `toInput()` — a stored instant rendered back into a `datetime-local` field, so
  reopening a form shows what was typed.
- `forDisplay()` / the **`Carbon::inDisplayZone()` macro** — for rendering an
  absolute date or time. Registered in `AppServiceProvider`; use it at EVERY
  absolute render: `$date->inDisplayZone()->format('d M Y, H:i')`.

Filament is pointed at the same zone with `FilamentTimezone::set()` in
`AppServiceProvider`, so its date columns and pickers agree with the front end.

`diffForHumans()` needs no conversion — a difference is the same in any zone —
which is why this went unnoticed until Polls became the first feature to show
and accept absolute times. A date-ONLY render is not exempt: 22:40 UTC is
already the next day in SAST, so an unconverted `format('d M Y')` shows the
wrong date for two hours every night.

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

## User Dashboard (/dashboard)

Authenticated-only. One bookmarkable route PER section (server-side, NOT
client-side tab switching), so each is back-button/bookmark friendly.

- **Routing:** `/dashboard` → redirect to `/dashboard/news`; then
  `dashboard.news|calendar|communities|campaigns|voting`, each a thin Livewire
  component under `App\Livewire\Dashboard\*` using `#[Layout('layouts.dashboard')]`.
- **Layout:** `layouts/dashboard.blade.php` (new, additive — `app.blade.php`
  untouched) — a persistent left vertical nav (active-state per current route via
  `request()->routeIs`) + a content slot. The app top bar was extracted to
  `layouts/partials/top-nav.blade.php` and is included by BOTH `layouts.main` and
  `layouts.dashboard`, so profile / `/admin` / language stay in the TOP bar (one
  copy), never in the vertical nav.
- **News / Calendar / Campaigns / Voting:** styled "coming soon" placeholders
  (shared `livewire.dashboard.placeholder` view). No backend — genuinely empty.
- **My Communities** (`DashboardCommunities`): the viewer's ACTIVE memberships,
  eager-loaded, grouped **"Where you're an admin"** (via `Circle::administeredBy`
  — ONE query for the circle_admin set) vs **"Where you're a member"**, each
  sorted by the materialised `path` so same-region circles cluster; an empty
  group's heading is omitted. Bounded by how many circles the user is in — never
  paginated. Each row: the community's **Explore breadcrumb trail**
  (`Circle::ancestors()` + `<x-circle-breadcrumb>`) on line 1; circle name
  (linked) + an `admin`/`member` role badge on line 2.
- **Recently Visited:** `circle_visits` table (`unique(user_id, circle_id)`,
  `last_visited_at`) + `CircleVisit::record()` (idempotent upsert). Recorded in
  `CommunityPage::mount()` for logged-in viewers (after the visibility gate).
  `DashboardCommunities::recentlyVisited()` = distinct visited circles EXCLUDING
  ones the user is an active member of (no overlap with My Communities),
  most-recent-first, capped at 8, filtered to still-visible circles.
- **`<x-circle-breadcrumb :circle :ancestors>`** (`resources/views/components/`)
  — renders the trail a community shows in the Explore breadcrumb, built by
  **`App\Support\Circles\ExplorerBreadcrumb::for()`** (the ONE place that rule
  lives). Pass the already-fetched `ancestors` to avoid a re-query. Crumbs:
  1. root country place → `/explore` (the explorer's national level is
     `selectedCircleId = null`, NOT the country circle id — see Explore);
  2. each ancestor by its SHORT place name (`locatable->name`, "Gauteng", not
     the verbose circle name) → `/explore?circle=<id>`;
  3. for a sub-community only, a closing **type crumb**
     (`CommunityType::pluralLabel()`, e.g. "Organisations") →
     `/explore?circle=<place>&community=<CaseName>`.
  The circle's OWN name is deliberately NOT a crumb (a LocationCommunity's trail
  therefore ends at its parent place) — every surface showing the trail already
  shows the name, linked, directly beneath it. So EVERY crumb is a link; there
  is no "current"/unlinked crumb.
  Crumbs link INTO the explorer (`wire:navigate`), NEVER to the ancestor's
  community page — clicking a place crumb resumes browsing there, which is the
  whole point. `CommunityType::pluralLabel()` is shared with
  `Explore\Breadcrumb::typeLabel()` / `ExploreCommunities::labelFor()`, so the
  labels cannot drift. NB: the Explore breadcrumb component
  (`App\Livewire\Explore\Breadcrumb`) is `$parent`-coupled to ExploreCommunities
  and NOT reusable for href links — hence this separate renderer.

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
  - `CommentModerationRecordResource` (Governance): visible to admins AND
    circle_admins, role-scoped via `Circle::scopeManageableBy` — see Comment
    Moderation
- Nav group `Platform` registered for platform-management resources; `Governance`
  hosts `RequestResource` + `CommentModerationRecordResource`
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
dashboard.blade.php: authenticated /dashboard sections (top bar + left vertical
nav); shares the top bar with main via layouts/partials/top-nav.blade.php
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
- ContentBlockSeeder — 9 content blocks (4 page-copy + 4 collapsible how-to +
  `stewardship.neglect_days` [plain number, default 7]; idempotent,
  updateOrCreate by key)
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
- Filament resources beyond ContentBlock + EmailTemplate + Request +
  CommentModerationRecord + ThemeSuggestion + CircleMembership
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
- Notification, social media, learning service implementations (voting IS
  built — see the Polls section)
- Real AI moderation backend — `CommentModerationCheckerContract` is bound to a
  deterministic stub; swap the binding for OpenAI/local LLM (see Comment
  Moderation). Also deferred: forum pin/lock toggle UI, discussion search,
  circle_admin scoping is on the moderation queue but NOT yet on the Oversight
  page (platform-admin only there by design)
- Payment/subscription system
- API endpoints
- In-app notification templates (email templates are built — see
  Email Templates section)
- Poll notifications of any kind — nothing tells an eligible member that a poll
  opened, closes soon, has a result, or was cancelled. Deferred as UNRESOLVED
  rather than unwanted; the open questions are written up at the end of
  POLLING_SERVICE.md

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
- Rendering an absolute date without `->inDisplayZone()` — storage is UTC and
  the interface speaks `app.display_timezone`. Date-only renders are affected
  too: 22:40 UTC is already tomorrow in SAST
- Parsing a `datetime-local` value with `now()->parse()` — it is a wall clock
  with no zone; use `DisplayTime::fromInput()` or it lands hours out
- Treating services.name as a plain string — it is translatable JSON too
  (easy to miss: the service KEY is a plain string right beside it). Never
  write it with the query builder; a plain-string assignment through the
  model sets only the current locale and preserves the rest
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
- Naming a model method `delete()` — Eloquent owns it; the comment delete is
  `Comment::deleteBy($actor)`. Author edit is `editBy()`, moderator edit is
  `applyModeratorEdit()` (the latter deliberately does NOT null `ai_checked_at`)
- Referencing `StubModerationChecker` directly — always resolve
  `CommentModerationCheckerContract`; the binding is the only real-AI swap point
- Checking "pending AI review" per-comment in a loop — batch it (one query);
  and remember only Ai-sourced unresolved records quarantine, never User-sourced
- Adding a stored `pending`/moderation boolean on `comments` — that state is
  derived from `comment_moderation_records` (`Comment::pendingAiReview()`)
- Branching on `polls.status` to decide whether a poll accepts responses — it
  records WHY a poll stopped early, never WHETHER it is open. Ask
  `Poll::isOpen()` / `isClosed()`; and never add a stored `closed` case or a
  cron to write one (ADR-0001)
- Deriving a poll's electorate from `circle_memberships` because the log is
  append-only — `internal_role_approved` is mutated in place and keeps no
  history, so a past electorate cannot be reconstructed (ADR-0002). It is
  snapshotted into `poll_electorate` at publish, on purpose
- Filtering responses at TALLY time (e.g. dropping people who have since left
  the circle) — eligibility is tested when a response is CAST, so a published
  count never moves afterwards
- Calling `Poll::roster()` without checking `rosterIsVisible()` — it THROWS by
  design, because an empty roster is indistinguishable from "nobody responded"
- Confusing `Mark::value` with `Mark::ratingScalePointId` — the first is the
  numeric score a Tally averages, the second is what a response STORES. They
  coincide only by luck
- Calling a poll "anonymous" in UI or code — identity is always stored, so it
  is not a secret ballot; the flag is `hide_voter_identities` and it is a
  display rule
- Giving `poll_response_items.rank` a non-null default — the
  `(poll_response_id, rank)` unique index relies on NULLs not being compared,
  which is what lets a rating response leave every rank null

---

## Agent skills

### Issue tracker

Issues and specs live as local markdown files under `.scratch/<feature-slug>/`.
See `docs/agents/issue-tracker.md`.

### Triage labels

The five canonical roles, unchanged (`needs-triage`, `needs-info`,
`ready-for-agent`, `ready-for-human`, `wontfix`).
See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root.
See `docs/agents/domain.md`.
