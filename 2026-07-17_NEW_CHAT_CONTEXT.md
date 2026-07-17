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
- Filament v5 (filament/filament ^5.6) — admin panel at /admin + forms
- Spatie Laravel Permission (teams enabled, team_foreign_key = circle_id)
- Spatie Laravel Translatable (circles.description + content_blocks.content
  /title + email_templates.subject/body)
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
- Event — event spaces

### Geographic levels (locatable) — SA hierarchy:
```
Country
  └── Province
        ├── DistrictMunicipality
        │     └── LocalMunicipality
        │           └── MainPlace        ← always terminal
        └── City
              └── MainPlace              ← always terminal
```

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
    - LocationLevel — geographic abstraction layer (see below)

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

8. **Explore page** — public Livewire 4 page, two-section layout
   (see Explore UI Supplement below)

9. **Geographic abstraction layer** — LocationLevel enum + HasLocationLevel
   interface allow future non-SA country hierarchies without schema changes
   (see Geographic Abstraction section below)

10. **Internationalisation** — PHP array lang files under lang/en/ organised
    by feature area; spatie/laravel-translatable on circles.description +
    content_blocks.content; circle names treated as proper nouns (not
    translated); browser Accept-Language auto-detection via
    SetLocaleFromBrowser middleware

11. **Community page** — dedicated full page at /communities/{circle}
    replaces modal for viewing a community; stateless back-link via
    ?from= query parameter

12. **Filament admin panel** — panel at /admin (User::canAccessPanel: admin +
    superadmin, plus any circle_admin — see Filament section). "Content blocks"
    is the first
    resource: an admin-editable, locale-aware CMS for small pieces of copy
    rendered into public views via `<x-content-block>` (see below)

13. **Email templates** — DB-backed, locale-aware email system:
    email_templates table (translatable subject/body), EmailTemplate model,
    EmailServiceHandler (implements CircleServiceContract) that renders +
    sends via {{ variable }} substitution, TemplateMailable + mail views,
    and a Filament EmailTemplateResource under a "Communication" nav group
    (see Email Templates section below)

14. **Organisation approval / Requests** — external approval workflow. A
    logged-in user submits an Organisation Community; it stays PENDING until
    the org's contact approves it via an emailed token link. Backed by a
    generic `requests` table + Request model, a public RequestController,
    a Filament RequestResource under a "Governance" group, and a daily
    `requests:expire` command. CircleStatus enum + circles.status gate the
    circle's lifecycle. (See Organisation Approval & Requests section below.)

15. **Circle administrators** — Circle::administrators() returns the users
    holding the `circle_admin` role scoped to that circle (queries the
    model_has_roles pivot on circle_id directly, since Spatie runs in teams
    mode; a circle can have zero or many). Circle::responsibleAdminFor(Circle)
    is an escalation resolver: call it on the circle the request concerns
    (e.g. $request->circle); it walks the circle + ancestors nearest→root for
    the nearest LocationCommunity admin, falling back to global admin →
    superadmin. Climb rule is intentionally LocationCommunity-only (non-location
    circles skipped) — route to the geographic steward, not any circle_admin
    above. WIRED: Request::createForOrganisation() stores it in
    requests.responsible_admin_id at creation; AddCommunityModal then emails
    that admin (email.organisation_approval_admin_notice, link to the Filament
    request view; no-op when null). Surfaced in the Governance RequestResource
    (view field, table column, "Assigned to me" filter) — notification/
    discovery only, never gates who can act. Administrators are also shown on
    the Community page.

16. **Services as Livewire UI containers** — services (`services` table:
    key, handler_class, container_component) each have a CircleServiceContract
    handler. containerComponent(): ?string returns the FQCN of a
    App\Livewire\Communities\Services\*ServiceContainer (thin: mount(Circle)
    + service() delegating to the handler), or null (HasNoContainerComponent
    trait — Email, Manage Users). A circleable implementing
    App\Contracts\Circles\HasDefaultServices (only LocationCommunity today:
    news,events,forums,media,voting) gets those keys attached IN ORDER at
    creation, in Circle::booted() (covers all creation paths; checked via
    instanceof, not method_exists). `circles:backfill-services` attaches
    missing defaults to existing circles (idempotent, chunkById, manual — not
    scheduled). The Community Page renders each attached service with a
    container as a TAB (badges removed) via <livewire:dynamic-component>.

