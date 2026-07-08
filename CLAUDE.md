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

---

## Key Services

### CircleCreationService
Single entry point for creating any circle type.
- Handles name/description auto-population
- Attaches default services
- Wrapped in DB transaction
- Signature: create(type, data, parentCircle?, locatableType, locatableId)
- Circles are created with status Active (DB default) — set
  CircleStatus::Pending after creation for approval-gated types

### CircleMembershipService
Membership management (partially built).

### CoordinateData::nearest(float $lat, float $lng): ?static
Nearest-neighbour lookup:
1. Bounding box ±0.5° + squared Euclidean ORDER BY LIMIT 1
2. Fallback: full table scan if bounding box returns 0 results
   Composite index on (latitude, longitude) exists.
   Do NOT use SQRT — squared distance sufficient for ranking.

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

Route: GET /communities/{circle} — route-model bound to Circle.
Public. No auth middleware yet.
Component: CommunityPage (app/Livewire/Explore/CommunityPage.php)
Layout: layouts/public.blade.php (temporary — pre-auth)

### Back link (stateless)
"View →" on CommunityCard generates:
/communities/{circle}?from={urlencoded current explore URL}
CommunityPage reads ?from= for back link. Falls back to /explore.

### Content
Currently: placeholder (same as former CommunityDetail modal).
Type-specific nested components: future work.

---

## Filament Admin Panel (/admin)

AdminPanelProvider (`app/Providers/Filament/AdminPanelProvider.php`).
- Path `/admin`, panel id `admin`, `->login()`, dark mode on, primary = Amber
- Access restricted to `admin` + `superadmin` roles via
  `User::canAccessPanel()` (User implements `FilamentUser`)
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
(`updateOrCreate` by key). English stubs, empty pt_BR (falls back). Keys:
`email.welcome`, `email.circle_invitation`, `email.password_reset`.

Local mail: MailHog via MAMP — SMTP `localhost:1025`, UI at
`http://localhost:8025/mailhog` (note the `/mailhog` web path).

---

## Organisation Approval & Requests

External-approval workflow: a logged-in user submits a new Organisation
Community; it stays PENDING until the organisation's contact approves it via
an emailed link. Only `organisation_approval` is implemented end-to-end.

### requests table + Request model (`app/Models/Request.php`)
Generic request record: `type`, `status` (default pending), `direction`
(external|internal), `requester_id`, `circle_id`, polymorphic `requestable`,
`respondent_email`, `respondent_user_id`, `token` (unique) + `token_expires_at`,
`responded_at`, `response_note`, `metadata` (JSON), `ulid` (public id), soft deletes.
- `booted()` auto-generates `ulid` (`Str::ulid`) + `token` (`Str::random(64)`)
- Scopes: `pending()`, `expired()`, `external()`, `internal()`
- `createForOrganisation(requester, circle, organisation, respondentEmail, metadata=[])`
  — 7-day token, metadata seeded with an empty `email_log`
- `logEmail(template, recipient, status, error?)` — appends to
  `metadata.email_log` (audit of every send attempt)
- `isExpired()` — `token_expires_at` in the past
- The model is `App\Models\Request` — alias it (`as RequestModel`) wherever
  `Illuminate\Http\Request` is also used

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
`review_url`), `…_confirmed`, `…_denied`.

### Governance admin (Filament)
- `RequestResource` (`app/Filament/Resources/Requests/`) under a `Governance`
  nav group (auto-rendered; provider unchanged)
- List: type/status/direction badges + filters; View: read-only detail +
  email-log table
- Row actions (pending/expired only): **Approve**, **Deny** (optional note),
  **Resend** (regenerates token+expiry, resends request email). Each mirrors
  the controller, logs the email, shows a success/warning notification

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
Public layout: public.blade.php (temporary — used by Explore + CommunityPage)

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
- EmailTemplateSeeder — 6 email templates (welcome/invitation/reset + 3
  organisation-approval; idempotent, updateOrCreate by key)
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

- Full membership system (circle_user pivot + approval workflow)
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
- Language switcher UI
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

- Using Livewire 3 syntax (wire:model.defer, etc.) — this is Livewire 4
- Blade component props: declare them with `/** @var … */` hints (in a
  comment-free `@php` block) so the IDE doesn't flag them as undefined
  variables — and NEVER put `//` comments inside `@props([...])`, it breaks
  the IDE's prop parsing (see resources/views/components/content-block.blade.php)
- Alpine handlers in Blade: use `x-on:click` (not `@click`) so neither the
  IDE nor Blade mistakes it for a directive
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
- Forgetting the Request name clash — the Eloquent model is App\Models\Request;
  alias it (App\Models\Request as RequestModel) in files that also import
  Illuminate\Http\Request
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