17. **Circle membership** — circle_memberships table + CircleMembership model
    (circle_id, user_id, internal_role nullable, joined_at, left_at [null=active],
    metadata; rows never deleted, only closed via left_at). Per-type rules via
    HasMembershipRules interface + HasStandardMembershipRules trait (2 concurrent,
    3-month hold, no roles); OrganisationCommunity overrides allowedInternalRoles
    → ['organisation_member']. Domain methods on Circle: activeMembership(),
    canUserJoin() (admin/superadmin bypass; counts same-type active memberships;
    returns allowed/reason/available_at/swappable), joinAsMember() (validates
    role, re-checks server-side unless skipChecks, closes a swapped membership),
    leave(). Org-creator gets a direct membership (skipChecks) in BOTH
    RequestController::approve() and RequestResource::approveAction(). Community
    Page has $membership/$isVisitor (passed into every *ServiceContainer mount)
    + a Join/Leave UI (modal only when there's a role question or a swap).
    CommunityCard label: "Enter" (member) vs "Visit" (else), batch-loaded once
    via ExploreCommunities::memberCircleIds() (no per-card query). Read-only
    Filament CircleMembershipResource (Governance, admin/superadmin).
    circles:backfill-admin-memberships (idempotent, manual): gives existing
    circle_admins a membership; org-community admins get organisation_member.
    (The forum-groups system now exists — see decision 20. Membership-BASED
    forum visibility filtering [$isVisitor gating public vs private groups] is
    still deferred to a later Forums pass.)

19. **RequestType enum + org-member-claim flow** — see the CLAUDE.md "Enums" +
    membership sections (introduced alongside decision 18's approval work).

20. **Forums groups (overview + CRUD)** — real implementation behind
    ForumService/ForumServiceContainer. forum_groups + forum_discussions tables
    (created_by nullable+nullOnDelete; forum_groups unique per (circle_id,slug);
    forum_discussions FULLTEXT is MySQL-only/guarded). Models in app/Models/Forums;
    4 plain enums in app/Enums/Forums (ForumGroupVisibility [public/private/
    internal] + Status, ForumDiscussionStatus/ModerationStatus). Visibility:
    Internal = members with ANY approved internal_role (via
    CircleMembership::hasApprovedInternalRole — never hardcode organisation_member).
    ForumGroupVisibility::participationFloor() is the single view→participate rule
    (Public→Private); ForumGroup::canView()/canParticipate() use it. The overview
    list + stats filter by canView (managers bypass). ForumService does the writes
    (create/update/deactivate + slug, explicit or derived). ForumServiceContainer
    tab: stats (Groups, Participants=0 stub, Discussions — all scoped to viewable),
    search + status filter (default active), gated Create/Manage via
    Circle::isManageableBy(). Create/edit via a wire-elements ForumGroupModal
    (sectioned: Basic Info incl. editable slug / Visibility & Access radio + live
    read-only Group Access note / Group Images placeholder / Tags picker; "Save
    Group"). Modal opened via a BLADE $dispatch('openModal') (a PHP $this->dispatch
    from the nested container does NOT reach the modal host under Livewire 4 +
    wire-elements 3.0.4). New-entity buttons use the "+ " prefix convention.
    Discussions page at /communities/{circle}/forums/{forumGroup:slug}
    (scopeBindings, ?from= back-link) — placeholder body. Deferred: discussion
    list/detail, join, moderation, pin/lock (canParticipate is ready to gate them).

21. **Theme-based tagging + tag suggestions** — a lightweight descriptive tag
    layer over `themes`, UNRELATED to ThemeCommunity. `taggables` polymorphic
    pivot + HasTags trait (tags() morphToMany Theme) on Circle, ForumGroup,
    ForumDiscussion ONLY (Organisation tagged via its OrganisationCommunity
    Circle). Theme inverses circles()/forumGroups()/forumDiscussions(); added
    the missing Theme::themeCommunities() HasMany (the FK/belongsTo half already
    existed). Uniform canBeTaggedBy(?User): Circle→isManageableBy;
    ForumGroup→owning circle; ForumDiscussion→author OR owning circle. Reusable
    TagPicker Livewire (attach/detach gated; "suggest a tag" open to any authed
    user → pending ThemeSuggestion with origin, attaches nothing) is the edit
    surface. DISPLAY: <x-tag-list> (plain bordered pills, alphabetical) under the
    description on the community page (Circle) + each ForumGroup card; managers
    also get an "Edit tags" affordance opening the picker (inline on the circle
    page, edit-modal on the forum card); non-managers see read-only.
    ForumDiscussion has no display surface yet. ThemeSuggestion model
    (ThemeSuggestionStatus enum) approve() [Theme firstOrCreate dedupe +
    auto-attach to origin + email] / reject(note) [email]. Filament
    ThemeSuggestionResource (Platform, admin/superadmin) with Approve/Reject.
    ThemeCommunity circles are auto-tagged with their own theme on creation
    (Circle::booted created hook, guarded on theme_id); circles:backfill-theme-tags
    covers legacy circles (idempotent, manual).

18. **RequestType enum + organisation-member-claim flow** — requests.type is now
    backed by App\Enums\RequestType (cast on Request): OrganisationApproval,
    CircleJoin/LocationRequest/CircleAssociation (reserved — filter/badge only),
    OrganisationMemberClaim. A non-creator claiming internal_role
    'organisation_member' (joinAsMember, skipChecks=false) becomes a member
    immediately but with metadata.internal_role_approved='pending', and an
    OrganisationMemberClaim Request (requestable = the membership) is opened to
    the org contact (email.organisation_member_claim_request). RequestController
    dispatches approve/reject on type: approve → 'approved' + claim_approved
    email to claimer; reject → 'rejected' (internal_role kept for audit) +
    claim_rejected email. CircleMembership::hasApprovedInternalRole() is the ONLY
    correct elevated-access check (never internal_role alone). Claim requests
    show in the Governance Requests table but the Approve/Deny/Resend actions are
    hidden for them (external/token-based only). Creator grants (skipChecks) do
    NOT trigger the claim.

---

## Geographic Abstraction Layer (Multi-Country)

Introduced to support future non-SA country hierarchies (e.g. Brazil)
without changing existing SA tables, seeders, or data.

### LocationLevel enum (app/Enums/LocationLevel.php)
```php
enum LocationLevel: string {
    case Country  = 'country';
    case Region   = 'region';    // Province, State, etc.
    case District = 'district';  // DistrictMunicipality, etc.
    case Local    = 'local';     // LocalMunicipality, etc.
    case City     = 'city';      // Metropolitan — has MainPlace children, NOT terminal
    case Place    = 'place';     // MainPlace — ALWAYS terminal (isTerminal() = true)
}
```

IMPORTANT: Only Place is terminal. City is NOT terminal — it has MainPlace children.

### HasLocationLevel interface (app/Contracts/Geographic/HasLocationLevel.php)
All SA locatable models implement:
- `locationLevel(): LocationLevel`
- `locationLabel(): string` — human-readable name of this instance
- `locationParentId(): ?int` — null for Country

### LocatableType enum additions
- `locationLevel(): LocationLevel` — returns correct level for each type
- `isTerminal(): bool` — proxy for `$this->locationLevel()->isTerminal()`

### Column browser usage
- `isTerminal()` drives "No further sub-areas" message and
  "Your location not listed?" button
- Whether to render a next column is driven by `$children->isNotEmpty()`
  (DB check), NOT by isTerminal()

---

## Internationalisation

### Approach
- PHP array files under `lang/en/` organised by feature area
- Keys are stable snake_case strings (e.g. 'no_communities')
- Dynamic values passed as Laravel placeholders
- Circle names: proper nouns — NOT translated
- Circle descriptions: spatie/laravel-translatable — stored as
  `{"en": "...", "pt": "..."}` JSON, resolved transparently at runtime
- Content blocks (content_blocks.content): spatie/laravel-translatable —
  stored as `{"en": "...", "pt_BR": "..."}` JSON, resolved via
  ContentBlock::get() (see Filament Admin Panel section)
- Email templates (email_templates.subject/body): spatie/laravel-translatable
  — same `{"en": "...", "pt_BR": "..."}` JSON pattern, resolved via
  EmailTemplate::getByKey() (see Email Templates section)
- Community type names (Organisation, Campaign, etc.): ARE translatable
- Place/location proper names: NOT translated

### Lang file structure
```
lang/
  en/
    explore.php
    communities.php
    navigation.php
    validation.php
    ui.php
  pt/           ← shared Portuguese base (pt_BR falls back to pt)
    ...
  pt_BR/        ← Brazilian Portuguese overrides
    ...
```

### Locale detection middleware (SetLocaleFromBrowser)
Priority chain (highest to lowest):
1. User's saved locale preference (if field exists on users table)
2. Session-stored locale (key: 'locale')
3. Browser Accept-Language header — negotiated against
   config('app.supported_locales')
4. App default locale (config/app.locale)

Sets both `App::setLocale()` and `Carbon::setLocale()`.
Runs on web middleware group only (covers Livewire XHR automatically).

### Language switcher
LocaleController (invokable) on GET /locale/{locale} (route locale.update) puts
a supported locale in session('locale') and redirects back; middleware applies
it next request (unsupported ignored). Per-locale links in the main nav AND the
Filament admin top-bar highlight the active locale; shown to guests too. No
users.locale column yet — session-scoped.

### config/app.php additions
```php
'supported_locales' => ['en', 'pt_BR'],
```

---

## Filament Admin Panel & Content Blocks

### Admin panel
- AdminPanelProvider (app/Providers/Filament/AdminPanelProvider.php)
- Path /admin, panel id `admin`, `->login()`, dark mode on, primary = Amber
- Access via User::canAccessPanel(): `admin` + `superadmin` (global) AND any
  `circle_admin` (Circle::administeredBy($this)->isNotEmpty() — team-scoped
  role checked across all teams). Since circle_admins can now reach /admin,
  every resource gates itself explicitly:
    - ContentBlockResource, EmailTemplateResource: canViewAny() → admin/super
      only (canAccess() defaults to it, covering nav + all pages)
    - Dashboard (app/Filament/Pages/Dashboard.php, subclass): it's the panel
      HOME (/admin), so canAccess() stays true (denying it 403s the home route,
      not redirect); shouldRegisterNavigation() hides it from circle_admins and
      mount() redirects them to the Requests index
    - RequestResource: visible to admins AND circle_admins, role-scoped (below)
- Nav group `Platform` registered for platform-management resources
- Auto-discovers Resources/Pages/Widgets under app/Filament/

### Content Blocks (admin-editable, locale-aware CMS copy)
Small pieces of copy (banners, hints, instructions) rendered into public
views and editable in the admin panel.

**content_blocks table** (base migration + 2026_07_07 add-collapsible)
- `key` (string, unique) — stable lookup handle used in views
- `description` (string) — admin-facing note
- `content` (JSON, translatable) — `{"en": "...", "pt_BR": "..."}`
- `title` (JSON, translatable, nullable) — heading for collapsible blocks
- `is_html` (bool, default true) — rich HTML vs plain text
- `collapsible` (bool, default false) — render as expand/collapse disclosure
- `default_collapsed` (bool, default true) — initial state when collapsible

**ContentBlock model (app/Models/ContentBlock.php)**
- `$translatable = ['content', 'title']`; casts is_html/collapsible/
  default_collapsed to boolean
- `ContentBlock::get(string $key, string $fallback = ''): string`
  - Cached 1h per key+locale
  - Resolution: current locale → app.fallback_locale (en) → $fallback
  - Markup/whitespace-only content (e.g. `<p></p>`) treated as blank
- Cache auto-flushed on saved/deleted (booted() hooks), per supported locale

**ContentBlockResource (app/Filament/Resources/ContentBlocks/)**
- Under `Platform` nav group
- `key` disabled on edit (stable handle)
- Toggles: is_html, collapsible (live), default_collapsed (hidden unless
  collapsible)
- Per-locale tabs (from config('app.supported_locales')): title TextInput
  (visible only when collapsible) + content RichEditor (is_html) / Textarea
- Table: per-locale content checkmark + a collapsible boolean icon column
- EditContentBlock hydrates full content AND title translations on fill

**ContentBlockSeeder** — registered in DatabaseSeeder, idempotent
(updateOrCreate by key). Seeds English only; pt_BR left blank (falls back
to English). Keys: explore.welcome_banner, explore.column_browser_hint,
community.join_instructions, onboarding.new_user_welcome, plus 4 collapsible
how-to blocks community.how_to_add.{campaign,course,event,theme}
(title "How this works", placeholder content). NOTE: the organisation
how-to block (community.how_to_add.organisation) exists in the dev DB but
is NOT in the seeder yet.

**x-content-block Blade component**
`<x-content-block key="explore.welcome_banner" fallback="…" />`
- Props: key, fallback, collapsible, collapsed, title — collapsible/collapsed/
  title default to the block's stored values; a non-null inline value overrides
- Non-collapsible: renders ContentBlock::get() directly (as before)
- Collapsible: Alpine disclosure — title on the left, +/- toggle on the right,
  body via x-show + x-collapse (Livewire's bundled Alpine). Initial state is
  server-rendered to avoid FOUC (the project has no x-cloak CSS)
- Renders nothing when empty and the viewer cannot edit
- `{!! !!}` when is_html else escaped; inline edit pencil (top-right, on hover)
  for admin/superadmin only

**Usage:** collapsible how-to blocks render in the Add Community modals — see
Explore UI Supplement → Add Community button.

---

## Email Templates & Communication

DB-backed, locale-aware transactional emails, editable in the admin panel.

**email_templates table** (migration 2026_07_06_000001)
- `key` (string 150, unique) — stable lookup handle used in code
- `description` (string 255, nullable) — admin hint
- `subject` (JSON, translatable) — `{"en": "...", "pt_BR": "..."}`
- `body` (JSON, translatable)
- `is_html` (bool, default true) — HTML vs plain-text rendering
- `available_variables` (JSON array, nullable) — variable whitelist,
  e.g. `["user_name", "action_url"]` (developer-set, not admin-edited)
- `is_active` (bool, default true) — inactive templates cannot be sent

**EmailTemplate model (app/Models/Communication/EmailTemplate.php)**
- `$translatable = ['subject', 'body']`; casts is_html/is_active bool,
  available_variables array
- `EmailTemplate::getByKey(string $key): ?self` — cached 1h per key+locale,
  cache flushed on saved/deleted per supported locale (same pattern as
  ContentBlock)

**EmailServiceHandler (app/Services/Communication/EmailServiceHandler.php)**
- Implements CircleServiceContract; getKey() = 'email'
- `sendTemplate(key, toAddress, variables = [], ?Circle)` — synchronous
- `queueTemplate(key, toAddress, variables = [], ?Circle)` — queued
- Both delegate to a private buildMailable(): resolves template, throws
  RuntimeException if missing/inactive, substitutes `{{ variable_name }}`
  placeholders via strtr(), returns a TemplateMailable
- `$circle` param reserved for future circle-scoped context

**TemplateMailable (app/Mail/TemplateMailable.php)**
- Constructor: (subject, body, isHtml); assigns subject to the inherited
  Mailable::$subject (avoids the typed-property redeclaration fatal)
- HTML → resources/views/mail/template.blade.php
- Plain → resources/views/mail/template-plain.blade.php
- Minimal inline-styled views, no external CSS

**EmailTemplateResource (app/Filament/Resources/EmailTemplates/)**
- Under a NEW `Communication` nav group
- key disabled on edit; description; is_html/is_active toggles
- available_variables shown as read-only chips (disabled TagsInput,
  dehydrated(false))
- Per-locale tabs (from config('app.supported_locales')): subject TextInput
  + body RichEditor (is_html) / Textarea (plain)
- Table: key, description, per-locale "Complete/Missing" badge, is_active
  ToggleColumn, updated_at

**EmailTemplateSeeder** — registered in DatabaseSeeder, idempotent
(updateOrCreate by key). English stubs, empty pt_BR (falls back). Keys:
email.welcome, email.circle_invitation, email.password_reset,
email.organisation_approval_request/confirmed/denied,
email.organisation_approval_admin_notice,
email.organisation_member_claim_request/approved/rejected, and
email.theme_suggestion_approved/rejected (12 total).

**Local mail:** MailHog via MAMP — SMTP localhost:1025, UI at
http://localhost:8025/mailhog (note the /mailhog web path).
Tests never touch MailHog (MAIL_MAILER=array in phpunit.xml + Mail::fake()).

---

## Organisation Approval & Requests

External approval workflow: a logged-in user submits an Organisation
Community; it stays PENDING until the org's contact approves it via an
emailed token link. Only `organisation_approval` is implemented end-to-end
(circle_join / location_request / circle_association are reserved strings).

**CircleStatus + circles.status**
- Enum app/Enums/CircleStatus: Active, Pending, Denied, Suspended, Archived
- circles.status column (default active); Circle casts it + scopeActive()
- New circles default to Active; the org flow sets Pending explicitly

**requests table + Request model (app/Models/Communication/Request.php)**
- type / status / direction (external|internal), requester, circle,
  polymorphic requestable, respondent_email, respondent_user_id,
  responsible_admin_id (FK users, nullable — see decision 15),
  token + token_expires_at,
  responded_at, response_note, metadata (JSON), ulid (public id), soft deletes
- booted() auto-generates ulid + token; scopes pending/expired/external/internal
- createForOrganisation(...); logEmail() appends to metadata.email_log; isExpired()
- Model is App\Models\Communication\Request — alias as RequestModel where Illuminate\Http\Request is used

**Submission (AddCommunityModal)** — auth-guarded org form + duplicate check;
submitOrganisation() creates Organisation + a Pending circle + Request, then
emails the contact; circleId (parent) passed from Add bar AND empty-state;
organisations.contact_job_title column added.

**Public approval (no auth, token-based)** — RequestController show/approve/deny;
routes GET/POST /requests/confirm/{token}[/approve|/deny]; views
requests/{confirm,confirmed,denied,expired} on layouts/public.blade.php
(nav-free). Approve → circle Active + requester gets circle_admin (Spatie,
scoped to circle_id); deny → circle stays pending. Emails link to the GET
landing page (requests.confirm), never the POST routes.

**Governance admin** — Filament RequestResource (app/Filament/Resources/Requests/)
under a NEW `Governance` group: badge columns + filters, read-only view page
with email-log table, and Approve / Deny / Resend row actions (pending/expired).
Role-scoped via getEloquentQuery() (single choke point — Filament resolves
route records through it, so listing AND record pages are scoped):
- admin/superadmin: unscoped, see + act on ALL requests (escalation net — if
  the responsible circle_admin doesn't act, they can).
- circle_admin (non-privileged): only requests where responsible_admin_id =
  them, OR whose circle they administer or a DESCENDANT of it (subtree via
  Circle::administeredBy + path LIKE / isNestedIn; matches responsibleAdminFor's
  upward walk — NOT ancestors).
Action visibility (Approve/Deny/Resend) = status AND userMayActOn(): privileged
act on any pending; circle_admins only within their directed-or-subtree scope.

**Expiry** — `requests:expire` command (chunkById) flips past-expiry pending
requests to expired; scheduled daily in routes/console.php.

---

## Seeded Data

- Full SA demography hierarchy seeded:
  - Country (South Africa, id=191)
  - 9 Provinces
  - All District Municipalities
  - All Local Municipalities
  - All Cities
  - ~14,039 MainPlaces (via MainPlaceCommunitiesSeeder)
- ~12,675 CoordinateData records (lat/lng for MainPlaces)
- LocationCommunity circles for all levels down to MainPlace
- ThemeCommunity circles (national + WC province + Eden DM)
- 8 content blocks (via ContentBlockSeeder): 4 page-copy blocks + 4
  collapsible how-to blocks (community.how_to_add.{campaign,course,event,theme})
- 12 email templates (via EmailTemplateSeeder): welcome, circle_invitation,
  password_reset + organisation_approval_request/confirmed/denied +
  organisation_approval_admin_notice +
  organisation_member_claim_request/approved/rejected +
  theme_suggestion_approved/rejected
- Spatie roles: new_user, full_member, curator, trainer,
  admin, superadmin, circle_admin, circle_full_member, circle_visitor
- 9 service stubs

---

## What Has Been Built

### Database / Models
- Full demography hierarchy with soft deletes on:
  City, LocalMunicipality, DistrictMunicipality, MainPlace
- All 5 community models in app/Models/Communities/
- Organisation and Course entity models in app/Models/
- Circle and Service models in app/Models/Circles/
- CoordinateData model with nearest() static method
  (bounding box ±0.5° + squared Euclidean, fallback to full scan)
- Composite index on coordinate_data(latitude, longitude)
- ContentBlock model + content_blocks table (translatable content)
- EmailTemplate model + email_templates table (translatable subject/body)
- All migrations including circle_associations
- circles.description: JSON column (spatie/laravel-translatable)

### Seeders
- LocationCommunitiesSeeder (country → LM/City)
- MainPlaceCommunitiesSeeder (~14,039 MainPlace circles, idempotent)
- ThemeCommunitiesSeeder
- ContentBlockSeeder (idempotent, registered in DatabaseSeeder)
- EmailTemplateSeeder (idempotent, registered in DatabaseSeeder)
- Full SA demography data

### Services
- CircleCreationService
- CircleMembershipService
- EmailServiceHandler (Communication — sendTemplate/queueTemplate)
- 9 circle service handler stubs

### Explore Page (see Explore UI Supplement for full detail)
- Two-section layout: top (location browser) + bottom (community types)
- Top section: two-column — left (column browser) + right (location card)
- ExploreCommunities with #[Url] state sync for all key properties
- CommunityTypeFilter, Breadcrumb, ColumnBrowser, CommunityCard,
  SearchOverlay, MapView stub (disabled)
- RequestLocationModal (for unlisted MainPlaces)
- Add Community modal stubs for all community types
- "Could this be your community?" geolocation button
- resources/js/utils/geolocation.js (getUserLocation())
- ExploreCommunities::MAX_HEIGHT_LOCATIONS_COLUMN constant
  controls left column max-height with overflow-y-auto

### Community Page
- Route: GET /communities/{circle} (public, no auth yet), name communities.show
- CommunityPage Livewire component (app/Livewire/Communities/CommunityPage.php)
- Stateless back-link via ?from= query parameter
- layouts/main.blade.php (public layout with nav)
- Displays the circle's administrators (Circle::administrators()) —
  see Circle administrators note below

### Filament Admin Panel
- AdminPanelProvider at /admin (admin + superadmin, plus circle_admins who see
  only a scoped Requests resource; other resources + Dashboard admin-only)
- `Platform` nav group → ContentBlockResource (per-locale content editing)
- `Communication` nav group → EmailTemplateResource (per-locale subject/body)
- `Governance` nav group → RequestResource (view-only + Approve/Deny/Resend)
- x-content-block Blade component (supports collapsible disclosures),
  rendered at the top of the Explore page (explore.welcome_banner) and as
  collapsible how-to guidance in each Add Community modal (keyed off
  CommunityType, language-independent — AddCommunityModal::howToKey())

### Email / Communication (see Email Templates section)
- email_templates table + EmailTemplate model (translatable, cached getByKey)
- EmailServiceHandler with sendTemplate() + queueTemplate()
- TemplateMailable + HTML/plain mail views
- EmailTemplateResource + EmailTemplateSeeder (6 templates)
- Verified end-to-end send to MailHog; EmailServiceHandlerTest covers welcome

### Organisation Approval & Requests (see section above)
- CircleStatus enum + circles.status; requests table + Request model
- AddCommunityModal org form → creates Organisation + Pending circle + Request
- Public RequestController + /requests/confirm/{token} pages (approve/deny)
- Filament RequestResource (Governance) with Approve/Deny/Resend actions
- requests:expire command scheduled daily

### Authentication (manual, Livewire 4)
- Login, Register, ForgotPassword, ResetPassword components
- LogoutController
- Guest and authenticated layouts
- Dashboard view

---

## What Is NOT Yet Built

- Auth/permission guards on Add Community and Request Location buttons
  (TODO comments in place throughout)
- Campaign model fields
- Filament resources beyond ContentBlock + EmailTemplate + Request
- Request types other than organisation_approval (circle_join,
  location_request, circle_association are reserved strings only)
- Role transition after org approval: requester gets circle_admin on approval
  (intended, even for platform admins); switching that to a dedicated
  organisation-staff role during onboarding is future work
- Map view for Explore page (SVG sourcing in progress)
- User profile pages with saved locale preference
- Notification, voting, social media, learning systems
  (service stubs exist, full implementation pending)
- Payment/subscription system
- API endpoints
- Notification system + wiring EmailServiceHandler into OTHER flows
  (registration welcome, circle invitations, password reset) — templates
  exist but aren't triggered by app events yet (organisation-approval IS wired)
- CommunityPage type-specific nested components (placeholder only)
- "Also here" badge on community cards (currently only in column browser)
- Wider placement of x-content-block — currently used on the Explore page
  (explore.welcome_banner) and in the Add Community modals (how_to_add.*);
  other seeded keys (column_browser_hint, community.join_instructions,
  onboarding.new_user_welcome) not yet placed in views
- Real copy for the how-to blocks (campaign/course/event/theme are
  placeholder "test" content) + seeding the organisation how-to block

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
10. Only LocationLevel::Place is terminal — City is NOT terminal
11. circles.description is a JSON column (spatie/laravel-translatable)
    — never treat it as a plain string column
12. content_blocks.content is translatable JSON — always read via
    ContentBlock::get(); the /admin panel is admin/superadmin only
13. email_templates.subject/body are translatable JSON — send via
    EmailServiceHandler; available_variables is developer-set, not admin-edited
14. Tests must NEVER use RefreshDatabase (full migrate fails on sqlite — no
    countries migration) and never hit MailHog (phpunit.xml MAIL_MAILER=array
    + Mail::fake()); build only the tables a test needs
15. All lang keys must exist in lang/en/ before being used in views
16. The Eloquent model is App\Models\Communication\Request — alias it (as
    RequestModel) wherever Illuminate\Http\Request is also imported
17. Approval emails link to the GET landing page route('requests.confirm',
    $token), never the POST approve/deny routes (email clicks are GET)
18. New circles are created Active by CircleCreationService — set
    CircleStatus::Pending explicitly for approval-gated circles
19. Blade: declare component props with /** @var */ hints (comment-free
    @props) so the IDE doesn't flag them; use x-on:click (not @click) for
    Alpine handlers

---

## Folder Structure (key paths)

```
app/
  Contracts/
    Geographic/       HasLocationLevel
    Circles/          HasDefaultServices
    Circleable, Locatable,
    CircleServiceContract (+ containerComponent()), ProvidesCircleIdentity
  Console/Commands/   ExpireRequests (requests:expire),
                      BackfillCircleServices (circles:backfill-services)
  Enums/              CommunityType, LocatableType, LocationLevel, CircleStatus, RequestType, ThemeSuggestionStatus; Forums/ (4 enums)
  Filament/
    Pages/            Dashboard (admin-only nav; redirects circle_admins)
    Resources/
      ContentBlocks/  ContentBlockResource + Pages/
      EmailTemplates/ EmailTemplateResource + Pages/
      Requests/       RequestResource + Pages/ (List, View)
  Mail/               TemplateMailable
  Http/Controllers/   RequestController (+ Auth/LogoutController)
  Http/Middleware/    SetLocaleFromBrowser
  Livewire/Auth/      Login, Register, ForgotPassword, ResetPassword
  Livewire/Communities/ CommunityPage
    Services/         {Forum,News,Events,Media,Voting,Notifications,
                      ManageLearning,ManageSocialMedia}ServiceContainer
  Livewire/Explore/   ExploreCommunities + sub-components
                      RequestLocationModal
  Models/Circles/     Circle, Service
  Models/Communities/ OrganisationCommunity, Campaign, CourseCommunity,
                      LocationCommunity, ThemeCommunity
  Models/Communication/ EmailTemplate, Request
  Models/Demography/  Country, Province, DistrictMunicipality,
                      LocalMunicipality, City, MainPlace, CoordinateData
  Models/             Organisation, Course, User, ContentBlock
  Providers/Filament/ AdminPanelProvider
  Services/Circles/   CircleCreationService, CircleMembershipService,
                      + 9 service handlers
  Services/Communication/ EmailServiceHandler
  Traits/             HasCircle, HasLocation

resources/
  js/
    utils/            geolocation.js
    app.js
  views/
    layouts/          app.blade.php (do not modify),
                      guest.blade.php, authenticated.blade.php,
                      main.blade.php (public pages, with nav),
                      public.blade.php (nav-free, external request pages)
    livewire/auth/    login, register, forgot-password, reset-password
    livewire/explore/ all explore components
    livewire/communities/ community-page
    requests/         confirm, confirmed, denied, expired (approval pages)
    mail/             template.blade.php, template-plain.blade.php
    components/       content-block.blade.php
    components/explore/ empty-state, no-further-levels

tests/
  Services/           EmailServiceHandlerTest (Services testsuite in phpunit.xml)
  Feature/  Unit/     (default Laravel examples)

lang/
  en/                 explore.php, communities.php, navigation.php,
                      validation.php, ui.php
  pt/                 (shared Portuguese base stubs)
  pt_BR/              (test translations — partial)
```

---

# Platform 2027 — Explore UI Supplement

---

## Explore Page — Full Visual Layout

### Route
GET /explore — fully public, no auth required.

### Overall two-section structure

```
┌─────────────────────────────────────────────────────────────────┐
│                        TOP SECTION                              │
│  ┌──────────────────────────┬──────────────────────────────┐   │
│  │    LEFT COLUMN (50%)     │    RIGHT COLUMN (50%)        │   │
│  │                          │                              │   │
│  │  EXPLORE COMMUNITIES     │                              │   │
│  │  [Could this be your     │   LocationCommunity Card     │   │
│  │   community?]     (right)│   (appears on location click)│   │
│  │                          │                              │   │
│  │  [🌍 All] [📍 Locations] │   (placeholder if none      │   │
│  │                          │    selected yet)             │   │
│  │  📍 SA › W.Cape › Eden   │                              │   │
│  │  [🗺 Map] [☰ Browse]     │                              │   │
│  │                          │                              │   │
│  │  ┌──────────────────┐    │                              │   │
│  │  │  Column Browser  │    │                              │   │
│  │  │  (scrollable,    │    │                              │   │
│  │  │  max-height from │    │                              │   │
│  │  │  MAX_HEIGHT_     │    │                              │   │
│  │  │  LOCATIONS_      │    │                              │   │
│  │  │  COLUMN const)   │    │                              │   │
│  │  └──────────────────┘    │                              │   │
│  └──────────────────────────┴──────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                       BOTTOM SECTION                            │
│                                                                 │
│  [🏛 Organisations] [📢 Campaigns] [🎓 Courses]                │
│  [💡 Themes] [📅 Events]                                        │
│                                                                 │
│  [+ Add an Organisation Community]              (right-aligned) │
│                                                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                     │
│  │   Card   │  │   Card   │  │   Card   │                     │
│  └──────────┘  └──────────┘  └──────────┘                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

Mobile: two-column top section collapses to single column
(left on top, right below). Bottom section tabs scroll horizontally.

---

## Top Section — Left Column

### Header row
- Left: "Explore Communities" heading
- Right: "Could this be your community?" button (outline/secondary style)
  — only visible when geolocation resolves successfully to a MainPlace
  — navigates to /explore?circle={id} for that MainPlace
  — TODO: guard with auth + permission check

### Type filter (top section only)
Shows only: [🌍 All] [📍 Locations]
Switching never resets geographic selection.

### Breadcrumb row
```
📍 South Africa › Western Cape › Eden DM       [🗺 Map] [☰ Browse]
```
- "South Africa" always present and always clickable
- Clicking any crumb trims the trail back to that level
- Type label (e.g. "Themes") is display-only — not clickable
- Map toggle: visible but disabled ("Coming soon" tooltip)
- Browse is default view mode

### Column browser
Three-panel geographic drill-down:
```
┌──────────────────┬──────────────────┬──────────────────┐
│   Provinces      │   Districts      │   Local Munis    │
│                  │                  │                  │
│  Western Cape ●  │  Cape Winelands● │  Drakenstein     │
│  KZN             │  Garden Route    │  Stellenbosch    │
│  Gauteng         │  Overberg        │  Witzenberg      │
│  ...             │  ...             │  Breede Valley ● │
└──────────────────┴──────────────────┴──────────────────┘
```
- Max height: ExploreCommunities::MAX_HEIGHT_LOCATIONS_COLUMN
- overflow-y-auto (scrollbar only when needed)
- Each column scrolls independently
- Selected item highlighted per column
- Level badge next to each item

### Terminal level behaviour (MainPlace)
When column browser is displaying MainPlace-level results:
- Next column shows: "No further sub-areas" (x-explore.no-further-levels)
  NOT the empty-state CTA component
- At bottom of MainPlace list: subtle button —
  "Your location not listed? Click here to request us to add it"
  → opens RequestLocationModal
  — TODO: guard with auth + permission check

### "Also here" badge
Circles linked via APPROVED circle_associations merged into location
list with subtle "Also here" badge. Lives ONLY in column browser list —
NOT on community cards (transient flag dropped on re-serialisation).

---

## Top Section — Right Column

When a location is selected in the column browser, the right column
displays the CommunityCard for that location's LocationCommunity circle.

- Same CommunityCard component and styling as bottom section cards
- "View →" button opens /communities/{circle} (not a modal)
- If no location selected: neutral placeholder —
  "Select a location to explore its community"
- Clicking a different location updates the card

---

## Bottom Section

### Type filter tabs
[🏛 Organisations] [📢 Campaigns] [🎓 Courses] [💡 Themes] [📅 Events]

All filtered by current geographic selection from top section.
Switching tab never resets geographic selection.

### Add Community button
Shown in all states (empty and non-empty):
- Non-empty: right-aligned bar above card grid
- Empty: centred below empty state message (replaces "Be the first" CTA)
- Label: "Add a/an {Type} Community" (correct a/an per type hardcoded)
- Opens AddCommunityModal (placeholder — no save logic yet)
- AddCommunityModal renders a collapsible how-to content block per type via
  AddCommunityModal::howToKey() (maps CommunityType → community.how_to_add.*,
  language-independent); falls back to placeholder text for types without one
- TODO: guard with auth + permission check (comment in every instance)

### Community cards
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
"View →" navigates to /communities/{circle} (NOT a modal).

### Level badge labels (CommunityCard::levelBadge)
- Country:              "National"
- Province:             "Provincial"
- DistrictMunicipality: "DM"
- LocalMunicipality:    "LM"
- City/Metro:           "City" (or "Metro" if locatable->metropolis)

Member count is the real active-membership count. Cards get it batch-loaded by
the list (ExploreCommunities::memberCounts() — one grouped query keyed by
circle_id, passed in as :member-count; never a per-card query). The community
PAGE uses CommunityPage::memberCount() (active circle_memberships).

---

## Community Type Icons and Labels

### Icons
- All:                 🌍
- LocationCommunity:   📍
- OrganisationCommunity: 🏛
- Campaign:            📢
- CourseCommunity:     🎓
- ThemeCommunity:      💡
- Event:               📅

### Plural labels (filter tabs)
All / Locations / Organisations / Campaigns / Courses / Themes / Events

### Singular labels (buttons, modals, empty states)
Location Community / Organisation Community / Campaign /
Course Community / Theme Community / Event

### a/an per type (hardcoded)
- "Add an Organisation Community"
- "Add a Campaign"
- "Add a Course Community"
- "Add a Theme Community"
- "Add an Event"

---

## Empty States

Three distinct states. x-explore.empty-state props:
- $icon, $heading, $subheading, $ctaLabel, $ctaAction
- $belowCount, $belowLabel (hide below section if 0)

### State 1: None at this level, exist below
```
        📢
No Campaigns at Province level yet
Be the first to start one that matters to all of Western Cape.
      [ + Add a Campaign ]
──────────────────────────
14 Campaigns in sub-regions  ›
```
Count powered by path LIKE query. "›" drills down.

### State 2: None anywhere in this branch
```
        🌱
   No Campaigns here yet
This is a fresh space waiting to grow.
      [ + Add a Campaign ]
```

### State 3: Terminal level — no further geographic children
```
   No further sub-areas
```
(x-explore.no-further-levels — minimal, no icon, no CTA)
Used ONLY at MainPlace level. Driven by LocatableType::isTerminal().

---

## Request Location Modal (RequestLocationModal)

Triggered by "Your location not listed?" button at MainPlace level.

- Title: "Request a location in {parentLocationName}"
- Subtitle: "We will let you know once it has been added."
- Input: "Location name" (text, no validation yet)
- Accepts: parentLocationName (string), parentCircleId (int, stored for future use)
- Buttons: "Send Request" (stub — closes modal) + "Cancel"
- TODO: implement save/notification logic

---

## Search Overlay

Activated by 🔍 Search button (top right of page header).
Overlays the full page.

- Minimum 2 characters before results appear
- Searches circle.name (LIKE %term%)
- Optionally filtered by selectedType if active
- Results: name + geographic breadcrumb + type badge
- Maximum 10 results
- Clicking result: closes overlay, navigates to circle's
  location in browser, updates right column card

### Livewire properties
- query: string = ''
- open: bool = false

---

## Geolocation — "Could this be your community?"

### JS utility
resources/js/utils/geolocation.js exports getUserLocation():
- Returns Promise<{latitude, longitude}>
- Rejects with {code: 'denied'|'unavailable'|'timeout', message}
- Options defaults: enableHighAccuracy:false, timeout:10000,
  maximumAge:300000 (5 min cache)

### Wiring
- x-init on ExploreCommunities root calls getUserLocation()
- On success: $wire.setUserLocation(latitude, longitude)
- On failure: silent — button never appears

### Server-side chain
setUserLocation() →
  CoordinateData::nearest(lat, lng) →
    ->getMainPlace() →
      ->explorerLocationCommunityUrl() →
        stored in $suggestedCommunityUrl

### CoordinateData::nearest() algorithm
Primary: bounding box ±0.5° + squared Euclidean ORDER BY, LIMIT 1
Fallback (if 0 results): full table scan, same ORDER BY
Composite index on (latitude, longitude).

### MainPlace::explorerLocationCommunityUrl()
Returns /explore?circle={circle_id} or null if no circle exists.

---

## URL State Sync

ExploreCommunities properties with #[Url]:
- selectedCircleId — current geographic selection
- selectedType (top section) — All or Locations
- selectedBottomType — Organisations/Campaigns/Courses/Themes/Events
- viewMode — browse or map

Restores full state on direct URL load including correct breadcrumb trail.

---

## Community Page (/communities/{circle})

Route: GET /communities/{circle} — public (name communities.show), but mount()
404s circles not visible to the viewer via Circle::isVisibleTo() (pending only
for admin/superadmin; mirrors the Explore visibleTo() scope).
Component: CommunityPage (app/Livewire/Communities/CommunityPage.php).
Layout: layouts/main.blade.php (public layout with nav).

### Back link
Stateless — reads ?from= query parameter.
"View →" on CommunityCard appends current Explore URL as
?from={urlencoded explore URL with full query string}.
Falls back to /explore if from is absent or invalid.

### Content
- Community name + type icon (unchanged)
- Geographic breadcrumb
- Circle administrators (Circle::administrators() — comma-joined names, or a
  "no admins" string when empty; rendered via a #[Computed] method)
- Member count (👥; admins count as members — see decision 15/16)
- Description
- Service TABS (badges removed) — every attached service with a
  container_component is a tab, ordered per defaultServices() when the
  circleable implements HasDefaultServices, else attachment order; first tab
  active; active container rendered via <livewire:dynamic-component>. No #[Url]
  sync yet (TODO). See decision 16.
- Join Community button placeholder (right-aligned)

For ORGANISATION communities the top row splits into two halves: left = the
existing location/admins/members + org contact columns; right = "Organisation
members" listing the APPROVED organisation_members
(CommunityPage::organisationMembers(), filtered via hasApprovedInternalRole),
in an overflow-y list. Non-org communities keep the single unsplit top row.

Type-specific nested components: future work (TODO).

---

## Map View (DEFERRED — Phase 2)

[🗺 Map] toggle visible but disabled — "Coming soon" tooltip.
No SVG loaded. Toggle preserved in URL state.

Planned: clickable SVG of SA provinces, sidebar with communities,
Alpine.js hover/highlight, Livewire data fetch on click.
Recommended SVG: amCharts SA provinces map.

---

## Key Interaction Rules

1. Switching community TYPE → preserves geographic selection
2. Clicking geographic item → preserves selected type
3. Clicking breadcrumb crumb → preserves type, trims geographic trail
4. "South Africa" crumb always present and always clickable
5. Type label in breadcrumb is display-only — not clickable
6. "Also here" circles only badged in column browser (not on cards)
7. Add/Join/Start buttons are stubs — TODO auth guards throughout
8. All queries: with(['circleable','locatable','services'])
9. Path column used for all ancestor/descendant queries
10. isTerminal() drives UI hints only; $children->isNotEmpty()
    drives whether to render the next column
11. Map view disabled until SVG sourced and integrated
12. Bottom section filtered by top section's geographic selection
